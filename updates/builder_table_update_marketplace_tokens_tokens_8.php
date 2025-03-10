<?php namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableUpdateMarketplaceTokensTokens8 extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->boolean('is_hidden')->nullable()->default(false);
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('is_hidden');
        });
    }
}
