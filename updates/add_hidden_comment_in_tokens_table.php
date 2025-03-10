<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddHiddenCommentInTokensTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('marketplace_tokens_tokens', 'hidden_comment')) {
            Schema::table('marketplace_tokens_tokens', function ($table) {
                $table->text('hidden_comment')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('marketplace_tokens_tokens', 'hidden_comment')) {
            Schema::table('marketplace_tokens_tokens', function ($table) {
                $table->dropColumn('hidden_comment');
            });
        }
    }
}
