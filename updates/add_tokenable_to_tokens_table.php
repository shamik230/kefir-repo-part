<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddTokenableToTokensTable extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->unsignedBigInteger('tokenable_id')->nullable();
            $table->string('tokenable_type')->nullable();
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('tokenable_id');
            $table->dropColumn('tokenable_type');
        });
    }
}