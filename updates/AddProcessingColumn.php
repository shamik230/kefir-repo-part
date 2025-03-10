<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddProcessingColumn extends Migration
{
    function up()
    {
        Schema::table('marketplace_drop_mini_shop_constraints', function (Blueprint $table) {
            $table->boolean('processing')->default(false);
        });
    }

    function down()
    {
        Schema::table('marketplace_drop_mini_shop_constraints', function (Blueprint $table) {
            $table->dropColumn(['processing']);
        });
    }
}
