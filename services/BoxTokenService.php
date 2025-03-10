<?php

namespace Marketplace\Tokens\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Marketplace\Categories\Models\Category;
use Marketplace\Collections\Models\Collection;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Module\actions\CreateModuleToTokenAction;
use Marketplace\Module\Models\Module;
use Marketplace\Tokens\Actions\CreateTokenFromTokenToImportAction;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Models\Box;
use Marketplace\Tokens\Models\BoxType;
use Marketplace\Tokens\Models\TokenType;
use Marketplace\TokensToImport\Models\TokenToImport;
use Marketplace\Transactions\Models\Transaction;
use PgSql\Lob;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;
use System\Models\File;

class BoxTokenService
{
    protected $tokenService;
    protected $chainBoxService;
    protected $createTokenFromTokenToImportAction;
    protected $createModuleToTokenAction;

    public function __construct()
    {
        $this->tokenService = app(TokenService::class);
        $this->chainBoxService = app(ChainBoxService::class);
        $this->createTokenFromTokenToImportAction = app(CreateTokenFromTokenToImportAction::class);
        $this->createModuleToTokenAction = app(CreateModuleToTokenAction::class);
    }

    public function createTokenFromTransaction(Transaction $transaction): Token
    {
        try {
            DB::beginTransaction();

            // Find collection 
            $collection = Collection::query()
                ->where('contract_address', env('BOXES_CONTRACT_ADDRESS'))
                ->first();

            // Get token data
            $bcTokenData = $this->chainBoxService->getTokenInfoByHash($transaction->transaction_hash);

            // Prepare token data
            $tokenImgFile = (new File())->fromUrl($bcTokenData['image']);
            $ipfsPath = $this->tokenService->extractIpfsPath($bcTokenData['image']);

            // Create token
            $token = Token::updateOrCreate(
                [
                    'external_id' => $bcTokenData['token_id'],
                    'collection_id' => $collection->id,
                ],
                [
                    'name' => $bcTokenData['token_name'],
                    'description' => $bcTokenData['token_description'],
                    'type' => $tokenImgFile->content_type,
                    'transaction_hash' => $transaction->transaction_hash,

                    'price' => $transaction->price < 100 ? 100 : $transaction->price,
                    'currency' => Transaction::CURRENCY_RUR_TYPE,
                    'user_id' => $transaction->from_user_id,

                    'external_address' =>  $collection->contract_address,
                    'file' => $ipfsPath,
                    'moderation_status_id' => ModerationStatus::MODERATED_ID,

                    'ipfs_json_uri' => $bcTokenData['token_uri'],
                    'ipfs_img_uri' => $bcTokenData['image'],

                    'token_type_id' => TokenType::BOX_TOKEN,
                ]
            );

            $token->upload_file()->add($tokenImgFile);
            $token->preview_upload()->add($tokenImgFile);

            $token->author = $transaction->from_user_id;
            $token->preview = $tokenImgFile->path;
            $token->save();

            // Create box
            $box = new Box();
            $box->save();

            // Link token and box
            $token->tokenable()->associate($box);
            $token->save();

            DB::commit();
            return $token;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateOpenedBox(Box $box, $bcResponse)
    {
        $boxType = $this->getBoxItemType($bcResponse);

        $box->type_code = $boxType;
        $box->opened_at = Carbon::now();
        $box->save();

        return $box;
    }

    public function createBoxItem(Token $token, array $bcTokenData, Transaction $transaction)
    {
        $box = $token->tokenable;

        switch ($box->type_code) {
            case BoxType::KEFIRIUS:
                $collection = Collection::firstOrCreate(
                    [
                        'contract_address' => env('KEFIRIUMKOT_ADDRESS')
                    ],
                    [
                        'name' => 'KEFIRIUS',
                        'description' => 'KEFIRIUS',
                        'category_id' => Category::CATEGORY_ITEM_ID,
                        'user_id' => User::first()->id,
                        'moderation_status_id' => ModerationStatus::MODERATED_ID,
                        'modarated_at' => now()
                    ]
                );
                $boxItemToken = $this->initCollectionToken($transaction, $collection, $bcTokenData);
                break;

            case BoxType::SPOTTY:
                $collection = Collection::firstOrCreate(
                    [
                        'contract_address' => env('SPOTTY_CONTRACT_ADDRESS')
                    ],
                    [
                        'name' => 'SPOTTY',
                        'description' => 'SPOTTY',
                        'category_id' => Category::CATEGORY_ITEM_ID,
                        'user_id' => User::first()->id,
                        'moderation_status_id' => ModerationStatus::MODERATED_ID,
                        'modarated_at' => now()
                    ]
                );
                $boxItemToken = $this->initCollectionToken($transaction, $collection, $bcTokenData);
                break;

            case BoxType::MINIFACTORY1:
                $boxItemToken = $this->initMinifactoryToken($transaction, $bcTokenData);
                break;
            case BoxType::MINIFACTORY2:
                $boxItemToken = $this->initMinifactoryToken($transaction, $bcTokenData);
                break;
            case BoxType::MINIFACTORY3:
                $boxItemToken = $this->initMinifactoryToken($transaction, $bcTokenData);
                break;
            case BoxType::MINIFACTORY4:
                $boxItemToken = $this->initMinifactoryToken($transaction, $bcTokenData);
                break;
            case BoxType::MINIFACTORY5:
                $boxItemToken = $this->initMinifactoryToken($transaction, $bcTokenData);
                break;

            case BoxType::FACTORY_PASS:
                $collection = Collection::firstOrCreate(
                    [
                        'contract_address' => env('FACTORY_PASS_CONTRACT_ADDRESS')
                    ],
                    [
                        'name' => 'FACTORY PASS',
                        'description' => 'FACTORY PASS',
                        'category_id' => Category::CATEGORY_ITEM_ID,
                        'user_id' => User::first()->id,
                        'moderation_status_id' => ModerationStatus::MODERATED_ID,
                        'modarated_at' => now()
                    ]
                );
                $boxItemToken = $this->initWlCardToken($transaction, $collection, $bcTokenData);
                break;

            default:
                return response()->json([
                    'message' => 'Ошибка создания токена для бокса'
                ], 400);
                break;
        }

        // Link box item and box
        $box->boxable()->associate($boxItemToken);
        $box->save();

        return $boxItemToken;
    }

    protected function getBoxItemType($bcResponse): string
    {
        $code = strtolower($bcResponse['details']['openRandom']);

        if ($code == 'minifactory') {
            $typeMf = $bcResponse['details']['typeMF'];
            $code = $code . $typeMf;
        }

        \Log::debug('code', [$code]);

        $bcBoxTypeMap = [
            'kefirius' => BoxType::KEFIRIUS,
            'spotty' => BoxType::SPOTTY,
            'factorypass' => BoxType::FACTORY_PASS,
            'minifactory1' => BoxType::MINIFACTORY1,
            'minifactory2' => BoxType::MINIFACTORY2,
            'minifactory3' => BoxType::MINIFACTORY3,
            'minifactory4' => BoxType::MINIFACTORY4,
            'minifactory5' => BoxType::MINIFACTORY5,
        ];

        $boxType = $bcBoxTypeMap[$code];
        \Log::debug('boxType', [$boxType]);

        return $boxType;
    }

    protected function initCollectionToken(Transaction $transaction, Collection $collection, array $bcTokenData)
    {
        $token = Token::query()
            ->where('external_id', $bcTokenData['token_id'])
            ->where('external_address', $collection->contract_address)
            ->first();

        if ($token) {
            $token->update([
                'user_id' => $transaction->from_user_id,
            ]);
        } else {
            $ipfsUrl = $this->convertToPinata($bcTokenData['image']);

            $image = (new File)->fromUrl($ipfsUrl);

            $ipfsPath = $this->tokenService->extractIpfsPath($bcTokenData['image']);

            $token = Token::create([
                'external_id' => $bcTokenData['token_id'],
                'collection_id' => $collection->id,

                'name' => $bcTokenData['token_name'],
                'description' => $bcTokenData['token_description'],
                'type' => $image->content_type,
                'transaction_hash' => $transaction->transaction_hash,
                'price' => $transaction->price >= 100 ? $transaction->price : 100,
                'currency' => Transaction::CURRENCY_RUR_TYPE,
                'user_id' => $transaction->from_user_id,

                'external_address' => $collection->contract_address,
                'file' => $ipfsPath, // ipfs hash
                'moderation_status_id' => ModerationStatus::MODERATED_ID,

                'ipfs_json_uri' => $bcTokenData['token_uri'],
                'ipfs_img_uri' => $bcTokenData['image'],
            ]);

            $token->upload_file()->add($image);
            $token->preview_upload()->add($image);

            $token->author = $transaction->from_user_id;
            $token->preview = $image->path;
            $token->save();
        }

        \Log::debug(__METHOD__, [
            'token' => $token
        ]);

        return $token;
    }

    protected function initWlCardToken(Transaction $transaction, Collection $collection, array $bcTokenData)
    {
        $image = (new File)->fromUrl($bcTokenData['image']);

        $ipfsPath = $this->tokenService->extractIpfsPath($bcTokenData['image']);

        // Create token
        $token = Token::updateOrCreate(
            [
                'external_id' => $bcTokenData['token_id'],
                'collection_id' => $collection->id,
            ],
            [
                'name' => $bcTokenData['token_name'],
                'description' => $bcTokenData['token_description'],
                'type' => $image->content_type,
                'transaction_hash' => $transaction->transaction_hash,
                'price' => $transaction->price >= 100 ? $transaction->price : 100,
                'currency' => Transaction::CURRENCY_RUR_TYPE,
                'user_id' => $transaction->from_user_id,

                'external_address' => $collection->contract_address,
                'file' => $ipfsPath, // ipfs hash
                'moderation_status_id' => ModerationStatus::MODERATED_ID,

                'ipfs_json_uri' => $bcTokenData['token_uri'],
                'ipfs_img_uri' => $bcTokenData['image'],

                'token_type_id' => TokenType::FACTORY_PASS_TOKEN,
            ]
        );

        $token->upload_file()->add($image);
        $token->preview_upload()->add($image);

        $token->author = $transaction->from_user_id;
        $token->preview = $image->path;
        $token->save();

        \Log::debug('Created token', [$token]);

        return $token;
    }

    protected function initMinifactoryToken(Transaction $transaction, array $bcTokenData)
    {
        $urlParts = null;
        $collectionId = intval(env('DROP_MINI_SHOP_COLLECTION'));
        $tokenPrice = 100;

        $collection = Collection::findOrFail($collectionId);

        // Create TokenToImport
        $tokenToImportData = [
            'external_id' => $bcTokenData['token_id'],
            'external_address' => $collection->contract_address,
            'symbol' => env('SYMBOL_MINIFACTORY'),
            'mime' => null,
            'ipfs_url' => $urlParts,
            'name' => $bcTokenData['token_name'],
            'description' => $bcTokenData['token_description'],
            'preview_url' => $bcTokenData['image'],
            'page' => 1,
        ];

        $tokenToImport = TokenToImport::make($tokenToImportData);
        $tokenToImport->user_id = $transaction->from_user_id;
        $tokenToImport->id = $bcTokenData['token_id'];

        // Create Token
        $token = $this->createTokenFromTokenToImportAction->execute(
            $tokenToImport,
            $collectionId,
            $transaction->transaction_hash,
            $tokenPrice,
            false,
            true,
            $transaction->currency
        );

        if (!$token) {
            return response([
                'message' => 'Ошибка создания модуля миницеха'
            ], 400);
        }

        $module = $this->createModuleToTokenAction->execute(
            $token->id,
            $bcTokenData['attributes']['module'] ?? $bcTokenData['attributes']['typeId']
        );

        return $token;
    }

    protected function convertToPinata(string $url): string
    {
        if (strpos($url, 'ipfs://') === 0) {
            $parsedUrl = substr($url, 7);

            $pinataPrefix = "https://gateway.pinata.cloud/ipfs/";

            $url = $pinataPrefix . ltrim($parsedUrl, '/');
        }

        return $url;
    }
}
