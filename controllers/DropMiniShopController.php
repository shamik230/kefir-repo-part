<?php

namespace Marketplace\Tokens\Controllers;

use App;
use Db;
use Guzzle\Http\Message\Request;
use Illuminate\Http\JsonResponse;
use Input;
use Log;
use Marketplace\Payments\Controllers\PaymentController;
use Marketplace\Payments\Models\Payment;
use Marketplace\Payments\Services\BlockchainService;
use Marketplace\Payments\Services\PaymentService;
use Marketplace\Tokens\Models\DropMiniShop;
use Marketplace\Tokens\Models\DropMiniShopSettings;
use Marketplace\Tokens\Requests\DropMiniShopEnclosedBuyRequest;
use Marketplace\Tokens\Requests\DropMiniShopFreeDropRequest;
use Marketplace\Tokens\Requests\DropMiniShopIndexRequest;
use Marketplace\Tokens\Requests\DropMiniShopOpenBuyRequest;
use Marketplace\Tokens\Requests\DropMiniShopSheetWhiteListRequest;
use Marketplace\Tokens\Services\CreateMintDropMiniShopService;
use Marketplace\Tokens\Services\DropMiniShopService;
use Marketplace\Traits\KefiriumBot;
use Marketplace\Traits\SendEmail;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;
use Exception;

class DropMiniShopController
{
    use SendEmail, KefiriumBot;

    /** @var BlockchainService */
    protected $bchService;
    protected $freePay;

    /** @var DropMiniShopService */
    protected $service;

    /** @var CreateMintDropMiniShopService */
    protected $createMintDropMiniShop;

    /** @var PaymentService */
    protected $paymentService;

    /** @var PaymentController */
    protected $paymentController;

    function __construct(BlockchainService $bchService)
    {
        $this->service = app(DropMiniShopService::class);
        $this->bchService = $bchService;
        $this->freePay = !App::environment(['prod', 'production']);
        $this->paymentController = app(PaymentController::class);
        $this->paymentService = app(PaymentService::class);
        $this->createMintDropMiniShop = new CreateMintDropMiniShopService();
    }

    /**
     * Получение инфы по дропу МиниЦеха
     *
     * @OA\Get(
     *     path="/api/v1/drop_mini_shop",tags={"DropMiniShop"},
     *     @OA\Parameter(
     *         name="wallet_address",
     *         in="query",
     *         description="Адрес ММ кошелька пользователя",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *          @OA\Parameter(
     *          name="user_id",
     *          in="query",
     *          description="id пользователя",
     *          required=false,
     *          @OA\Schema(
     *              type="int",
     *          ),
     *      ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Validation Exception"),
     *     @OA\Response(response="401", description="Unauthorized"),
     * )
     */
    function index(DropMiniShopIndexRequest $request): array
    {
        $user = User::find($request->user_id);
        return DropMiniShopSettings::getSettings($user ?? null, strtolower($request->wallet_address));
    }

    /**
     * Обнуление ограничений у кошелька минидропа
     *
     * @OA\Get(
     *     path="/api/v1/drop_mini_shop/resetWalletRestrictions",tags={"DropMiniShop"},
     *     @OA\Parameter(
     *         name="wallet_address",
     *         in="query",
     *         description="Адрес ММ кошелька пользователя",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Validation Exception"),
     *     @OA\Response(response="401", description="Unauthorized"),
     * )
     */
    function resetWalletRestrictions(): JsonResponse
    {
        if ($this->freePay) {
            DropMiniShop::query()
                ->where('wallet_address', strtolower(Input::get('wallet_address')))
                ->update([
                    'processing' => false,
                    'tokens_available' => DropMiniShop::ENCLOSED_SALE_MAX_TOKENS,
                    'free_tokens_available' => DropMiniShop::FREE_DROP_MAX_TOKENS
                ]);
            return response()->json(true);
        } else {
            return response()->json(false);
        }
    }

    /**
     * Получение списка вайт листов по дропам
     *
     * @OA\Get(
     *     path="/api/v1/drop_mini_shop/sheet_white_list",tags={"DropMiniShop"},
     *     @OA\Parameter(
     *         name="wallet_address",
     *         in="query",
     *         description="Адрес ММ кошелька пользователя",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         ),
     *     ),
     *     @OA\Response(response="200", description="OK"),
     *     @OA\Response(response="422", description="Validation Exception"),
     *     @OA\Response(response="401", description="Unauthorized"),
     * )
     */
    function sheetWhiteList(
        DropMiniShopSheetWhiteListRequest $request,
        DropMiniShopService               $dropMiniShopService
    ): JsonResponse {
        return response()->json($dropMiniShopService->dropWhitelist(strtolower($request->wallet_address)));
    }

