<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBoxTypesTable Migration
 */
class CreateBoxTypesTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_tokens_box_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_tokens_box_types');
    }
}