<?php

namespace Marketplace\TokensToImport\Updates;

use Db;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class RecreateIndexIdxSearchTokens extends Migration
{
    function up()
    {
        DB::statement("DROP INDEX IF EXISTS idx_search_tokens");
        DB::statement("CREATE INDEX idx_search_tokens on marketplace_tokens_tokens USING GIN(search_by_name_description)");
    }

    function down()
    {
//
    }
}
