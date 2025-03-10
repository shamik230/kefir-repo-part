<?php

namespace Marketplace\TokensToImport\Updates;

use Marketplace\Transactions\Models\Transaction;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddCurrencyColumn extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->string('currency')->default(Transaction::CURRENCY_RUR_TYPE);
        });
    }

    function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
}
