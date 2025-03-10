<?php

namespace marketplace\tokens\updates;

use Db;
use Marketplace\Tokens\Models\DropMiniShopSettings as S;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddNewColumnsToDropMiniShopSettingsTable extends Migration
{
    public function up()
    {
        $currentSettings = DB::table('marketplace_drop_mini_shop_settings')->where('id', 1)->first();
        $settingsArray = json_decode($currentSettings->settings, true);

        $settingsArray['mini_rarity_price_operator'] = 1;
        $settingsArray['usual_rarity_price_operator'] = 1;
        $settingsArray['unusual_rarity_price_operator'] = 1;
        $settingsArray['rare_rarity_price_operator'] = 1;
        $settingsArray['epic_rarity_price_operator'] = 1;
        $settingsArray['legend_rarity_price_operator'] = 1;

        $updatedSettings = json_encode($settingsArray);

        DB::table('marketplace_drop_mini_shop_settings')->where('id', 1)->update(['settings' => $updatedSettings]);

    }

    public function down()
    {
        //
    }
}
