<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AlterTokensTableSetExternalIdAsString extends Migration
{
    function up(): void
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
           $table->string('external_id')->nullable()->change();
        });
    }

    function down(): void
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->integer('external_id')->nullable()->change();
        });
    }
}
