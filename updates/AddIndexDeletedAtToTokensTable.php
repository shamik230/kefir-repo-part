<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddIndexDeletedAtToTokensTable extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->index('deleted_at');
        });
    }

    function down()
    {
       //
    }
}
