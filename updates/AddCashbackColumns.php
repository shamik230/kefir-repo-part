<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddCashbackColumns extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->unsignedDecimal('buyer_percent')->nullable()
                ->comment('Buyer cashback percent on sale');
            $table->unsignedDecimal('seller_percent')->nullable()
                ->comment('Seller cashback percent on sale');
        });
    }

    function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn(['buyer_percent', 'seller_percent']);
        });
    }
}
