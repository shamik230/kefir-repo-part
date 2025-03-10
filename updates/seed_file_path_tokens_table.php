<?php

namespace Marketplace\Tokens\Updates;

use Illuminate\Support\Facades\DB;
use Marketplace\Collections\Models\Collection;
use October\Rain\Database\Updates\Seeder;

class seed_file_path_tokens_table extends Seeder
{
    public function run()
    {
        $collection = Collection::query()
            ->where('contract_address', env('DROP_MINI_SHOP_ADDRESS'))
            ->firstOrFail();

        $url = config('app.url') ?? 'https://kefirium.ru';

        $query = "
            UPDATE marketplace_tokens_tokens
            SET file_path = '{$url}/storage/app/media/factories/modules/' || marketplace_token_equipments.factory_type || '/' || marketplace_token_equipments.module_type || '.mp4'
            FROM marketplace_token_equipments
            WHERE marketplace_tokens_tokens.id = marketplace_token_equipments.token_id
            AND marketplace_tokens_tokens.collection_id = {$collection->id}
            AND marketplace_tokens_tokens.file_path IS NULL
        ";

        DB::statement($query);
    }
}
