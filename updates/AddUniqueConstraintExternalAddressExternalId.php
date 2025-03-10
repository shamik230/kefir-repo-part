<?php

namespace Marketplace\TokensToImport\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AddUniqueConstraintExternalAddressExternalId extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->unique(['external_id', 'external_address']);
        });
    }

    function down()
    {
    }
}


