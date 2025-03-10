<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class add_file_path_to_tokens_table extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('marketplace_tokens_tokens', 'file_path')) {
            Schema::table('marketplace_tokens_tokens', function ($table) {
                $table->string('file_path')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('marketplace_tokens_tokens', 'file_path')) {
            Schema::table('marketplace_tokens_tokens', function ($table) {
                $table->dropColumn('file_path');
            });
        }
    }
}
