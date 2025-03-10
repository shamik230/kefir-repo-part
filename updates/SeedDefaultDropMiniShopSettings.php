<?php

namespace Marketplace\Tokens\Updates;

use Carbon\Carbon;
use Db;
use Marketplace\Tokens\Models\DropMiniShopSettings as DMSS;
use October\Rain\Database\Updates\Migration;

class SeedDefaultDropMiniShopSettings extends Migration
{
    function up()
    {
        $defaultSettings = [
            DMSS::FREE_DROP_IS_ACTIVE => '1',
            DMSS::FREE_DROP_TYPE => DMSS::FREE_DROP_TYPE_ACTIVE_VK,
            DMSS::ENCLOSED_SALES_IS_ACTIVE => '0',
            DMSS::ENCLOSED_SALES_DATE => Carbon::create(2024, 3, 13)->toDateTimeString(),
            DMSS::ENCLOSED_SALES_START => now()->setTime(10, 0)->toDateTimeString(),
            DMSS::ENCLOSED_SALES_END => now()->setTime(0, 0)->toDateTimeString(),
            DMSS::ENCLOSED_SALES_PRICE_ONE_TOKEN => 100,
            DMSS::ENCLOSED_SALES_PRICE_THREE_TOKEN => 290,
            DMSS::OPEN_SALES_IS_ACTIVE => '0',
            DMSS::OPEN_SALES_DATE_START => Carbon::now()->toDateTimeString(),
            DMSS::OPEN_SALES_PRICE_TOKEN_RUR => 100,
            DMSS::OPEN_SALES_PRICE_RUR_INCREASE => 10,
            DMSS::OPEN_SALES_PRICE_TOKEN_KEFIR => 50,
            DMSS::OPEN_SALES_PRICE_KEFIR_INCREASE => 2,
        ];

        Db::table('system_settings')
            ->updateOrInsert(
                ['item' => (new DMSS)->settingsCode],
                ['value' => json_encode($defaultSettings)]
            );
    }
}
