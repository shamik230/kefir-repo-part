<?php

namespace Marketplace\TokensToImport\Updates;

use Db;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddIndexExternalAdressAndExternalId extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->index('external_id');
            $table->index('external_address');
        });
    }

    function down()
    {
//
    }
}
