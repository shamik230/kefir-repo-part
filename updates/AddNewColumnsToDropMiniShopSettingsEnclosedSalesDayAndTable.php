<?php

namespace Marketplace\TokensToImport\Updates;

use Db;
use Marketplace\Tokens\Models\DropMiniShopSettings as S;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddNewColumnsToDropMiniShopSettingsEnclosedSalesDayAndTable extends Migration
{
    public function up()
    {
        DB::table('marketplace_drop_mini_shop_settings')->update([
            'settings' => DB::raw("jsonb_set(settings, '{enclosed_sales_date_end}', to_jsonb(now()::date), false)")
        ]);

    }

    public function down()
    {
        //
    }
}
