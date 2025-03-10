<?php namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableCreateMarketplaceTokensTokens extends Migration
{
    public function up()
    {
        Schema::create('marketplace_tokens_tokens', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('user_id');
            $table->integer('collection_id');
            $table->string('file')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('price', 10, 0);
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_tokens_tokens');
    }
}
