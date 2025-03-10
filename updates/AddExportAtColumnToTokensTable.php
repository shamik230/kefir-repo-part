<?php

namespace Marketplace\TokensToImport\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

class AddExportAtColumnToTokensTable extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dateTime('export_at')->nullable();
        });
    }

    function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn(['export_at']);
        });
    }
}
