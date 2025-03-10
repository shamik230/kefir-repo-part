<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBoxesTable Migration
 */
class CreateBoxesTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_tokens_boxes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('boxable_id')->nullable();
            $table->string('boxable_type')->nullable();
            $table->string('type_code')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->foreign('type_code')->references('code')->on('marketplace_tokens_box_types')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_tokens_boxes');
    }
}