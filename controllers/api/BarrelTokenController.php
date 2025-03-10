<?php

namespace Marketplace\Tokens\Controllers\Api;

use Illuminate\Http\Request;
use RainLab\User\Facades\Auth;
use Marketplace\BlockchainAccount\Models\BlockchainAccount;
use Marketplace\Collections\Models\Collection;
use Marketplace\Barrel\Models\Barrel;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Tokens\Jobs\BurnBarrelTokenJob;
use Marketplace\Tokens\Jobs\MintBarrelTokenJob;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Models\TokenType;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use Marketplace\KefiriumCashback\Actions\CreateCashbackFromTransactionCreateBarrelTokenAction;
use Marketplace\KefiriumCashback\Models\KefiriumCashback;

class BarrelTokenController
{
    /**
     * @OA\Post(
     *     path="/api/v1/barrels/mint",
     *     summary="Mint a barrel token",
     *     description="Mint a new barrel token by specifying a kefir barrel ID and a blockchain account.",
     *     operationId="mintBarrelToken",
     *     tags={"Barrel"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Kefir barrel ID and blockchain account details",
     *         @OA\JsonContent(
     *             required={"barrel_id","blockchain_account"},
     *             @OA\Property(property="barrel_id", type="integer", example=1),
     *             @OA\Property(property="blockchain_account", type="string", example="0x0E0E72CbF7f407314D32Fa2eC3C9D737dD703e3a")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully minted the barrel token",
     *         @OA\JsonContent(
     *             @OA\Property(property="moderation_status_id", type="integer", example=3),
     *             @OA\Property(property="is_sale", type="boolean", example=false),
     *             @OA\Property(property="name", type="string", example="Малая бочка"),
     *             @OA\Property(property="description", type="string", example="<p>Свойства: содержит в себе 500 литров кефира, для минта требует (сжигает) 200 литров кефира.</p>"),
     *             @OA\Property(property="type", type="string", example="image/png"),
     *             @OA\Property(property="barrel_volume", type="string", example="500"),
     *             @OA\Property(property="royalty", type="integer", example=null),
     *             @OA\Property(property="price", type="integer", example=100),
     *             @OA\Property(property="hidden", type="boolean", example=null),
     *             @OA\Property(property="external_reference", type="string", example=null),
     *             @OA\Property(property="file", type="string", example="http://localhost:8080/storage/app/uploads/public/666/7d2/4c7/6667d24c74f8f968705268.png"),
     *             @OA\Property(property="token_type_id", type="integer", example=1),
     *             @OA\Property(property="collection_id", type="integer", example=2163),
     *             @OA\Property(property="user_id", type="integer", example=1696),
     *             @OA\Property(property="author_id", type="integer", example=1696),
     *             @OA\Property(property="updated_at", type="string", example="2024-06-11 09:42:52"),
     *             @OA\Property(property="created_at", type="string", example="2024-06-11 09:42:52"),
     *             @OA\Property(property="id", type="integer", example=8174),
     *             @OA\Property(property="is_favorited", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */
    function mintBarrelToken(Request $request)
    {
        // Find collection
        $collectionId = env('BARRELS_COLLECTION_ID') ?? 0;
        $collection = Collection::find($collectionId);
        if (!$collection) {
            return response()->json(['message' => 'Не найдена коллекция "Бочки кефира"'], 400);
        }

        // Find kefir barrel type
        $barrel = Barrel::find($request->barrel_id);
        if (!$barrel) {
            return response()->json(['message' => "Не найден тип бочки для id = $request->barrel_id"], 400);
        }
        $creationPrice = $barrel->price_kefirium + $barrel->volume;

        // Check user balance
        $user = Auth::user();
        if ($user->kefirium < $creationPrice) {
            return response()->json(['message' => "Недостаточно Кефира для покупки. Цена: $creationPrice л, баланс: $user->kefirium л"], 400);
        }

        // Check blockchain account
        if (!$request->blockchain_account) {
            return response()->json(['message' => "Не указан блокчейн аккаунт"], 400);
        }

        $blockchainAccount = BlockchainAccount::where('user_id', $user->id)
            ->where('address', strtolower($request->blockchain_account))
            ->first();

        if (!$blockchainAccount) {
            return response()->json(['message' => "Блокчейн аккаунт $request->blockchain_account не привязан к текущему пользователю"], 400);
        }

        try {
            $kefiriumCashback = KefiriumCashback::create([
                'type' => KefiriumCashback::TYPE_CREATE_BARREL,
                'user_id' => $user->id,
                'amount' => -$creationPrice,
            ]);

            $transaction = Transaction::create([
                'transaction_types_id' =>  TransactionTypes::TYPE_CREATE_BARREL,
                'status' => Transaction::STATUS_PENDING,
                'currency' => Transaction::CURRENCY_KEFIRIUM_TYPE,
                'from_user_id' => $user->id,
                'price' => $creationPrice,
            ]);

            MintBarrelTokenJob::dispatch($transaction, $blockchainAccount, $barrel, $kefiriumCashback);

            return response()->json(['data' => $transaction, 'message' => 'Транзакция на минт токена принята в обработку. Подробности на странице профиля во вкладке "Активность"'], 202);
        } catch (\Exception $e) {
            \Log::debug('exception', [$e]);
            return response()->json(['message' => 'Неизвестная ошибка'], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/barrels/burn",
     *     summary="Burn a barrel token",
     *     description="Burn a barrel token to release the kefir and complete the transaction.",
     *     operationId="burnBarrelToken",
     *     tags={"Barrel"},
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token_id"},
     *             @OA\Property(property="token_id", type="integer", example=37),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token successfully burned",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=8174),
     *             @OA\Property(property="created_at", type="string", example="2024-06-11 09:42:52"),
     *             @OA\Property(property="updated_at", type="string", example="2024-06-11 09:43:32"),
     *             @OA\Property(property="name", type="string", example="Малая бочка"),
     *             @OA\Property(property="description", type="string", example="<p>Свойства: содержит в себе 500 литров кефира, для минта требует (сжигает) 200 литров кефира.</p>"),
     *             @OA\Property(property="type", type="string", example="image/png"),
     *             @OA\Property(property="barrel_volume", type="string", example="500"),
     *             @OA\Property(property="file", type="string", example="http://localhost:8080/storage/app/uploads/public/666/7d2/4c7/6667d24c74f8f968705268.png"),
     *             @OA\Property(property="external_reference", type="string", example=null),
     *             @OA\Property(property="price", type="string", example="100"),
     *             @OA\Property(property="royalty", type="string", example=null),
     *             @OA\Property(property="hidden", type="boolean", example=false),
     *             @OA\Property(property="is_sale", type="boolean", example=false),
     *             @OA\Property(property="is_hidden", type="boolean", example=false),
     *             @OA\Property(property="moderation_status_id", type="integer", example=3),
     *             @OA\Property(property="transaction_hash", type="string", example="0x505a174bb124e8706f9dc038b4ed53a58b491a9b0601682f1d07df7b5ac37b7a"),
     *             @OA\Property(property="currency", type="string", example="kefirium"),
     *             @OA\Property(property="collection", type="object", {})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */
    function burnBarrelToken(Request $request)
    {
        try {
            $token = Token::find($request->token_id);
            if (!$token) {
                return response()->json(['message' => 'Токен не найден'], 404);
            }

            if ($token->is_sale) {
                return response()->json(['message' => 'Нельзя сжечь бочку так как она выставлена на продажу'], 400);
            }

            // Check blockchain account
            // ...

            $transaction = Transaction::create([
                'transaction_types_id' =>  TransactionTypes::TYPE_BURN_BARREL,
                'status' => Transaction::STATUS_PENDING,
                'currency' => Transaction::CURRENCY_KEFIRIUM_TYPE,
                'transaction_hash' => null,
                'token_id' => $token->id,
                'from_user_id' => $token->user_id,
                'price' => $token->barrel_volume,
            ]);

            BurnBarrelTokenJob::dispatch($transaction, $token);

            return response()->json(
                [
                    'data' => $transaction,
                    'message' => 'Сжигание бочки находится в обработке. Подробнее на странице профиля во вкладке "Активность"'
                ],
                202
            );
        } catch (\Exception $e) {
            \Log::debug('exception', [$e]);
            return response()->json(['message' => 'Ошибка'], 400);
        }
    }
}
