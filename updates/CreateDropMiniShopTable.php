<?php

namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class CreateDropMiniShopTable extends Migration
{
    function up()
    {
        Schema::create('marketplace_drop_mini_shop_constraints', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('wallet_address')->unique();
            $table->integer('tokens_available');
            $table->integer('free_tokens_available');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('CASCADE');
        });
    }

    function down()
    {
        Schema::dropIfExists('marketplace_drop_mini_shop_constraints');
    }
}
