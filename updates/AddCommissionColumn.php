<?php

namespace Marketplace\TokensToImport\Updates;

use Marketplace\Transactions\Models\Transaction;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddCommissionColumn extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->integer('commission')->nullable();
        });
    }

    function down()
    {
        //
    }
}
