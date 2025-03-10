<?php

namespace marketplace\tokens\updates;

use Db;
use Marketplace\Tokens\Models\DropMiniShopSettings as S;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class CreateDropMiniShopSettingsTable extends Migration
{
    function up()
    {
        Schema::create('marketplace_drop_mini_shop_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->jsonb('settings');
        });
        Db::table('marketplace_drop_mini_shop_settings')->insert([
            'id' => 1,
            'settings' => json_encode([
                'free_drop_is_active' => false,
                'free_drop_type' => S::FREE_DROP_TYPE_ACTIVE_TG,
                'enclosed_sales_is_active' => true,
                'enclosed_sales_date' => now()->setMonth(5)->setDay(6),
                'enclosed_sales_price_one_token' => 100,
                'enclosed_sales_start' => '00:00',
                'enclosed_sales_price_three_token' => 290,
                'enclosed_sales_completion' => '23:05',
                'open_sales_is_active' => true,
                'open_sales_date_start' => now()->setMonth(5)->setDay(5),
                'open_sales_price_token_rur' => 100,
                'open_sales_price_token_kefir' => 200,
                'open_sales_price_rur_increase' => 25,
                'open_sales_price_kefir_increase' => 35,
                'mini_rarity_claim' => 0.1,
                'mini_rarity_incomplete' => 0.2,
                'mini_rarity_full' => 0.5,
                'mini_rarity_price_pult' => 0,
                'usual_rarity_claim' => 0.5,
                'usual_rarity_incomplete' => 1,
                'usual_rarity_full' => 2.5,
                'usual_rarity_price_pult' => 0,
                'unusual_rarity_claim' => 1,
                'unusual_rarity_incomplete' => 2,
                'unusual_rarity_full' => 5,
                'unusual_rarity_price_pult' => 0,
                'rare_rarity_claim' => 1.5,
                'rare_rarity_incomplete' => 3,
                'rare_rarity_full' => 7.5,
                'rare_rarity_price_pult' => 0,
                'epic_rarity_claim' => 2.5,
                'epic_rarity_incomplete' => 5,
                'epic_rarity_full' => 12.5,
                'epic_rarity_price_pult' => 0,
                'legend_rarity_claim' => 5,
                'legend_rarity_incomplete' => 10,
                'legend_rarity_full' => 25,
                'legend_rarity_price_pult' => 0,
            ]),
        ]);
    }

    function down()
    {
        Schema::dropIfExists('marketplace_drop_mini_shop_settings');
    }
}
