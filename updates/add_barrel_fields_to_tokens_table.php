<?php

namespace Marketplace\Tokens\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddBarrelFieldsToTokensTable extends Migration
{
    public function up()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->decimal('barrel_volume', 10, 0)->nullable();
            $table->unsignedInteger('token_type_id')->nullable();
            $table->string('ipfs_json_uri')->nullable();
            $table->string('ipfs_img_uri')->nullable();
        });
    }

    public function down()
    {
        Schema::table('marketplace_tokens_tokens', function ($table) {
            $table->dropColumn('barrel_volume');
            $table->dropColumn('token_type_id');
            $table->dropColumn('ipfs_json_uri');
            $table->dropColumn('ipfs_img_uri');
        });
    }
}