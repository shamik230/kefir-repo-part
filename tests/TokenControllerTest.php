<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Collections\Models\Collection;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Payments\Controllers\PaymentController;
use Marketplace\Tokens\Actions\ChangeTokenSaleStatusAction;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Queries\TokenQuery;
use Marketplace\Transactions\Models\Transaction;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;

class TokenControllerTest extends TestCase
{
    use DatabaseTransactions;

    function testIndex()
    {
        /** @var Token $token */
        $token = Token::query()
            ->hidden(false)
            ->moderationAccessChecked(null)
            ->whereHas('upload_file')
            ->inRandomOrder()
            ->firstOrFail();

        $this->getJson("/api/v1/token/$token->id")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $token->id])
            ->assertJsonStructure([
                'id',
                'wallet',
                'sbp',
            ]);
    }

    function testGlobalSearch()
    {
        $this->getJson('/api/v1/search?query=' . 'абв')
            ->assertSuccessful()
            ->assertJsonStructure([
                'tokens',
                'collections',
                'users',
            ]);
    }

    function testCreate()
    {
        [$user, $collection] = $this->prepareForTokenCreation();

        $file = UploadedFile::fake()->image('someshit.jpg', 400, 400);
        $data = [
            'name' => Str::random(),
            'description' => '<p>' . Str::random() . '</p>',
            'collection_id' => $collection->id,
            'price' => rand(100, 100000),
            'hidden' => Str::random(),
            'file' => $file,
            'preview' => $file,
        ];

        $this->postJson('/api/v1/token/create', $data)
            ->assertSuccessful()
            ->assertJsonFragment(array_merge($data, [
                'moderation_status_id' => ModerationStatus::MODERATING_ID,
            ]));
    }

    function testCreateWithoutFileShouldReturn422()
    {
        [$user, $collection] = $this->prepareForTokenCreation();

        $this->postJson('/api/v1/token/create', [
            'name' => Str::random(),
            'description' => '<p>' . Str::random() . '</p>',
            'price' => rand(100, 10000),
            'file' => 'str',
            'preview' => 'random',
        ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['file', 'preview'],
            ]);
    }

    function testPutTokenOnSale()
    {
        app()->bind(PaymentController::class, function () {
            return new class extends PaymentController {
                function statusWallet(): array
                {
                    return ['data' => ['status' => 'open']];
                }
            };
        });

        app()->bind(ChangeTokenSaleStatusAction::class, function () {
            return new class extends ChangeTokenSaleStatusAction {
                function execute(Token   $token,
                                 bool    $isSale,
                                 ?float  $price = null,
                                 bool    $notOwner = false,
                                 bool    $sync = false,
                                 ?string $hash = null): Token
                {
                    return $token;
                }
            };
        });

        $tokenQueryFn = function ($q) {
            /** @var TokenQuery $q */
            $q->moderated()
                ->onSale()
                ->exported(false)
                ->internal()
                ->whereDoesntHave('transactions', function ($q) {
                    $q->where('status', Transaction::STATUS_PENDING);
                });
        };
        /** @var User $user */
        $user = User::query()->activated()
            ->individual()
            ->whereHas('tokens', $tokenQueryFn)
            ->whereHas('fiat', function ($q) {
                $q->where('is_active', true)
                    ->where('status', 'open');
            })
            ->with(['tokens' => $tokenQueryFn])
            ->inRandomOrder()
            ->first();
        /** @var Token $token */
        $token = $user->tokens->first();

        Auth::login($user);

        $this->postJson('/api/v1/token/sale', [
            'token_id' => $token->id,
            'price' => $token->price,
            'transaction_hash' => Str::uuid()->toString(),
        ])
            ->assertSuccessful()
            ->assertJsonFragment([
                'id' => $token->id,
                'user_id' => $token->user_id,
                'name' => $token->name,
            ])
            ->assertJsonStructure(['token', 'transaction', 'commission']);
    }

    function testCheckTypeToken()
    {
        $getTokenByTypeFn = function (string $type): Token {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return Token::query()
                ->where('type', $type)
                ->hidden(false)
                ->moderated()
                ->firstOrFail();
        };
        $tokenImage = $getTokenByTypeFn('image/png');
        $tokenGif = $getTokenByTypeFn('image/gif');
        $tokenVideo = $getTokenByTypeFn('video/mp4');
        $tokenAudio = $getTokenByTypeFn('audio/mpeg');

        $this->getJson("/api/v1/token/$tokenImage->id")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $tokenImage->id, 'type' => $tokenImage->type]);

        $this->getJson("/api/v1/token/$tokenGif->id")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $tokenGif->id, 'type' => $tokenGif->type]);

        $this->getJson("/api/v1/token/$tokenVideo->id")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $tokenVideo->id, 'type' => $tokenVideo->type]);

        $this->getJson("/api/v1/token/$tokenAudio->id")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $tokenAudio->id, 'type' => $tokenAudio->type]);
    }

    // Helpers

    /**
     * @return array{0: User, 1: Collection}
     */
    protected function prepareForTokenCreation(): array
    {
        Queue::fake();
        app()->bind(CollectionController::class, function () {
            return new class extends CollectionController {
                function resizer($file, $width, $height)
                {
                    return null;
                }
            };
        });
        /** @var User $user */
        $user = User::query()
            ->whereHas('collections', function ($q) {
                $q->where('moderation_status_id', ModerationStatus::MODERATED_ID);
            })
            ->with([
                'collections' => function ($q) {
                    $q->where('moderation_status_id', ModerationStatus::MODERATED_ID)->limit(1);
                },
            ])
            ->activated()
            ->individual()
            ->inRandomOrder()
            ->firstOrFail();
        Auth::login($user);

        return [$user, $user->collections->first()];
    }

    function testPutTokenOnSaleShouldChangeIsSaleTokenAttribute()
    {
        Auth::logout();
        app()->bind(PaymentController::class, function () {
            return new class extends PaymentController {
                function statusWallet(): array
                {
                    return [
                        'data' => [
                            'status' => 'open',
                        ],
                    ];
                }
            };
        });
        /** @var Token $token */
        $token = Token::query()
            ->inRandomOrder()
            ->moderated()
            ->hidden(false)
            ->where('is_sale', false)
            ->whereDoesntHave('lots')
            ->whereNull('external_id')
            ->whereHas('user', function ($query) {
                $query->whereHas('fiat');
            })
            ->with('user')
            ->first();

        $this->postJson('/api/v1/token/sale', [
            'token_id' => $token->id,
            'price' => rand(100, 1000),
            "only_validations" => 0,
            "ownerKefirium" => 0,
        ])
            ->assertUnauthorized();

        Auth::logout();

        $user = $token->user;

        Auth::login($user);
        $price = rand(100, 1000);

        $this->postJson('/api/v1/token/sale', [
            'token_id' => $token->id,
            'price' => $price,
            'only_validations' => 0,
            'ownerKefirium' => 0,
            'transaction_hash' => Str::uuid()->toString(),
        ])
            ->assertSuccessful()
            ->assertJsonFragment([
                'id' => $token->id,
                'price' => $price,
            ]);

        Auth::logout();
        $user = User::query()
            ->where('id', '!=', $token->user_id)
            ->activated()
            ->individual()
            ->inRandomOrder()
            ->first();
        Auth::login($user);
        $this->postJson('/api/v1/token/sale', [
            'token_id' => $token->id,
            'price' => $price,
            "only_validations" => 0,
            "ownerKefirium" => 0,
        ])
            ->assertForbidden();
    }

    function testRemoveTokenFromSaleShouldChangeFromSaleTokenAttribute()
    {
        Auth::logout();
        app()->bind(PaymentController::class, function () {
            return new class extends PaymentController {
                function statusWallet(): array
                {
                    return [
                        'data' => [
                            'status' => 'open',
                        ],
                    ];
                }
            };
        });
        /** @var Token $token */
        $token = Token::query()
            ->inRandomOrder()
            ->hidden(false)
            ->moderated()
            ->where('is_sale', false)
            ->whereNull('external_id')
            ->whereHas('user', function ($query) {
                $query->whereHas('fiat');
            })
            ->with('user')
            ->first();

        $this->postJson('/api/v1/token/from/sale', [
            'token_id' => $token->id,
            "ownerKefirium" => 0,
        ])
            ->assertUnauthorized();

        Auth::logout();

        $user = $token->user;

        Auth::login($user);

        $this->postJson('/api/v1/token/from/sale', [
            'token_id' => $token->id,
            "ownerKefirium" => 0,
            'transaction_hash' => Str::uuid()->toString(),
        ])
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $token->id]);

        Auth::logout();

        $user = User::query()
            ->where('id', '!=', $token->user_id)
            ->activated()
            ->individual()
            ->inRandomOrder()
            ->first();
        Auth::login($user);
        $this
            ->postJson('/api/v1/token/from/sale', [
                'token_id' => $token->id,
                "ownerKefirium" => 0,
            ])
            ->assertForbidden();
    }
}
