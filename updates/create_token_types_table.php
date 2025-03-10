<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateTokenTypesTable Migration
 */
class CreateTokenTypesTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_tokens_token_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_tokens_token_types');
    }
}