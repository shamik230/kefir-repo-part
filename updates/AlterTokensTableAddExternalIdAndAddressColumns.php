<?php

namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class AlterTokensTableAddExternalIdAndAddressColumns extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->integer('external_id')->nullable();
            $table->string('external_address')->nullable();
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'external_address']);
        });
    }
}
