<?php

namespace Marketplace\TokensToImport\Updates;

use Db;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddSearchIdxColumnToTokensTable extends Migration
{
    function up()
    {
        DB::unprepared(<<<SQL
ALTER TABLE marketplace_tokens_tokens
    ADD COLUMN search_by_name_description tsvector
        GENERATED ALWAYS AS (
            setweight(to_tsvector('russian', name), 'A') ||
            setweight(to_tsvector('russian', description), 'D')
            ) STORED;
SQL
        );
    }

    function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn(['search_by_name_description']);
        });
    }
}
