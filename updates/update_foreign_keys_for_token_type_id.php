<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class UpdateForeignKeysForTokenTypeId extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->foreign('token_type_id')->references('id')->on('marketplace_tokens_token_types');
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropForeign(['token_type_id']);
        });
    }
}