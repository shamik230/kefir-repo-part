<?php

namespace Marketplace\Tokens\Controllers\Api;

use Marketplace\BoxDrop\Models\BoxDrop;
use Marketplace\BoxDrop\Models\BoxDropSettings;
use Marketplace\BoxDrop\Resources\BoxDropResource;
use Marketplace\KefiriumCashback\Models\KefiriumCashback;
use Marketplace\Tokens\Requests\Box\BoxMintRequest;
use Marketplace\Tokens\Requests\Box\BoxOpenRequest;
use Marketplace\Tokens\Requests\Box\BoxWhitelistRequest;
use Marketplace\Tokens\Services\BoxTokenService;
use Marketplace\Tokens\Services\ChainBoxService;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactions\Services\TransactionService;
use Marketplace\Transactiontypes\Models\TransactionTypes;

/**
 * BoxToken API Controller
 */
class BoxTokenController
{
    protected $chainBoxService;
    protected $transactionService;
    protected $boxTokenService;

    public function __construct()
    {
        $this->chainBoxService = app(ChainBoxService::class);
        $this->transactionService = app(TransactionService::class);
        $this->boxTokenService = app(BoxTokenService::class);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/boxes/mint",
     *     tags={"Boxes"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="address",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="stage",
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function mintBox(BoxMintRequest $request)
    {
        $validatedData = $request->all();

        $address = $validatedData['address'];
        $stage = $validatedData['stage'];
        $user = $validatedData['user'];
        $mintBoxPrice = $validatedData['mint_box_price'];

        if ($stage == 'public') {
            // Create kefirium payment
            $kefiriumCashback = KefiriumCashback::create([
                'type' => KefiriumCashback::TYPE_MINT_BOX,
                'amount' => -$mintBoxPrice,
                'user_id' => $user->id
            ]);
        }

        // Create transaction
        $transaction = Transaction::create([
            'from_user_id' => $user->id,
            'transaction_types_id' => TransactionTypes::TYPE_MINT_BOX,
            'status' => Transaction::STATUS_PENDING,
            'price' => $mintBoxPrice,
            'currency' => Transaction::CURRENCY_KEFIRIUM_TYPE,
        ]);

        try {
            // Send request to mint
            $bcResponseData = $this->chainBoxService->mintBox($address, $stage);

            // Check response status
            $status = $this->transactionService->checkResponseStatus($bcResponseData);

            // Update transaction
            $transaction->status = $status;
            if (isset($bcResponseData['hash'])) {
                $transaction->transaction_hash = $bcResponseData['hash'];
            }
            $transaction->save();
            \Log::debug('Updated transaction', [$transaction]);

            if ($transaction->status == Transaction::STATUS_SUCCESS) {
                return response()->json([
                    'message' => 'Успешный минт бокса.',
                    'transaction' => $transaction
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Минт бокса в обработке. Подробности на странице профиля во вкладке "Активность"',
                    'transaction' => $transaction,
                ], 202);
            }
        } catch (\Exception $e) {
            \Log::error("Error minting box", [$e]);

            // Update transaction
            $transaction->update([
                'status' => Transaction::STATUS_ERROR
            ]);

            // Remove kefirium payment for refund
            if (isset($kefiriumCashback)) {
                $kefiriumCashback->delete();
            }

            $errorMessage = 'Ошибка минта бокса.';
            if (preg_match('/Max Supply for address/', $e->getMessage())) {
                $errorMessage = "Достигнут лимит боксов {$stage} для адреса {$address}.";
            }

            return response()->json([
                'message' => $errorMessage,
                'transaction' => $transaction
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/boxes/open",
     *     tags={"Boxes"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="address",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="token_id",
     *                 type="integer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="token",
     *                 type="object"
     *             )
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function openBox(BoxOpenRequest $request)
    {
        $validatedData = $request->all();

        $user = $validatedData['user'];
        $boxToken = $validatedData['token'];
        $address = $validatedData['address'];

        // Create transaction
        $transaction = Transaction::create([
            'token_id' => $boxToken->id,
            'from_user_id' => $user->id,
            'transaction_types_id' => TransactionTypes::TYPE_OPEN_BOX,
            'status' => Transaction::STATUS_PENDING,
            'currency' => Transaction::CURRENCY_KEFIRIUM_TYPE,
        ]);

        try {
            // Send request to open
            $bcResponseData = $this->chainBoxService->openBox($boxToken->external_id, $address);

            // Check response status
            $status = $this->transactionService->checkResponseStatus($bcResponseData);

            // Update transaction
            $transaction->status = $status;
            if (isset($bcResponseData['hash'])) {
                $transaction->transaction_hash = $bcResponseData['hash'];
            }
            $transaction->save();
            \Log::debug('Updated transaction', [$transaction]);

            // Update opened box
            $this->boxTokenService->updateOpenedBox($boxToken->tokenable, $bcResponseData);

            // Create transaction (get item)
            $transaction = Transaction::create([
                'token_id' => $boxToken->id,
                'from_user_id' => $user->id,
                'transaction_types_id' => TransactionTypes::TYPE_GET_BOX_ITEM,
                'status' => Transaction::STATUS_PENDING,
                'currency' => Transaction::CURRENCY_KEFIRIUM_TYPE,
            ]);

            return response()->json([
                'message' => 'Успешное открытие бокса.',
                'token' => $boxToken
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error opening box", [$e]);

            // Update transaction
            $transaction->update([
                'status' => Transaction::STATUS_ERROR
            ]);

            return response()->json([
                'message' => 'Ошибка при открытие бокса.',
                'transaction' => $transaction
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/boxes/whitelisted",
     *     tags={"Boxes"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="address",
     *                 type="string",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="boolean",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *     )
     * )
     */

    public function checkWhiteList(BoxWhitelistRequest $request)
    {
        $validatedData = $request->validated();

        $address = strtolower($validatedData['address']);

        $isWhitelisted = $this->chainBoxService->isWhitelisted($address);

        $message = $isWhitelisted
            ? "Адрес {$address} находится в белом списке."
            : "Адрес {$address} не находится в белом списке.";

        return response()->json([
            'message' => $message,
            'status' => (bool) $isWhitelisted
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/boxes/info",
     *     tags={"Boxes"},
     *     @OA\Response(
     *         response=200,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function getInfo()
    {
        $mintBoxPrice = floatval(BoxDropSettings::get('box_price_kefirium'));
        $totalBoxesCount = intval(BoxDropSettings::get('total_boxes_count'));

        $data = $this->chainBoxService->getContractInfo();

        $mintedBoxesCount = intval($data['totalSupply']);

        $availableBoxes = $totalBoxesCount - $mintedBoxesCount;

        $boxDrops = BoxDrop::query()
            ->with(['status'])
            ->get();

        return response()->json([
            'stages' => BoxDropResource::collection($boxDrops),
            'price' => $mintBoxPrice,
            'available_count' => $availableBoxes,
            'minted_count' => $mintedBoxesCount,
            'total_count' => $totalBoxesCount,
            'contract_info' => $data
        ], 200);
    }
}
