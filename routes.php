<?php

use Marketplace\Bearer\Middleware\BearerTokenMiddleware;
use Marketplace\Profile\Middleware\DataBaseRollbackMiddleware;
use Marketplace\Tokens\BlockchainMintMiddleware;
use Marketplace\Tokens\Controllers\Api\BarrelTokenController;
use Marketplace\Tokens\Controllers\Api\BoxTokenController;
use Marketplace\Tokens\Controllers\Api\RedirectController;
use Marketplace\Tokens\Controllers\DropMiniShopController;
use Marketplace\Tokens\Controllers\TokenController;
use RainLab\User\Classes\AuthMiddleware;

Route::middleware([DataBaseRollbackMiddleware::class])->group(function () {

    // Drop mini shop
    Route::prefix('/api/v1')->group(function () {
        Route::get('/drop_mini_shop', [DropMiniShopController::class, 'index']);
        Route::get('/drop_mini_shop/resetWalletRestrictions', [DropMiniShopController::class, 'resetWalletRestrictions']);
        Route::middleware(['web', AuthMiddleware::class])->prefix('/drop_mini_shop')->group(function () {
            Route::get('/sheet_white_list', [DropMiniShopController::class, 'sheetWhiteList']);
            Route::post('/free_drop', [DropMiniShopController::class, 'freeDrop']);
            Route::post('/enclosed_buy', [DropMiniShopController::class, 'enclosedBuy']);
            Route::post('/open_buy', [DropMiniShopController::class, 'openBuy']);
        });
    });

    // Barrels
    Route::prefix('/api/v1')->group(function () {
        Route::middleware(['web', AuthMiddleware::class])->group(function () {
            Route::post('/barrels/mint', [BarrelTokenController::class, 'mintBarrelToken']);
            Route::post('/barrels/burn', [BarrelTokenController::class, 'burnBarrelToken']);
        });
    });

    // Boxes
    Route::prefix('/api/v1')->group(function () {
        Route::get('/boxes/info', [BoxTokenController::class, 'getInfo']);

        Route::middleware(['web', AuthMiddleware::class])->group(function () {
            Route::post('/boxes/mint', [BoxTokenController::class, 'mintBox']);
            Route::post('/boxes/open', [BoxTokenController::class, 'openBox']);
            Route::post('/boxes/whitelisted', [BoxTokenController::class, 'checkWhiteList']);
        });
    });

    // Redirect route

    Route::get('/api/v1/qr/{link}', [RedirectController::class, 'redirectToLink']);

    // Token

    Route::get(
        '/token/polygon/{blockchain_contract_address}/{blockchain_token_id}',
        [TokenController::class, 'redirectTokenPage']
    );

    Route::group(['prefix' => '/api', 'middleware' => 'web'], function () {
        Route::post('/v1/token/create', [TokenController::class, 'create']);
        Route::post('/v1/token/refresh', [TokenController::class, 'refresh'])
            ->middleware(AuthMiddleware::class);;
        Route::get('/v1/user/{user_id}/tokens/filtersort', [TokenController::class, 'filterSort']);

        Route::get('/v1/token/{id}', [TokenController::class, 'index'])->where(['id' => '\d{1,9}']);
        Route::get(
            '/v1/tokens/trend',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@trend',
            ]
        );
        Route::get('/v1/tokens/user/{user_id}/pay', [TokenController::class, 'payTokens']);
        Route::get('/v1/tokens/author/{user_id}/pay', [TokenController::class, 'authorTokens',]);
        Route::get(
            '/v1/user/{id}/tokens/search',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@search',
            ]
        );
        Route::get(
            '/v1/author/{id}/tokens/search',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@searchAuthor',
            ]
        );
        Route::get('/v1/user/{id}/tokens/filter', [TokenController::class, 'filter']);
        Route::get(
            '/v1/author/{id}/tokens/filter',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@filterAuthor',
            ]
        );

        Route::get(
            '/v1/user/get/tokens/',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@userTokens',
            ]
        )->middleware(AuthMiddleware::class);

        Route::get('/v1/user/tokens/hidden', [TokenController::class, 'userTokensIsHidden'])
            ->middleware(AuthMiddleware::class);

        Route::post('/v1/token/sale', [TokenController::class, 'putTokenOnSale'])
            ->middleware(AuthMiddleware::class);

        Route::post('/v1/token/from/sale', [TokenController::class, 'removeTokenFromSale'])
            ->middleware(AuthMiddleware::class);

        Route::post(
            '/v1/token/changeOwnerByWallet',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@changeOwnerByWallet',
            ]
        )->middleware(AuthMiddleware::class);
        Route::post(
            '/v1/token/hidden/',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@userIsHidden',
            ]
        )->middleware(AuthMiddleware::class);
        Route::get(
            '/v1/user/tokens/search/hidden',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@searchHidden',
            ]
        )->middleware(AuthMiddleware::class);
        Route::get(
            '/v1/size/images',
            [
                'uses' => 'Marketplace\Tokens\Services\SizeServices@get',
            ]
        )->middleware(AuthMiddleware::class);
        Route::get(
            '/v1/user/tokens/filter/hidden',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@filterHidden',
            ]
        )->middleware(AuthMiddleware::class);
        // Route::get(
        //     '/v1/gif/resizer',
        //     array(
        //         'uses' => 'Marketplace\Tokens\Controllers\TokenController@gifResizerMain'
        //     )
        // )->middleware('Marketplace\Payments\Middleware\UserBackendMiddleware');

        Route::get('/v1/search', [TokenController::class, 'globalSearch']);
        // Route::get(
        //     '/v1/moder',
        //     array(
        //         'uses' => 'Marketplace\Tokens\Controllers\TokenController@modearated'
        //     )
        // );
        // Route::post(
        //     '/v1/token/mint',
        //     array(
        //         'uses' => 'Marketplace\Tokens\Controllers\TokenController@tokenMint'
        //     )
        // )->middleware(AuthMiddleware::class);
        Route::post('/v1/token/blockchain', [TokenController::class, 'tokenBlockchain'])
            ->middleware(BlockchainMintMiddleware::class);
        Route::post('/v1/token/blockchain/reset', [TokenController::class, 'tokenBlockchainReset']);

        Route::post(
            '/v1/token/sale/only',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@onlyIsSale',
            ]
        );

        Route::post(
            '/v1/token/mint/commission',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@commission',
            ]
        )->middleware(AuthMiddleware::class);
        Route::post(
            '/v1/token/verification/add',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@addVerificationInToken',
            ]
        )->middleware(AuthMiddleware::class);
        Route::post(
            '/v1/token/verification',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@tokenVerificationCheck',
            ]
        )->middleware(AuthMiddleware::class);
        Route::post(
            '/v1/token/verification/new/text',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@tokenVerificationCheckCacheDelete',
            ]
        )->middleware(AuthMiddleware::class);

        Route::post(
            '/v1/token/ownership',
            [
                'uses' => 'Marketplace\Tokens\Controllers\TokenController@verifyTokenOwnership',
            ]
        )->middleware(BearerTokenMiddleware::class);
        Route::post(
            '/v1/tokens/generate',
            [
                'uses' => 'Marketplace\Tokens\Generation\GenerateTokens@generate',
            ]
        )->middleware('\Marketplace\Tokens\Middleware\GenerateTokens');
    });
});
