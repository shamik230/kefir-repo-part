<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddIndexesToTokensTable extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->index('author_id');
            $table->index('preview');
            $table->index('name');
            $table->index('file');
            $table->index('collection_id');
        });
    }

    function down()
    {
       //
    }
}
