<?php

namespace Marketplace\TokensToImport\Updates;

use Carbon\Carbon;
use Db;
use Marketplace\Tokens\Models\DropMiniShopSettings as DMSS;
use October\Rain\Database\Updates\Migration;

class SeedDefaultRaritySettings extends Migration
{
    function up()
    {
        $defaultSettings = [
            DMSS::MINI_RARITY_CLAIM => 0.1,
            DMSS::MINI_RARITY_INCOMPLETE => 0.2,
            DMSS::MINI_RARITY_FULL => 0.5,
            DMSS::MINI_RARITY_PRICE_PULT => 0,
            DMSS::USUAL_RARITY_CLAIM => 0.5,
            DMSS::USUAL_RARITY_INCOMPLETE => 1,
            DMSS::USUAL_RARITY_FULL => 2.5,
            DMSS::USUAL_RARITY_PRICE_PULT => 0,
            DMSS::UNUSUAL_RARITY_CLAIM => 1,
            DMSS::UNUSUAL_RARITY_INCOMPLETE => 2,
            DMSS::UNUSUAL_RARITY_FULL => 5,
            DMSS::UNUSUAL_RARITY_PRICE_PULT => 0,
            DMSS::RARE_RARITY_CLAIM => 1.5,
            DMSS::RARE_RARITY_INCOMPLETE => 3,
            DMSS::RARE_RARITY_FULL => 7.5,
            DMSS::RARE_RARITY_PRICE_PULT => 0,
            DMSS::EPIC_RARITY_CLAIM => 2.5,
            DMSS::EPIC_RARITY_INCOMPLETE => 5,
            DMSS::EPIC_RARITY_FULL => 12.5,
            DMSS::EPIC_RARITY_PRICE_PULT => 0,
            DMSS::LEGEND_RARITY_CLAIM => 5,
            DMSS::LEGEND_RARITY_INCOMPLETE => 10,
            DMSS::LEGEND_RARITY_FULL => 25,
            DMSS::LEGEND_RARITY_PRICE_PULT => 0,
        ];

        Db::table('system_settings')
            ->updateOrInsert(
                ['item' => (new DMSS)->settingsCode],
                ['value' => json_encode($defaultSettings)]
            );
    }
}
