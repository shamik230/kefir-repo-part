<?php

namespace Marketplace\Tokens\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class BuilderTableUpdateMarketplaceTokensTokens27 extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->text('content_on_redemption')->nullable();
            $table->text('is_utilitarian')->default(false);
            $table->text('is_produced')->default(false);
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('content_on_redemption');
            $table->dropColumn('is_utilitarian');
            $table->dropColumn('is_produced');
        });
    }
}
