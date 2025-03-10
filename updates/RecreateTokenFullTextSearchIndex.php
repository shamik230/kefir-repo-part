<?php

namespace Marketplace\TokensToImport\Updates;

use Db;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class RecreateTokenFullTextSearchIndex extends Migration
{
    function up()
    {
        Schema::table('marketplace_tokens_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_search_tokens');
        });
        Db::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION make_tsvector(title TEXT, content TEXT)
    RETURNS tsvector AS
$$
BEGIN
    RETURN (setweight(to_tsvector(title), 'A') ||
            setweight(to_tsvector(content), 'D'));
END
$$ LANGUAGE 'plpgsql' IMMUTABLE;
CREATE INDEX IF NOT EXISTS idx_search_tokens ON marketplace_tokens_tokens
    USING gin (make_tsvector(name, description))

SQL
        );
    }
}