    /**
     * Получение токена с бесплатного дропа
     *
     * @OA\Post(
     *       path="/api/v1/drop_mini_shop/free_drop",
     *       tags={"DropMiniShop"},
     *       @OA\RequestBody(
     *           required=true,
     *           @OA\MediaType(
     *               mediaType="application/json",
     *               @OA\Schema(required={"wallet_address"},
     *               @OA\Property(property="wallet_address",description="Адрес БЧ кошелька пользователя",type="string"),
     *               )
     *           )
     *       ),
     *       @OA\Response(response="200", description="OK"),
     *       @OA\Response(response="403", description="Not Authorized"),
     *       @OA\Response(response="422", description="Validation Exception")
     *   )
     */
    function freeDrop(DropMiniShopFreeDropRequest $request): JsonResponse
    {
        $response = $this->service->dropMintRequest($request->wallet_address, $request->type);

        if ($response['status'] == 'error') {
            return response()->json(['status' => 'error', 'message' => $response['message']], 422);
        }
        $freeMintPayment = Payment::query()->createMint(
            Auth::id(),
            0,
            Payment::TYPE_MINT,
            json_encode($response['data']['data']),
            json_encode(['nftBuyer' => $request->wallet_address, 'stage' => $request->type])
        );

        $mintTransaction = Transaction::query()->createMint(
            $this->service->getUserIdCollectionDropMiniShop(),
            0.0,
            ($response['status'] == 'success') ? Transaction::STATUS_SUCCESS : Transaction::STATUS_PENDING,
            $response['data']['data']['hash'],
            $freeMintPayment->id,
            null,
            null,
            Transaction::CURRENCY_RUR_TYPE,
            Auth::id()
        );
        $this->service->setProcessingDrop($request->wallet_address);
        if ($response['status'] == 'success') {
            DB::beginTransaction();
            try {
                $this->service->updateConstraints(DropMiniShopSettings::FREE_DROP_KEY, $request->wallet_address);
                $executeMint = $this->createMintDropMiniShop
                    ->execute($response['data']['data']['details']['tokens'][0], $mintTransaction, $freeMintPayment);
                if ($executeMint['status'] == 'error') {
                    return response()->json(['status' => $executeMint['status'], 'message' => $executeMint['message']], 500);
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $this->sendErrorMint($freeMintPayment, 'Ошибка во время минта @freeDrop.' . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'Ошибка во время минта токена'], 500);
            } finally {
                $this->service->setProcessingDrop($request->wallet_address, false);
            }
        }
        return response()->json([
            'status' => 'success',
            'message' => $response['message'],
        ]);
    }

    /**
     * Покупка токена в закрытой распродаже
     *
     * @OA\Post(
     *       path="/api/v1/drop_mini_shop/enclosed_buy",
     *       tags={"DropMiniShop"},
     *       @OA\RequestBody(
     *           required=true,
     *           @OA\MediaType(
     *               mediaType="application/json",
     *               @OA\Schema(required={"wallet_address","amount"},
     *               @OA\Property(property="wallet_address",description="Адрес БЧ кошелька пользователя",type="string"),
     *               @OA\Property(property="amount",type="int",description="Покупка 1 или 3 токенов?",default=1)
     *               )
     *           )
     *       ),
     *       @OA\Response(response="200", description="OK"),
     *       @OA\Response(response="403", description="Not Authorized"),
     *       @OA\Response(response="422", description="Validation Exception")
     *   )
     */
    function enclosedBuy(DropMiniShopEnclosedBuyRequest $request): JsonResponse
    {
        $buyEnclosedMintPayment = Payment::query()->createMint(
            Auth::id(),
            $request->price,
            Payment::TYPE_MINT,
            null,
            json_encode(['nftBuyer' => $request->wallet_address, 'stage' => $request->type, 'count' => $request->amount])
        );
        $res = $this->getPaymentLink($buyEnclosedMintPayment);
        if (!$res['success']) {
            return response()->json(['status' => 'error', 'message' => "Ошибка оплаты токена"]);
        }
        $saleEnclosedMintPayment = Payment::query()->createMint(
            $this->service->getUserIdCollectionDropMiniShop(),
            $request->price,
            Payment::TYPE_MINT_SALE,
            json_encode($res),
            json_encode(['nftBuyer' => $request->wallet_address, 'stage' => $request->type, 'count' => $request->amount])
        );
        $buyEnclosedMintPayment->update(['transaction_uuid' => $res['data']['paymentID'], 'completed_at' => null, 'response'=>json_encode($res)]);
        $saleEnclosedMintPayment->update(['transaction_uuid' => $res['data']['paymentID'], 'completed_at' => null]);

        if (env('DROP_MINI_SHOP_ENCLOSED_BUY') || $this->freePay) {
            return $this->successfulBuyMintTest($buyEnclosedMintPayment);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Ссылка на оплату токена', 'url' => $res['data']['url']]);
        }
    }

