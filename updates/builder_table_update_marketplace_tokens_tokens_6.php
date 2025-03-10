<?php namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableUpdateMarketplaceTokensTokens6 extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->integer('author_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('author_id');
        });
    }
}
