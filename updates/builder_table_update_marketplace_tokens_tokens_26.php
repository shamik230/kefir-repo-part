<?php

namespace Marketplace\Tokens\Updates;

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMarketplaceTokensTokens26 extends Migration
{
    public function up()
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION make_tsvector(title TEXT, content TEXT)
                            RETURNS tsvector AS $$
                        BEGIN
                        RETURN (setweight(to_tsvector('english', title),'A') ||
                            setweight(to_tsvector('english', content), 'B'));
                        END
                        $$ LANGUAGE 'plpgsql' IMMUTABLE;
                        CREATE INDEX IF NOT EXISTS idx_search_tokens ON marketplace_tokens_tokens
                        USING gin(make_tsvector(name, description));");
    }

    public function down()
    {
        // DB::statement("your query");
    }
}