    /**
     * Покупка токена в открытой распродаже
     *
     * @OA\Post(
     *       path="/api/v1/drop_mini_shop/open_buy",
     *       tags={"DropMiniShop"},
     *       @OA\RequestBody(
     *           required=true,
     *           @OA\MediaType(
     *               mediaType="application/json",
     *               @OA\Schema(required={"wallet_address","currency"},
     *               @OA\Property(property="wallet_address",description="Адрес БЧ кошелька пользователя",type="string"),
     *               @OA\Property(property="currency",type="string",description="Валюта",default="cash|kefirium")
     *               )
     *           )
     *       ),
     *       @OA\Response(response="200", description="OK"),
     *       @OA\Response(response="403", description="Not Authorized"),
     *       @OA\Response(response="422", description="Validation Exception")
     *   )
     */
    function openBuy(DropMiniShopOpenBuyRequest $request): JsonResponse
    {
        $openMintPayment = Payment::query()->createMint(
            $request->authUser->id,
            ($request->currency == DropMiniShopSettings::CURRENCY_KEFIRIUM) ? $request->price_kefir : $request->price_rur,
            Payment::TYPE_MINT,
            null,
            json_encode(['nftBuyer' => $request->wallet_address, 'stage' => $request->type, 'count' => 1, 'amount' => $request->currency])
        );

        if ($request->currency == DropMiniShopSettings::CURRENCY_KEFIRIUM) {
            $response = $this->service->dropMintRequest($request->wallet_address, $request->type);

            if ($response['status'] == 'error') {
                return response()->json(['status' => 'error', 'message' => $response['message']], 422);
            }

            $mintTransaction = Transaction::query()->createMint(
                $this->service->getUserIdCollectionDropMiniShop(),
                $openMintPayment->amount,
                ($response['status'] == 'success') ? Transaction::STATUS_SUCCESS : Transaction::STATUS_PENDING,
                $response['data']['data']['hash'],
                $openMintPayment->id,
                null,
                null,
                Transaction::CURRENCY_KEFIRIUM_TYPE,
                $request->authUser->id
            );

            $this->service->setProcessingDrop($request->wallet_address);

            if ($response['status'] == 'success') {
                DB::beginTransaction();
                try {
                    $executeMint = $this->createMintDropMiniShop
                        ->execute(
                            $response['data']['data']['details']['tokens'][0],
                            $mintTransaction,
                            $openMintPayment
                        );
                    if ($executeMint['status'] == 'error') {
                        return response()->json(['status' => $executeMint['status'], 'message' => $executeMint['message']], 500);
                    }
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendErrorMint($openMintPayment, 'Ошибка во время минта @openBuy .' . $e->getMessage());
                    return response()->json(['status' => 'error', 'message' => 'Ошибка во время минта токена'], 500);
                } finally {
                    $this->service->setProcessingDrop($request->wallet_address, false);
                }
            }
            return response()->json(['status' => 'success', 'message' => $response['message']]);
        } else {
            $res = $this->getPaymentLink($openMintPayment);
            if (!$res['success']) {
                return response()->json(['status' => 'error', 'message' => "Ошибка оплаты токена"]);
            }
            $saleOpenMintPayment = Payment::query()->createMint(
                $this->service->getUserIdCollectionDropMiniShop(),
                $openMintPayment->amount,
                Payment::TYPE_MINT_SALE,
                json_encode($res),
                json_encode(['nftBuyer' => $request->wallet_address, 'stage' => $request->type])
            );
            $openMintPayment->update(['transaction_uuid' => $res['data']['paymentID'], 'completed_at' => null, 'response'=>json_encode($res)]);
            $saleOpenMintPayment->update(['transaction_uuid' => $res['data']['paymentID'], 'completed_at' => null]);

            if (env('DROP_MINI_SHOP_OPEN_BUY') || $this->freePay) {
                return $this->successfulBuyMintTest($openMintPayment);
            } else {
                return response()->json(['status' => 'success', 'message' => 'Ссылка на оплату токена', 'url' => $res['data']['url']]);
            }
        }
    }

    function getPaymentLink(Payment $payment): array
    {
        return $this->paymentService->payForEntityTokenMint(
            "DropMiniShop",
            $payment,
            $payment->amount,
            intval(env('DROP_MINI_SHOP_COLLECTION'))
        );
    }

    function successfulBuyMintTest(Payment $payment): JsonResponse
    {
        Input::replace([
            'orderID' => $payment->id,
            'status' => [
                'code' => 20,
            ],
            'paymentID' => $payment->id,
        ]);
        $resp = $this->paymentController->toPayEntity(
            app(DropMiniShopService::class),
            app(CreateMintDropMiniShopService::class)
        );
        if ($resp->getStatusCode() !== 200) {
            return $resp;
        } else {
            return response()->json([
                'status' => 'success',
                'message' => 'Успешная покупка',
                'url' => "?result=success&payment_id=$payment->id"
            ]);
        }
    }

    /**
     * @param Payment $mintPayment
     * @param $data
     * @param Payment $saleMintPayment
     * @return bool
     */
    public function updatePaymentId(Payment $mintPayment, $data, Payment $saleMintPayment): bool
    {
        $mintPayment->update(['transaction_uuid' => $data['paymentID'], 'completed_at' => null]);
        $saleMintPayment->update(['transaction_uuid' => $data['paymentID'], 'completed_at' => null]);

        return true;
    }
}
