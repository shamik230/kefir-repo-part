<?php namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableUpdateMarketplaceTokensTokens15 extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->text('mint')->nullable();
            $table->boolean('is_hidden')->default(false)->change();
            $table->boolean('in_progress')->default(false)->change();
            $table->boolean('is_booked')->default(false)->change();
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('mint');
            $table->boolean('is_hidden')->default(null)->change();
            $table->boolean('in_progress')->default(null)->change();
            $table->boolean('is_booked')->default(null)->change();
        });
    }
}
