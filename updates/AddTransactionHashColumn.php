<?php

namespace Marketplace\TokensToImport\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

class AddTransactionHashColumn extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->string('transaction_hash')->default('');
        });
    }

    function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn(['transaction_hash']);
        });
    }
}
