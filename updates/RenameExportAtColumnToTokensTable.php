<?php

namespace Marketplace\TokensToImport\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

class RenameExportAtColumnToTokensTable extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->renameColumn('export_at', 'pending_at');
        });
    }

    function down()
    {
 //
    }
}
