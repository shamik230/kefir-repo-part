<?php namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableUpdateMarketplaceTokensTokens12 extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->boolean('in_progress')->default(false);
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('in_progress');
        });
    }
}
