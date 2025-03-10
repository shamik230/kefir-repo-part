<?php

namespace Marketplace\Tokens\Controllers;

use Auth;
use Cache;
use Carbon\Carbon as CarbonCarbon;
use DB;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log as IlluminateLog;
use Input;
use Lang;
use Log;
use Marketplace\BlockchainAccount\Models\BlockchainAccount;
use Marketplace\Categories\Models\Category;
use Marketplace\Collections\Actions\CreateCollectionAction;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Collections\Models\Collection;
use Marketplace\Entities\Models\Entity;
use Marketplace\Fiat\Models\Fiat;
use Marketplace\Galleries\Models\Gallery;
use Marketplace\Barrel\Models\Barrel;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Payments\Controllers\PaymentController;
use Marketplace\Payments\Models\Payment;
use Marketplace\Payments\Services\BlockchainService;
use Marketplace\Payments\Services\OwnerNodeService;
use Marketplace\Tokens\Actions\ChangeTokenSaleStatusAction;
use Marketplace\Tokens\Actions\ResizerGifAction;
use Marketplace\Tokens\Component\TokenComponentInterface;
use Marketplace\Tokens\Component\TokenOwnershipComponentInterface;
use Marketplace\Tokens\DTO\VerifyTokenOwnershipDTO;
use Marketplace\Tokens\ImageIpfs;
use Marketplace\Tokens\Jobs\ResizerGif;
use Marketplace\Tokens\Jobs\ResizerMp4;
use Marketplace\Tokens\Jobs\UploadImage;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Queries\TokenQuery;
use Marketplace\Tokens\Requests\TokenRefreshRequest;
use Marketplace\Tokens\Rules\WalletCheck;
use Marketplace\Tokens\Services\IpfsUrlService;
use Marketplace\Tokens\Services\TokenImageService;
use Marketplace\Tokens\Services\TokenService;
use Marketplace\Tokens\Traits\TokenCreationRules;
use Marketplace\TokenVerification\Models\TokenVerification;
use Marketplace\Traits\KefiriumBot;
use Marketplace\Traits\Logger;
use Marketplace\Traits\SendEmail;
use Marketplace\Traits\UsesCache;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use Project\Services\RequestsService;
use Queue;
use RainLab\User\Classes\TransactionUploadBlockchainTokenEvent;
use RainLab\User\Models\User;
use RainLab\User\Queries\UserQuery;
use RainLab\User\Services\PushWebsocketService;
use Response;
use Session;
use Str;
use Validator;

class TokenController
{
    use TokenCreationRules, Logger, UsesCache, KefiriumBot, SendEmail;

    const LOG_SECTION = 'Token';

    protected const CACHE_MINUTES = 5;

    /**
     *  Get token,
     * @OA\Get(
     * description = "поле galleries_full возвращает галлереи где добавлен токе и все галлереи авторизованного пользователя пользователя, если токен добавлен в галлерею то в galleries_full поле added будет true",
     *     path="/api/v1/token/{id}",tags={"Token"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id token",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *      @OA\Response(response="404", description="Not Found"),
     *      @OA\Response(response="401", description="Not Autorization"),
     * )
     */
    function index($id): JsonResponse
    {
        /** @var Token $token */
        $token = Token::verification($id)
            ->tokenItem($id)
            ->withPaymentSystemsStatuses()
            ->with([
                'collection',
                'preview_upload',
                'galleries',
                'blockchaintoken.blockchain.logo',
                'moderation_status',
                'author.legal',
                'author.avatar',
                'user.legal',
                'user.avatar',
                'transactions' => function ($q) {
                    $q->where('status', Transaction::STATUS_PENDING)
                        ->with(['transaction_types']);
                },
            ])
            ->withCount(['favorites'])
            ->find($id);

        if (!$token || ($token->inModeration() && $token->user_id != Auth::id())) {
            return response()->json('', 404);
        }

        $return = $token->toArray();
        $return['file'] = $return['file'] ?? null;
        return response()->json($return);
    }

    function redirectTokenPage(Request $request, string $blockchain_contract_address, int $blockchain_token_id)
    {
        /**
         *  Get token,
         * @OA\Get(
         * description = "получить страницу токена по адресу контракта",
         * path="/token/polygon/{blockchain_contract_address}/{blockchain_token_id}",tags={"Token"},
         * @OA\Parameter(
         *     name="blockchain_contract_address",
         *     in="path",
         *     description="Адрес контракта блокчейна",
         *     required=true,
         *     @OA\Schema(
         *         type="string",
         *     ),
         * ),
         * @OA\Parameter(
         *     name="blockchain_token_id",
         *     in="path",
         *     description="Идентификатор токена",
         *     required=true,
         *     @OA\Schema(
         *         type="integer",
         *     ),
         * ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="404", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */
        $validator = Validator::make([
            'blockchain_contract_address' => $blockchain_contract_address,
            'blockchain_token_id' => $blockchain_token_id,
        ], [
            'blockchain_contract_address' => 'required|string',
            'blockchain_token_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $token = Token::query()
            ->where('external_address', $blockchain_contract_address)
            ->where('external_id', $blockchain_token_id)
            ->first();

        $tokenUrl = $token ? url("/collection/token/$token->id") : url("/error");

        return redirect($tokenUrl);
    }


    /**
     * Create Token
     *
     * @OA\Post(
     *     path="/api/v1/token/create",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *              mediaType="multipart/form-data",
     *             @OA\Schema(required={"name","description","price"},
     *              @OA\Property(description="name",property="name",type="string"),
     *              @OA\Property(description="collection_name",property="collection_name",type="string"),
     *              @OA\Property(
     *                             description="collection_preview",
     *                             property="collection_preview",
     *                             type="string", format="binary"
     *                         ),
     *              @OA\Property(description="description",property="description",type="string"),
     *              @OA\Property(description="external_reference",property="external_reference",type="string"),
     *              @OA\Property(description="price",property="price",type="number"),
     *              @OA\Property(description="collection_id",property="collection_id",type="number"),
     *              @OA\Property(description="is_sale",property="is_sale",type="boolean", example=true),
     *              @OA\Property(description="hidden",property="hidden",type="text"),
     *              @OA\Property(
     *                             description="file",
     *                             property="file",
     *                             type="string", format="binary"
     *                         ),
     *              @OA\Property(
     *                             description="preview",
     *                             property="preview",
     *                             type="string", format="binary"
     *                         ),
     * )
     * )
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Validation Error"),
     * )
     */
    function create(TokenImageService $imageService): JsonResponse
    {
        try {
            $input = Input::all();
            $validator = Validator::make(
                $input,
                $this->getTokenCreationRules(Input::file('file')),
                Lang::get('marketplace.tokens::validation')
            );

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->messages()], 422);
            }

            if (Input::get('collection_name') && Input::file('collection_preview')) {
                $collectionId = app(CreateCollectionAction::class)->execute(
                    $input['collection_name'],
                    Category::CATEGORY_ART_ID,
                    Auth::user()->id,
                    "Коллекция " . Auth::user()->name,
                    ModerationStatus::MODERATING_ID,
                    Input::file('collection_preview')
                );
            } else {
                $collectionId = Input::get('collection_id');
            }

            $token = Token::create([
                'name' => $input['name'],
                'description' => $input['description'],
                'collection_id' => $collectionId,
                'content_on_redemption' => $input['content_on_redemption'] ?? '',
                'royalty' => 5,
                'price' => $input['price'],
                'hidden' => $input['hidden'] ?? '',
                'user_id' => Auth::id(),
                'type' => Input::file('file')->getMimeType(),
                'author' => Auth::id(),
                'is_sale' => false,
                'moderation_status_id' => ModerationStatus::MODERATING_ID,
                'external_reference' => $input['external_reference'] ?? '',
            ]);
            $token->save();
            $resultAdaptation = $imageService->adaptationImage(
                Input::file('file'),
                $token,
                app(CollectionController::class),
                app(ResizerGifAction::class),
                app(ResizerMp4::class),
                Input::file('preview')
            );

            if (!$resultAdaptation) {
                throw new Exception('Обработка изображения не удалась');
            }

            Queue::push(ImageIpfs::class, [
                'token' => $token->id,
                'file' => $token->upload_file->getLocalPath(),
                'filename' => $token->upload_file->file_name,
            ]);

            return response()->json($token->load([
                'collection',
                'user',
                'galleries',
                'transactions.transaction_types',
            ]));
        } catch (Exception $e) {
            $this->logDebug('@createToken', ['exception' => $e->getMessage(), 'exceptionTrace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 400);
        }
    }

    public function gifResizer(Token $token, $file)
    {
        $token->preview_upload = $file;
        $token->save();
        Queue::push(ResizerGif::class, ['file' => $token->preview_upload->getLocalPath()]);
    }

    public function checkType($file)
    {
        $typeFile = explode("/", $file);
        if ($typeFile[0] == "image") {
            return true;
        }
        return false;
    }


    public function trend()
    {

        /**
         *  Trend Tokens,
         * @OA\Get(
         *     path="/api/v1/tokens/trend",tags={"Token"},
         *
         *
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="404", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */


        $token = Token::where('is_hidden', false)->with('blockchaintoken')->with('galleries')->with('collection')->with([
            'author' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->with([
            'user' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->limit(6)->orderBy('created_at', 'DESC')->get();
        return response()->json($token, 200);
    }

    /**
     *  Получение получение купленных токено пользователя,
     * @OA\Get(
     *     path="/api/v1/tokens/user/{id}/pay",tags={"Token"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id user",
     *         required=true,
     *         example="1",
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *      @OA\Response(response="401", description="Not Found"),
     *      @OA\Response(response="401", description="Not Autorization"),
     * )
     */
    function payTokens($user_id)
    {
        $page = Input::get('page');

        return Cache::remember(
            'tokens_users_id_pay' . $user_id . $page,
            now()->addMinutes(6),
            function () use ($user_id) {
                $tokens = Token::query()
                    ->moderationAccessChecked($user_id)
                    ->where('user_id', $user_id)
                    ->hidden(false)
                    ->orderBy('updated_at', 'desc')
                    ->with([
                        'blockchaintoken',
                        'galleries',
                        'moderation_status',
                        'collection',
                        'author' => function ($query) {
                            $query->with(['legal', 'avatar']);
                        },
                        'user' => function ($query) {
                            $query->with(['legal', 'avatar']);
                        },
                    ])
                    ->withCount(['favorites'])
                    ->paginate(16)
                    ->toArray();

                return response()->json($tokens);
            }
        );
    }

    /**
     * Получение созданных пользователем токенов
     * @OA\Get(
     *     path="/api/v1/tokens/author/{id}/pay",tags={"Token"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id author",
     *         required=true,
     *         example="1",
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *      @OA\Response(response="401", description="Not Found"),
     *      @OA\Response(response="401", description="Not Autorization"),
     * )
     */
    function authorTokens($user_id)
    {
        $page = Input::get('page');
        return Cache::remember(sprintf("tokens_author_id_auth_%s_user_%s_page_%s", Auth::id(), $user_id, $page), 360, function () use ($user_id) {
            $tokens = Token::query()
                ->hiddenChecked($user_id)
                ->moderationAccessChecked($user_id)
                ->where('author_id', $user_id)
                ->with([
                    'blockchaintoken',
                    'galleries',
                    'moderation_status',
                    'collection',
                    'author' => function ($query) {
                        $query->with(['legal', 'avatar']);
                    },
                    'user' => function ($query) {
                        $query->with(['legal', 'avatar']);
                    },
                ])
                ->withCount('favorites')
                ->paginate(16)
                ->toArray();

            return response()->json($tokens);
        });
    }

    /**
     * @param HttpRequest $request
     * @param TokenOwnershipComponentInterface $tokenOwnership
     * @param TokenComponentInterface $tokenComponent
     * @return JsonResponse
     */
    public function verifyTokenOwnership(
        HttpRequest                      $request,
        TokenOwnershipComponentInterface $tokenOwnership,
        TokenComponentInterface          $tokenComponent
    )
    {
        try {
            $dto = VerifyTokenOwnershipDTO::hydrate($request->all());
            if (!$owned = $tokenOwnership->checkBySecretKey($dto->getTokenId(), $dto->getSecretKey())) {
                return response()->json(['owned' => $owned]);
            }
            $amount = $tokenComponent->getSameCollectionTokensSold($dto->getTokenId());
            return response()->json(
                [
                    'owned' => $owned,
                    'sold' => $amount,
                ]
            );
        } catch (Exception $e) {
            IlluminateLog::error(
                sprintf("Произошла ошибка %s", $e->getMessage()),
                [
                    'section' => self::LOG_SECTION,
                    'action' => __FUNCTION__,
                    'data' => $request->all(),
                ]
            );
        }
        return response()->json(
            [
                'Error' => "Произошла Внутренняя ошибка сервиса",
            ],
            500
        );
    }

    public function userTokens()
    {


        $token = Token::where('user_id', Auth::user()->id)->withCount('favorites')->with('galleries')->with('blockchaintoken')->with('collection')->with([
            'author' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->with([
            'user' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->paginate(16);
        return response()->json($token, 200);
    }

    /**
     * Выставить токен на продажу
     *
     * @OA\Post(
     *     path="/api/v1/token/sale",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(required={"token_id", "price"},
     *                 @OA\Property(property="token_id",type="integer"),
     *                 @OA\Property(property="price",type="number"),
     *                 @OA\Property(property="only_validations",type="number"),
     *                 @OA\Property(property="transaction_hash",type="string"),
     *                 @OA\Property(property="ownerKefirium",type="boolean"),
     *             )
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Invalide email or password"),
     * )
     */
    function putTokenOnSale(TokenService $tokenService): JsonResponse
    {
        App::setLocale('ru');

        /**
         * @var array{
         *     token_id: int,
         *     price: float,
         *     only_validations: bool|null,
         *     ownerKefirium: bool|null,
         *     transaction_hash: string|null
         * } $input
         */
        $input = Input::all();
        $validator = Validator::make($input, [
            'token_id' => 'required|integer',
            'price' => 'required|numeric|min:100',
            'only_validations' => 'nullable',
            'ownerKefirium' => 'boolean|nullable',
            'transaction_hash' => 'sometimes|nullable|string',
        ], Lang::get('marketplace.tokens::validation'));

        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }

        $checkIsAvailableForSale = $this->validateTokenAvailableForSale($input['token_id']);

        if (!$checkIsAvailableForSale['success']) {
            return response()->json(['error' => $checkIsAvailableForSale['error']], 403);
        } elseif ($input['only_validations'] ?? false) {
            return response()->json(['success' => true]);
        }

        /** @var Token $token */
        $token = $checkIsAvailableForSale['token'];
        // Обновить currency токена перед его выставлением на продажу
        $token->update(['currency' => Transaction::CURRENCY_RUR_TYPE]);
        if ($input['ownerKefirium'] ?? false) {
            // Если токен принадлежит кефиру - выставляем на продажу через БЧ сервис
            if (empty($token->external_address)) {
                return response()->json(['error' => 'Токен не имеет связи с блокчейном.'], 400);
            }
            $blockchainTokenItem = $tokenService->getBlockchainItem($token);
            if ($blockchainTokenItem->tokenOnSale()) {
                // Токен уже на продаже в бч
                $tokenService->syncStatus($token, true, $blockchainTokenItem->price());
                return response()->json(
                    ['error' => 'Токен уже на продаже. Синхронизируем статус с блокчейном.'],
                    400
                );
            }

            $putOnSaleResponse = $tokenService->putForBlockchainSale($token, $input['price']);
            if (!$putOnSaleResponse->success() || $putOnSaleResponse->status()->failed()) {
                return response()->json([
                    'error' => 'Ошибка при выставлении токена на продажу. Попробуйте позже',
                ], 500);
            }
            $exposedForSaleTransaction = $putOnSaleResponse->exposedForSaleTransaction();
        } else {
            $exposedForSaleTransaction = Transaction::query()->createExposeForSale(
                $token->user_id,
                $token->id,
                $input['price'],
                $input['transaction_hash'],
                Transaction::STATUS_SUCCESS,
                $token->currency
            );
            $tokenService->putForSale($token, $input['price'], $exposedForSaleTransaction);
        }

        return response()->json([
            'token' => $token->refresh()->load(['user.legal', 'user.avatar', 'author.legal', 'author.avatar']),
            'transaction' => $exposedForSaleTransaction->loadMissing([
                'transaction_types.icon',
                'from_user' => function ($q) {
                    /** @var UserQuery $q */
                    $q->with(['legal', 'avatar']);
                },
            ]),
            'commission' => env('COMMISSION'),
        ]);
    }

    /**
     * Снять токен с продажи
     *
     * @OA\Post(
     *     path="/api/v1/token/from/sale",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(required={"token_id"},
     *              @OA\Property(property="token_id",type="integer"),
     *              @OA\Property(property="ownerKefirium",type="boolean"),
     *              @OA\Property(property="transaction_hash",type="string"),
     *              )
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Invalide email or password"),
     * )
     */
    function removeTokenFromSale(TokenService $tokenService): JsonResponse
    {
        App::setLocale('ru');

        /**
         * @var array{
         *     token_id: int,
         *     ownerKefirium: bool|null,
         *     transaction_hash: string|null
         * } $input
         */
        $input = Input::all();
        $validator = Validator::make($input, [
            'token_id' => 'required|integer',
            'ownerKefirium' => 'boolean|nullable',
            'transaction_hash' => 'sometimes|nullable|string',
        ], Lang::get('marketplace.tokens::validation'));

        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }

        $checkIsAvailableForSale = $this->validateTokenAvailableForSale($input['token_id']);
        if (!$checkIsAvailableForSale['success']) {
            return response()->json(['error' => $checkIsAvailableForSale['error']], 403);
        }

        /** @var Token $token */
        $token = $checkIsAvailableForSale['token'];

        if ($input['ownerKefirium'] ?? false) {
            if (empty($token->external_address)) {
                return response()->json(['error' => 'Токен не имеет связи с блокчейном'], 400);
            }

            $blockchainTokenItem = $tokenService->getBlockchainItem($token);
            if (!$blockchainTokenItem->tokenOnSale()) {
                // Токен уже на продаже в бч
                $tokenService->syncStatus($token, false, 0);
                return response()->json(
                    ['error' => 'Токен уже снят с продажи. Синхронизируем статус с блокчейном.'],
                    400
                );
            }

            $removeTokenFromSale = $tokenService->removeFromBlockchainSale($token);
            $exposedForSaleTransaction = $removeTokenFromSale->removedFromSaleTransaction();
            if (!$removeTokenFromSale->success() || $removeTokenFromSale->status()->failed()) {
                return response()->json([
                    'error' => 'Ошибка при снятии токена с продажи. Попробуйте позже',
                ], 500);
            }
        } else {
            $exposedForSaleTransaction = Transaction::query()->createRemovedFromSale(
                $token->user_id,
                $token->id,
                $input['transaction_hash'],
                Transaction::STATUS_SUCCESS,
                $token->currency
            );
            $tokenService->removeFromSale($token, $exposedForSaleTransaction);
        }

        return response()->json([
            'token' => $token->refresh()->load(['user.legal', 'user.avatar', 'author.legal', 'author.avatar']),
            'transaction' => $exposedForSaleTransaction->loadMissing([
                'transaction_types.icon',
                'from_user' => function ($q) {
                    /** @var UserQuery $q */
                    $q->with(['legal', 'avatar']);
                },
            ]),
            'commission' => env('COMMISSION'),
        ]);
    }


    function changeOwnerByWallet(OwnerNodeService $ownerNodeService, BlockchainService $blockchainService)
    {
        /**
         * Сменить владельца токена
         * @OA\Post(
         *     path="/api/v1/token/changeOwnerByWallet",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *             mediaType="application/json",
         *             @OA\Schema(required={"token_id", "owner_wallet"},
         *
         *              @OA\Property(property="token_id",type="integer"),
         *              @OA\Property(property="owner_wallet",type="string"),
         *              )
         *         ),
         *
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */
        /** @var Token $token */
        $token = Token::query()->findOrFail(Input::get('token_id'));
        App::setLocale('ru');
        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => ['required', 'integer'],
                'owner_wallet' => ['required', 'string', new WalletCheck($ownerNodeService, $token)],
            ],
            Lang::get('marketplace.tokens::validation')
        );

        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        if ($token->transactions()->where('status', Transaction::STATUS_PENDING)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'У токена есть неоконченные транзакции'], 422);
        }

        $userIdOld = $token->user_id;
        if ($token->is_sale == true) {
            $changeSaleToken = new ChangeTokenSaleStatusAction();
            $changeSaleToken->execute($token, false);
            $removeFromSale = $blockchainService->removeFromSale($token);
            Log::info('removeFromSale: ' . var_export($removeFromSale, true));
        }
        $token->user_id = Auth::user()->id;
        $token->save();
        $paymentTransfer = Payment::query()->createTransfer($token->user_id, $userIdOld, $token);
        Transaction::create([
            'from_user_id' => $userIdOld,
            'token_id' => $token->id,
            'transaction_types_id' => TransactionTypes::TYPE_TRANSFER_ID,
            'from_payment_id' => $paymentTransfer->id,
            'price' => $token->price
        ]);
        $this->sendEmail(
            'change.ownerwallet',
            [
                'name' => $token->name,
                'link' => env('APP_URL') . '/collection/token/' . $token->id,
                'price' => $token->price,
                'date' => CarbonCarbon::now(),
            ],
            $token->user->email,
            "@changeOwnerByWallet"
        );
        return response()->json(['message' => 'success'], 200);
    }


    public function userIsHidden()
    {


        /**
         * Изменить значение is_hidden
         * @OA\Post(
         *     path="/api/v1/token/hidden/",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *             mediaType="application/json",
         *             @OA\Schema(required={"token_id"},
         *
         *              @OA\Property(property="token_id",type="integer"),
         *
         *              )
         *         ),
         *
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */
        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => 'required|integer',

            ]
        );

        if ($validator->fails()) {

            return response()->json($validator->messages(), 422);
        }
        $token = Token::where('user_id', Auth::user()->id)->where('id', Input::get('token_id'))->first();
        if ($token && $token->moderation_status_id == 3) {
            $token->is_hidden = !$token->is_hidden;
            $token->save();
            return response()->json($token, 200);
        }
        return response()->json(["Error" => ['Not Found']], 404);
    }

    /**
     * Hidden tokens
     *
     * @OA\Get(
     *     path="/api/v1/user/tokens/hidden",tags={"Token"},
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="401", description="Not Autorized"),
     * )
     */
    function userTokensIsHidden(): JsonResponse
    {
        $tokens = Token::query()
            ->hidden()
            ->ofUser(Auth::id())
            ->with([
                'blockchaintoken',
                'galleries',
                'favorites',
                'collection',
                'author' => function ($query) {
                    $query->with(['legal', 'avatar']);
                },
                'user' => function ($query) {
                    $query->with(['legal', 'avatar']);
                },
                'moderation_status',
            ])
            ->paginate(16);

        return response()->json($tokens);
    }

    public function search(int $id)
    {
        /**
         *  Search tokens,
         * @OA\Get(
         *     path="/api/v1/user/{id}/tokens/search",tags={"Search"},
         *     @OA\Parameter(
         *         name="query",
         *         in="query",
         *         description="query",
         *         example="test",
         *         required=true,
         *         @OA\Schema(
         *             type="string",
         *         ),
         *     ),
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         description="user_id",
         *         example="1",
         *         required=true,
         *         @OA\Schema(
         *             type="integer",
         *         ),
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="401", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */

        $query = Input::get('query');
        $query ? $query : $query = "";
        $tokens = Token::Hidden($id)->where('user_id', $id)->with('moderation_status')->where('name', 'ilike', '%' . $query . '%')->with([
            'blockchaintoken' => function ($query) {
                $query->with([
                    'blockchain' => function ($query) {
                        $query->with('logo');
                    },
                ]);
            },
        ])->withCount('favorites')->with('collection')->with('galleries')->with([
            'author' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->with([
            'user' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->with('moderation_status')->paginate(16)->appends(['query' => Input::get('query')])->toArray();


        return response()->json($tokens, 200);
    }


    public function searchAuthor(int $id)
    {
        /**
         *  Search tokens,
         * @OA\Get(
         *     path="/api/v1/author/{id}/tokens/search",tags={"Search"},
         *     @OA\Parameter(
         *         name="query",
         *         in="query",
         *         description="query",
         *         example="test",
         *         required=true,
         *         @OA\Schema(
         *             type="string",
         *         ),
         *     ),
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         description="author_id",
         *         example="1",
         *         required=true,
         *         @OA\Schema(
         *             type="integer",
         *         ),
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="401", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */

        $query = Input::get('query');
        $query ? $query : $query = "";
        $tokens = Token::Hidden($id)->where('author_id', $id)->with('moderation_status')->where('name', 'ilike', '%' . $query . '%')->with([
            'blockchaintoken' => function ($query) {
                $query->with([
                    'blockchain' => function ($query) {
                        $query->with('logo');
                    },
                ]);
            },
        ])->withCount('favorites')->with('collection')->with([
            'author' => function ($query) {
                $query->with('legal')->with('galleries')->with('avatar');
            },
        ])->with([
            'user' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->paginate(16)->toArray();


        return response()->json($tokens, 200);
    }

    public function searchHidden()
    {
        /**
         *  Search tokens hidden,
         * @OA\Get(
         *     path="/api/v1/user/tokens/search/hidden",tags={"Search"},
         *     @OA\Parameter(
         *         name="query",
         *         in="query",
         *         description="query",
         *         example="test",
         *         required=true,
         *         @OA\Schema(
         *             type="string",
         *         ),
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="401", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */

        $query = Input::get('query');
        $query ? $query : $query = "";
        $tokens = Token::where('is_hidden', true)->with('moderation_status')->where('user_id', Auth::user()->id)->where('name', 'ilike', '%' . $query . '%')->with([
            'blockchaintoken' => function ($query) {
                $query->with([
                    'blockchain' => function ($query) {
                        $query->with('logo');
                    },
                ]);
            },
        ])->withCount('favorites')->with('galleries')->with('collection')->with([
            'author' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->with([
            'user' => function ($query) {
                $query->with('legal')->with('avatar');
            },
        ])->paginate(16)->toArray();


        return response()->json($tokens, 200);
    }


    /**
     *  Filter tokens ,
     * @OA\Get(path="/api/v1/user/{id}/tokens/filter",tags={"Filter"},
     *     description = "price - по цене ,created_at - по дате создания, payment_date - по дате оплаты, polygon - токены с полигона, kefir - токены с кефира",
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="query",
     *         example="price",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="user_id",
     *         example="1",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *      @OA\Response(response="401", description="Not Found"),
     *      @OA\Response(response="401", description="Not Autorization"),
     * )
     */
    function filter($id): JsonResponse
    {
        $query = Input::get('query');
        $path = Input::get('path');
        $page = Input::get('page');

        $value = Cache::remember(
            'filter_tokens_' . $query . 'id' . $id . 'path' . $path . 'page' . $page,
            360,
            function () use ($query, $id) {
                return $this->filterByParameter($id, $query);
            }
        );

        return response()->json($value);
    }

    /**
     *  Filter or sort tokens
     * @OA\Get(
     *     path="/api/v1/user/{id}/tokens/filtersort",
     *     tags={"Filter"},
     *     description = "
    <h3>Фильтры (GET параметр <i>filter</i>):</h3>
    <ul>
    <li><b>polygon</b> - токены с полигона</li>
    <li><b>kefir</b> - токены с кефира</li>
    <li><b>hidden</b> - скрытые пользователем (доступно только для просмотра своих токенов)</li>
    <li><b>favorite</b> - добавленные в избранное</li>
    <li><b>author</b> - авторства данного пользователя</li>
    <li><b>not-author</b> - пользователь - не автор</li>
    <li><b>on-sale</b> - выставленные на продажу</li>
    <li><b>of-collections</b> - принадлежащие коллекциям (требует параметр `collection` с id
    коллекций через запятую)
    </li>
    <li><b>search</b> - поиск по названию токена (требует параметр `search` со строкой поиска)</li>
    </ul>

    <h3>Сортировка (GET параметр <i>sort</i>, направление (asc/desc) - <i>dir</i>):</h3>
    <ul>
    <li><b>price</b> - по цене</li>
    <li><b>created_at</b> - по дате создания</li>
    <li><b>payment_date</b> - по дате покупки</li>
    </ul>
    ",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="user_id",
     *          example="1",
     *          required=true,
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Фильтры (через запятую)",
     *         example="author",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Параметр сортировки",
     *         example="payment_date",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Parameter(
     *          name="collection",
     *          in="query",
     *          description="ID коллекций для фильтрации по коллекциям (через запятую)",
     *          example="1",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *          ),
     *     ),
     *     @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Строка для поиска по названию токена",
     *          example="qwerty",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *          ),
     *     ),
     *     @OA\Parameter(
     *          name="dir",
     *          in="query",
     *          description="Направление сортировки (по умолчанию - от большего к меньшему)",
     *          example="asc / desc",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *          ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     * )
     */
    function filterSort(int $user_id): JsonResponse
    {
        $authId = Auth::id();
        $filters = collect(explode(',', Input::get('filter', '')))->filter();
        $sortBy = Input::get('sort');

        $filterFn = function (TokenQuery &$q, ?string $filterParam) use ($user_id, $authId): TokenQuery {
            switch ($filterParam) {
                case 'favorite':
                    return $q->favoriteOf($authId);
                case 'author':
                    return $q->ofAuthor($user_id);
                case 'not-author':
                    return $q->ofAuthor($user_id, false);
                case 'on-sale':
                    return $q->onSale();
                case 'of-collections':
                    $ids = explode(',', Input::get('collection', ''));
                    return $q->ofCollections(collect($ids)->filter()->toArray(), true);
                case 'hidden':
                    return $q->when($authId == $user_id, function (TokenQuery $q) {
                        $q->hidden();
                    });
                case 'search':
                    $searchStr = trim(Input::get('search', ''));
                    // do not use search if it's empty
                    return $searchStr ? $q->fullTextSearch($searchStr) : $q;
                default:
                    return $q;
            }
        };
        $sortDir = Input::get('dir') == 'asc' ? 'asc' : 'desc';
        $sortFn = function (TokenQuery &$q, ?string $sortParam) use ($sortDir): TokenQuery {
            switch ($sortParam) {
                case 'price':
                    return $q->orderBy('price', $sortDir);
                case 'created_at':
                    return $q->orderBy('created_at', $sortDir);
                case 'payment_date':
                    return $q->orderedByLastPayment($sortDir);
                default:
                    return $q->orderBy('updated_at', $sortDir);
            }
        };

        $q = Token::query()
            ->ofUser($user_id)
            ->exported(false)
            ->hiddenChecked($user_id)
            ->moderationAccessChecked($user_id)
            ->when($filters->contains(function (string $filter) {
                return in_array($filter, ['polygon', 'kefir']);
            }), function (TokenQuery $q) use ($filters) {
                $q->filterByNets($filters->toArray());
            });

        $sortFn($q, $sortBy);
        foreach ($filters as $filter) {
            $filterFn($q, $filter);
        }

        $responseData = $q->withCount('favorites')
            ->with([
                'author' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
                'user' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
                'collection',
                'galleries',
                'moderation_status',
            ])
            ->paginate(16)
            ->appends(request()->query())
            ->toArray();

        return response()->json($responseData);
    }


    public function filterHidden()
    {

        /**
         *  Filter tokens hidden,
         * @OA\Get(
         *     path="/api/v1/user/tokens/filter/hidden",tags={"Filter"},
         * description = "price - по цене ,created_at - по дате создания, payment_date - по дате оплаты",
         *     @OA\Parameter(
         *         name="query",
         *         in="query",
         *         description="query",
         *         example="price",
         *         required=true,
         *         @OA\Schema(
         *             type="string",
         *         ),
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="401", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */
        $query = Input::get('query');
        $path = Input::get('path');
        $page = Input::get('page');
        $value = Cache::remember('filter_tokens_hidden_hedden' . $query . 'id' . Auth::user()->id . 'path' . $path . 'page' . $page, 360, function () use ($query) {

            return $this->filterByParameter(Auth::id(), $query, "user_id", true);
        });

        return response()->json($value, 200);
    }


    public function filterAuthor($id)
    {

        /**
         *  Filter tokens author,
         * @OA\Get(
         *     path="/api/v1/author/{id}/tokens/filter",tags={"Filter"},
         * description = "price - по цене ,created_at - по дате создания, payment_date - по дате оплаты",
         *     @OA\Parameter(
         *         name="query",
         *         in="query",
         *         description="query",
         *         example="price",
         *         required=true,
         *         @OA\Schema(
         *             type="string",
         *         ),
         *     ),
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         description="author_id",
         *         example="1",
         *         required=true,
         *         @OA\Schema(
         *             type="integer",
         *         ),
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *      @OA\Response(response="401", description="Not Found"),
         *      @OA\Response(response="401", description="Not Autorization"),
         *
         *
         * )
         */
        $query = Input::get('query');
        return Entity::where('name', 'ilike', '%' . $query . '%')->limit(10)->pluck('id');
    }

    private function filterByParameter(int $userId, string $param, string $userColumn = 'user_id', bool $hidden = false)
    {
        if (in_array($param, ['polygon', 'kefir'])) {
            return Token::hidden($userId)
                ->where($userColumn, $userId)
                ->when(
                    $param == 'polygon',
                    function ($q) {
                        return $q->whereNotNull('external_address');
                    }
                )
                ->when(
                    $param == 'kefir',
                    function ($q) {
                        $q->whereNull('external_address');
                    }
                )
                ->with([
                    'author' => function ($q) {
                        $q->with(['legal', 'avatar']);
                    },
                    'user' => function ($q) {
                        $q->with(['legal', 'avatar']);
                    },
                    'collection',
                    'galleries',
                    'moderation_status',
                    'blockchaintoken.blockchain.logo',
                ])
                ->withCount('favorites')
                ->orderBy('created_at')
                ->paginate(16)
                ->appends(['query' => Input::get('query')])
                ->toArray();
        } elseif (in_array($param, ['price', 'created_at', 'payment_date'])) {
            if (!$hidden) {
                return $param != 'payment_date'
                    ? $this->queryFilterDate($userId, $param, $userColumn)
                    : $this->queryFilterPayment($userId);
            } else {
                return $param != 'payment_date'
                    ? $this->queryFilterDateHidden($userId, $param, $userColumn)
                    : $this->queryFilterPaymentHidden($userId, $userColumn);
            }
        }

        return response()->json([['error' => 'Not Found']], 404);
    }

    private function queryFilterDate(int $userId, string $param, string $userColumn): array
    {
        return Token::hidden($userId)
            ->where($userColumn, $userId)
            ->with([
                'author' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
                'user' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
                'collection',
                'galleries',
                'moderation_status',
                'blockchaintoken.blockchain.logo',
            ])
            ->withCount('favorites')
            ->orderBy($param, $param == 'price' ? 'asc' : 'desc')
            ->paginate(16)
            ->appends(['query' => Input::get('query')])
            ->toArray();
    }

    private function queryFilterPayment($userId): array
    {

        return Token::hidden($userId)
            ->leftJoin('marketplace_payments_', 'marketplace_tokens_tokens.id', '=', 'marketplace_payments_.token_id')
            ->where('marketplace_tokens_tokens.user_id', $userId)
            ->select('marketplace_tokens_tokens.*', DB::raw('MAX(marketplace_payments_.created_at) as max_created_at'))
            ->groupBy('marketplace_tokens_tokens.id')
            ->orderByRaw('max_created_at DESC NULLS LAST')
            ->with([
                'author' => function ($query) {
                    $query->with('legal')->with('avatar');
                },
                'user' => function ($query) {
                    $query->with('legal')->with('avatar');
                },
                'payments' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'collection',
                'galleries',
                'moderation_status',
                'blockchaintoken.blockchain.logo',
            ])
            ->withCount('favorites')
            ->paginate(16)
            ->appends(['query' => Input::get('query')])
            ->toArray();
    }

    //    private function queryFilterPayment(): array
    //    {
    //        /**
    //         * 1. Выбрать платежи (запрос Payment)
    //         * 2. Где user_id == id
    //         * 3. Где тип == покупка или минт
    //         * 4. OrderBy created_at desc
    //         * 5. uniqueby = token_id
    //         * 6. with token  и перенести всё что есть в текущем токене (в том числе withCount)
    //         * 7.paginate 16
    //         * 8. pluck (token)
    //         * 9.aappend (query)
    //         * 10. toArray
    //         */
    //        $id = 521;
    //        $user = 'user_id';
    //        return Token::query()
    //            ->hidden($id)
    //            ->where($user, $id)
    //            ->with([
    //                'author' => function ($query) {
    //                    $query->with('legal')->with('avatar');
    //                },
    //                'user' => function ($query) {
    //                    $query->with('legal')->with('avatar');
    //                },
    //                'payments' => function ($query) {
    //                    $query->orderBy('created_at', 'desc');
    //                },
    //                'collection',
    //                'galleries',
    //                'moderation_status',
    //                'blockchaintoken.blockchain.logo',
    //            ])
    //            ->withCount('favorites')
    //            ->paginate(16)
    //            ->appends(['query' => Input::get('query')])
    //            ->toArray();
    //    }


    private function queryFilterDateHidden(int $userId, string $param, string $userColumn): array
    {
        return Token::hidden($userId)
            ->where($userColumn, Auth::id())
            ->with([
                'author' => function ($query) {
                    $query->with(['legal', 'avatar']);
                },
                'user' => function ($query) {
                    $query->with(['legal', 'avatar']);
                },
                'collection',
                'galleries',
                'moderation_status',
                'blockchaintoken.blockchain.logo',
            ])
            ->withCount('favorites')
            ->orderBy($param, $param == 'price' ? 'asc' : 'desc')
            ->paginate(16)
            ->appends(['query' => Input::get('query')])
            ->toArray();
    }

    private function queryFilterPaymentHidden($userId, $user): array
    {
        return Token::hidden($userId)
            ->where($user, Auth::id())
            ->with([
                'author' => function ($query) {
                    $query->with(['avatar', 'legal']);
                },
                'user' => function ($query) {
                    $query->with('legal')->with('avatar');
                },
                'payments' => function ($query) {
                    $query->orderBy('created_at');
                },
                'collection',
                'galleries',
                'moderation_status',
                'blockchaintoken.blockchain.logo',
            ])
            ->withCount('favorites')
            ->paginate(16)
            ->appends(['query' => Input::get('query')])
            ->toArray();
    }

    private function AddedTokenGalleries($id)
    {
        if (Auth::check()) {

            $tokenGallery = DB::table('marketplace_galleries_gallery_tokens')->where('token_id', $id)->pluck('gallery_id')->toArray();
            $gallery = Gallery::where('user_id', Auth::user()->id)->get()->map(function ($value, $key) use ($tokenGallery) {
                if (in_array($value['id'], $tokenGallery)) {
                    $value['added'] = true;
                } else {
                    $value['added'] = false;
                }


                return $value;
            })->toArray();

            usort($gallery, function ($a, $b) {
                return ($b['added'] - $a['added']);
            });

            return response()->json($gallery);
        }
    }

    /**
     *  Global search,
     * @OA\Get(
     *     path="/api/v1/search",tags={"Global Search"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="query",
     *         example="test",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="limit",
     *         required=false,
     *         default=10,
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="page",
     *         required=false,
     *         default=1,
     *         @OA\Schema(
     *             type="integer",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *      @OA\Response(response="401", description="Not Found"),
     *      @OA\Response(response="401", description="Not Autorization"),
     * )
     */
    function globalSearch(): JsonResponse
    {
        $query = trim(Input::get('query'));
        $limitForNonTokens = ((int)Input::get('limit')) ?: 10;
        $page = Input::get('page');

        if (!$query) {
            return response()->json();
        }

        $cacheKeys = [
            'global_search' => $query,
            'limit' => $limitForNonTokens,
            'page' => $page,
        ];
        $value = $this->cache(3, $cacheKeys, function () use ($query, $limitForNonTokens) {
            try {
                if (preg_match('/^0x[a-fA-F0-9]{40}(?::\d{1,3})?$/', $query)) {
                    if (strpos($query, ':') !== false) {
                        $externalId = Str::after($query, ':');
                        $externalAddress = Str::before($query, ':');
                        $externalId = (int)$externalId;
                    } else {
                        $externalId = null;
                        $externalAddress = $query;
                    }
                    $tokens = Token::query()
                        ->whereRaw('LOWER(external_address) = ?', [strtolower($externalAddress)])
                        ->when($externalId !== null, function ($q) use ($externalId) {
                            return $q->whereRaw('external_id = ?', [$externalId]);
                        })
                        ->moderated()
                        ->hidden(false)
                        ->orderBy('external_id')
                        ->paginate(12)
                        ->appends(['query' => Input::get('query')])
                        ->toArray();

                    $collections = Collection::query()
                        ->whereRaw('LOWER(contract_address) = ?', [strtolower($externalAddress)])
                        ->orderBy('contract_address')
                        ->limit($limitForNonTokens)
                        ->get()
                        ->toArray();

                    $userId = BlockchainAccount::where('address', strtolower($query))->pluck('user_id')->toArray();

                    $users = $userId ? User::query()
                        ->where('id', $userId)
                        ->activated()
                        ->with([
                            'legal',
                            'avatar',
                            'verification.verification_type.icon',
                        ])
                        ->orderByRAW('users.id DESC')
                        ->limit($limitForNonTokens)
                        ->get()
                        ->toArray() : [];
                } else {
                    $query = preg_replace('/[^\p{L}\p{N} ]/u', '', $query);
                    $tokens = Token::query()
                        ->fullTextSearch($query)
                        ->representative()
                        ->paginate(12)
                        ->appends(['query' => Input::get('query')])
                        ->toArray();

                    $collections = Collection::query()
                        ->fullTextSearch($query)
                        ->moderated()
                        ->with([
                            'preview',
                            'background',
                            'user' => function ($query) {
                                $query->with([
                                    'legal',
                                    'avatar',
                                    'verification.verification_type.icon',
                                ]);
                            },
                        ])
                        ->orderByRaw('marketplace_collections_collections.id DESC')
                        ->limit($limitForNonTokens)
                        ->get()
                        ->toArray();

                    $individuals = User::query()
                        ->fullTextSearchIndividual($query)
                        ->with(['avatar', 'legal'])
                        ->orderByRAW('users.id DESC')
                        ->limit($limitForNonTokens)
                        ->get()
                        ->toArray();

                    $entities = User::query()
                        ->fullTextSearchEntity($query)
                        // do not allow limit < 0
                        ->with(['avatar', 'legal'])
                        ->orderByRaw('users.id DESC')
                        ->limit(max(($limitForNonTokens - count($individuals)), 0))
                        ->get()
                        ->toArray();

                    $users = array_merge($individuals, $entities);
                }
                return [
                    'tokens' => $tokens,
                    'collections' => $collections,
                    'users' => $users,
                ];
            } catch (Exception $ex) {
                return Response::make($ex->getMessage(), 404);
            }
        });

        return response()->json($value);
    }

    /**
     * @param $token_id
     * @return array{
     *     IpfsHash: string,
     *     PinSize: int,
     *     Timestamp: string,
     *     isDuplicate: bool,
     *     contract_address: string
     * }|false
     */
    function tokenMint($token_id)
    {
        /** @var Token|null $token */
        $token = Token::query()->find($token_id);

        if ($token && !$token->blockchaintoken && $token->isModerated()) {
            /** @var Collection|null $collection */
            $collection = Collection::query()->find($token->collection_id);

            Cache::put('minting' . $token->id, true, 1500);

            $metaDataIpfs = $this->metaDataJson($token, $collection);

            Session::forget('commission_' . Input::get('token_id'));

            return $metaDataIpfs;
        }

        return false;
    }

    public function commission()
    {
        /**
         * Стоимость выгрузки токена в блокчейн
         * @OA\Post(
         *     path="/api/v1/token/mint/commission",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *             mediaType="application/json",
         *             @OA\Schema(required={"token_id"},
         *
         *              @OA\Property(property="token_id",type="integer"),
         *
         *              )
         *         ),
         *
         *     ),
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */
        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => 'required|integer',

            ]
        );

        if ($validator->fails()) {

            return response()->json($validator->messages(), 422);
        }
        $token = Token::where('user_id', Auth::user()->id)->where('id', Input::get('token_id'))->first();

        if ($token && !$token->blockchaintoken && $token->moderation_status_id == 3) {
            $collection = Collection::where('id', $token['collection_id'])->first();
            if (!$collection['blockchain_id']) {
                $commission = env('COMMISSIAETH');

                Session::put('commission_' . Input::get('token_id'), $commission);

                return response()->json(['amount' => $commission]);
            }
            return response()->json(["message" => ['Вы уже выгружали токен из этой коллекции']], 200);
        }
        return response()->json(["Error" => ['Вы не можете выгрузить этот токен!']], 403);
    }

    /**
     * @param $token
     * @param $collection
     * @return array{
     *     IpfsHash: string,
     *     PinSize: int,
     *     Timestamp: string,
     *     isDuplicate: bool,
     *     contract_address: string
     * }
     * @throws GuzzleException
     */
    private function metaDataJson($token, $collection): array
    {
        /** @var Token $t */
        $t = Token::query()->findOrFail($token['id']);
        $client = new Client;
        $data = [
            "pinataMetadata" => [
                "name" => "metadata.json",
            ],
            "pinataContent" => [
                "name" => $t->name,
                "description" => $t->description,
                "image" => 'ipfs://' . $t->file,
                "external_url" => $t->external_reference
                    ? $t->external_reference
                    : url("/collection/token/$t->id"),
            ],
        ];

        $this->logRequests(
            'TokenController@metaDataJson api.pinata.cloud/pinning/pinJSONToIPFS request',
            $data,
            RequestsService::CHANNEL_BLOCKCHAIN
        );

        $response = $client->post('https://api.pinata.cloud/pinning/pinJSONToIPFS', [
            'http_errors' => false,
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'pinata_api_key' => env('PINATA_API_KEY'),
                'pinata_secret_api_key' => env('PINATA_API_SECRET_KEY'),
            ],
        ]);
        $value = json_decode($response->getBody()->getContents(), true);

        $this->logRequests(
            'TokenController@metaDataJson api.pinata.cloud/pinning/pinJSONToIPFS response',
            $value,
            RequestsService::CHANNEL_BLOCKCHAIN
        );

        $value['contract_address'] = $collection->contract_address;
        return $value;
    }

    /**
     * Отмена выгрузки токена
     * @OA\Post(
     *     path="/api/v1/token/blockchain/reset",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(required={"token_id"},
     *                 @OA\Property(property="token_id",type="integer"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Invalide email or password"),
     * )
     */
    function tokenBlockchainReset(): JsonResponse
    {
        $data = request()->post();
        $validator = Validator::make($data, [
            'token_id' => 'required|integer|exists:marketplace_tokens_tokens,id',
        ]);

        if ($validator->fails()) {
            $this->logRequests('/api/v1/token/blockhain/reset bad request', $data, RequestsService::CHANNEL_BLOCKCHAIN);
            return response()->json($validator->messages());
        } else {
            $this->logRequests('/api/v1/token/blockhain/reset request', $data, RequestsService::CHANNEL_BLOCKCHAIN);
        }

        $token = Token::query()->withoutGlobalScopes()->find((int)$data['token_id']);
        $token->update(['pending_at' => null]);

        /** @var Transaction $transaction */
        $transaction = Transaction::query()
            ->where('transaction_types_id', TransactionTypes::TYPE_UPLOADED_TO_BLOCKCHAIN_ID)
            ->where('token_id', $token->id)
            ->where('from_user_id', $token->user_id)
            ->latest()
            ->first();

        return response()->json(true);
    }

    /**
     * Сохранение выгрузки
     * @OA\Post(
     *     path="/api/v1/token/blockchain",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(required={"token_id","address", "token_blockchain_id"},
     *                 @OA\Property(property="token_id",type="integer"),
     *                 @OA\Property(property="address",type="string"),
     *                 @OA\Property(property="token_blockchain_id",type="string"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Invalide email or password"),
     * )
     */
    function tokenBlockchain(PushWebsocketService $pushWebsocketService): JsonResponse
    {
        $data = request()->all();

        $validator = Validator::make($data, [
            'transaction_hash' => 'required|string',
            'token_id' => 'required|integer|exists:marketplace_tokens_tokens,id',
            'address' => 'required|string',
            'token_blockchain_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->logRequests(
                '/api/v1/token/blockhain bad request data',
                request()->all(),
                RequestsService::CHANNEL_BLOCKCHAIN
            );
            return response()->json($validator->messages(), 422);
        } else {
            $this->logRequests(
                '/api/v1/token/blockhain request data',
                request()->all(),
                RequestsService::CHANNEL_BLOCKCHAIN
            );
        }

        /** @var Token $token */
        $token = Token::with(['blockchaintoken'])->findOrFail((int)$data['token_id']);

        $token->update([
            'external_id' => $data['token_blockchain_id'],
            'external_address' => $data['address'],
            'pending_at' => null,
        ]);

        if (!$token->blockchaintoken && $token->isModerated()) { // todo check blockchaintoken here (should be replaced with smth later)
            /** @var Transaction $transaction */
            $transaction = Transaction::create([
                'from_user_id' => $token->user_id,
                'token_id' => $token->id,
                'transaction_types_id' => TransactionTypes::TYPE_UPLOADED_TO_BLOCKCHAIN_ID,
                'price' => $token->price,
                'transaction_hash' => $data['transaction_hash'] ?: null,
                'status' => Transaction::STATUS_SUCCESS,
            ]);
            $pushWebsocketService->pushToken($token);
            Log::info("токен выгружен");
            return response()->json($token);
        }

        return response()->json(["Error" => ['Вы не можете выгрузить этот токен!']], 403);
    }

    public function modearated()
    {

        // $tokens = User::get();
        // foreach($tokens as $token){
        //     $token->fiat->wallet_uuid = CarbonCarbon::now();
        //     $token->save();

        // }
        $token = Token::get();
        foreach ($token as $t) {
            $t->secret_key = Str::random(10);
            $t->save();
        }
    }

    public function addVerificationInToken()
    {
        /**
         * Добавить тексты верификации в токен
         * @OA\Post(
         *     path="/api/v1/token/verification/add",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *              mediaType="application/json",
         *             @OA\Schema(
         *
         *              @OA\Property(description="token_id",property="token_id",type="integer"),
         *              @OA\Property(
         *                  property="texts",
         *                  type="array"  ,
         *                  @OA\Items(
         *                      type="object",
         *                  ),
         *
         *           )
         * )
         *         ),
         *
         *     ),
         *
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */

        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => 'required|integer|exists:marketplace_tokens_tokens,id',
                'texts' => 'required|array|min:3',
            ]
        );
        if ($validator->fails()) {

            return response()->json($validator->messages(), 422);
        }
        $token = Token::where('id', Input::get('token_id'))->first();

        if (Auth::user()->id !== $token->author_id) {
            return response()->json(['Error' => ['Вы не являетесь автором токена']], 403);
        }
        if ($token->moderation_status_id !== 3) {
            return response()->json(['Error' => ['Ваш токен не прошел модерацию']], 403);
        }

        $texts = Input::get('texts');
        // if(count($texts) < 3){
        //     return response()->json(['Error' => ['Должно быть не меньше 3 фраз']], 422);
        // }
        $token->token_verifacation()->delete();
        Cache::forget(('token_verification_' . $token->id));
        foreach ($texts as $text) {
            if ($text) {
                TokenVerification::create([
                    'token_id' => $token->id,
                    'text' => $text,
                ]);
            }
        }
        return response()->json($token->token_verifacation);
    }

    public function tokenVerificationCheck()
    {
        /**
         * Верификация токена
         * @OA\Post(
         *     path="/api/v1/token/verification/",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *              mediaType="application/json",
         *             @OA\Schema(
         *
         *              @OA\Property(description="token_id",property="token_id",type="integer"),
         * )
         *         ),
         *
         *     ),
         *
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */

        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => 'required|integer|exists:marketplace_tokens_tokens,id',

            ]
        );
        if ($validator->fails()) {

            return response()->json($validator->messages(), 422);
        }
        $token = Token::where('id', Input::get('token_id'))->first();

        if (Auth::user()->id !== $token->author_id && Auth::user()->id !== $token->user_id) {
            return response()->json(['Error' => ['Вы не являетесь автором или владельцем токена']], 403);
        }
        if ($token->moderation_status_id !== 3) {
            return response()->json(['Error' => ['Ваш токен не прошел модерацию']], 403);
        }
        if ($token->token_verifacation->isEmpty()) {
            return response()->json(['Error' => ['У вас нет фраз']], 403);
        }

        return $this->verificationGenerate($token);
    }

    public function tokenVerificationCheckCacheDelete()
    {
        /**
         * Удалить старую верификацию
         * @OA\Post(
         *     path="/api/v1/token/verification/new/text",
         *     tags={"Token"},
         *     @OA\RequestBody(
         *
         *         required=true,
         *
         *         @OA\MediaType(
         *              mediaType="application/json",
         *             @OA\Schema(
         *
         *              @OA\Property(description="token_id",property="token_id",type="integer"),
         * )
         *         ),
         *
         *     ),
         *
         *     @OA\Response(response="200", description="OK"),
         *     @OA\Response(response="422", description="Invalide email or password"),
         *
         *
         * )
         */

        $validator = Validator::make(
            Input::all(),
            [
                'token_id' => 'required|integer|exists:marketplace_tokens_tokens,id',

            ]
        );
        if ($validator->fails()) {

            return response()->json($validator->messages(), 422);
        }
        $token = Token::where('id', Input::get('token_id'))->first();
        if (Auth::user()->id !== $token->author_id) {
            return response()->json(['Error' => ['Вы не являетесь автором или владельцем токена']], 403);
        }
        if ($token->moderation_status_id !== 3) {
            return response()->json(['Error' => ['Ваш токен не прошел модерацию']], 403);
        }
        if ($token->token_verifacation->isEmpty()) {
            return response()->json(['Error' => ['У вас нет фраз']], 403);
        }

        Cache::forget('token_verification_' . $token->id);
        return $this->verificationGenerate($token);
    }

    public function verificationGenerate($token)
    {
        return Cache::remember('token_verification_' . $token->id, 900, function () use ($token) {
            $text = $token->token_verifacation->random();
            return response()->json($text->text);
        });
    }

    function onlyIsSale(): JsonResponse
    {
        Session::has('token_is_sale')
            ? Session::forget('token_is_sale')
            : Session::put('token_is_sale', true);

        return response()->json([
            'result' => (int)Session::has('token_is_sale'),
        ]);
    }

    /**
     * @param int $token_id
     * @return array{
     *     success: bool,
     *     error: string|null,
     *     token: Token|null
     * }
     */
    protected function validateTokenAvailableForSale(int $token_id): array
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        /** @var Token|null $token */
        $token = Token::query()
            ->ofUser($authUser->id)
            ->where('id', $token_id)
            ->first();
        $returnFn = function (bool $success, ?string $error = null, ?Token $token = null): array {
            return [
                'success' => $success,
                'error' => $error,
                'token' => $token,
            ];
        };

        if (!$token) {
            return $returnFn(false, 'Токен не найден');
        }

        if (!$token->isModerated()) {
            return $returnFn(false, 'Ваш токен не прошел модерацию!');
        }

        if ($token->transactions()->where('status', Transaction::STATUS_PENDING)->exists()) {
            return $returnFn(false, 'У токена есть неоконченные транзакции');
        }

        if ($token->lots->whereNull('closed_at')->isNotEmpty()) {
            return $returnFn(false, 'Токен является активным лотом аукциона');
        }

        if (optional($token->module)->rent_from || optional($token->factory)->is_active) {
            return $returnFn(false, 'Оборудование токена сдано в аренду или находится в активном цехе');
        }
        // Юр лицо

        if ($authUser->isEntity() && !$authUser->legal->inn) {
            return $returnFn(false, 'Заполните профиль юр.лица');
        }

        // Физ лицо

        if ($authUser->isIndividual()) {
            $hasActiveFiat = $authUser->fiat && $authUser->fiat->is_active;
            $hasActiveSbp = $authUser->payment_system_sbp && $authUser->payment_system_sbp->is_active;

            if (!$hasActiveFiat && !$hasActiveSbp) {
                return $returnFn(false, 'У Вас нет ни одной активной платёжной системы');
            }

            if ($hasActiveFiat && !$hasActiveSbp) {
                /** @var PaymentController $paymentController */
                $paymentController = app(PaymentController::class);
                $res = $paymentController->statusWallet();
                if ($res && $res['data'] && $res['data']['status'] !== 'open') {
                    $walletStatus = $res['data']['status'];
                    $walletStatus = Fiat::STATUSES_RU[$walletStatus] ?? $walletStatus;
                    return $returnFn(false, "Ваш кошелёк в статусе \"$walletStatus\"");
                }
            }
        }

        return $returnFn(true, null, $token);
    }

    /**
     * Обновить токен
     *
     * @OA\Post(
     *     path="/api/v1/token/refresh",
     *     tags={"Token"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(required={"token_id"},
     *                 @OA\Property(property="token_id",type="integer"),
     *             )
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Invalide email or password"),
     * )
     */
    function refresh(TokenRefreshRequest $request, TokenService $tokenService, TokenImageService $imageService, IpfsUrlService $ipfsUrlService): JsonResponse
    {
        $response = $tokenService->refreshTokenBlockchain($request->token);
        if (!$response->success()) {
            return response()->json(['error' => 'Ошибка блокчейн сервера при обновлении токена'], 500);
        }

        $urlData = $ipfsUrlService->processUrl($response->data);
        $jsonDataUrl = $urlData ? $urlData['normalizedUrl'] : $response->data;

        $jsonData = file_get_contents($jsonDataUrl);
        $tokenRefreshData = json_decode($jsonData, true);

        $request->token->update([
            'name' => $tokenRefreshData['name'] ?? $request->token->name,
            'description' => $tokenRefreshData['description'] ?? $request->token->description
        ]);

        $imageService->processAndUploadImage($request->token, $tokenRefreshData['image']);

        return response()->json(['token' => $request->token]);
    }
}
