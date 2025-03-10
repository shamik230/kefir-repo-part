<?php

namespace Marketplace\Tokens\Updates;

use Marketplace\Collections\Models\Collection;
use Marketplace\Module\Models\Module;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Models\TokenType;
use October\Rain\Database\Updates\Seeder;

class SeedTokenTypesFactory extends Seeder
{
    public function run()
    {
        TokenType::firstOrCreate(
            [
                'id' => TokenType::FACTORY_MODULE_TOKEN,
            ],
            [
                'name' => 'Factory',
            ]
        );

        // Add token_type_id to exist minifactory tokens
        $modules = Module::query()
            ->with(['token'])
            ->get();

        foreach ($modules as $module) {
            if ($module->token) {
                $module->token->update([
                    'token_type_id' => TokenType::FACTORY_MODULE_TOKEN
                ]);

                $module->token->tokenable()->associate($module);
                $module->token->save();
            }
        }

        // Update tokes without module (if exists)
        $collection = Collection::query()
            ->where('contract_address', env('DROP_MINI_SHOP_ADDRESS'))
            ->firstOrFail();

        Token::query()
            ->where('collection_id', $collection->id)
            ->whereNull('token_type_id')
            ->update([
                'token_type_id' => TokenType::FACTORY_MODULE_TOKEN
            ]);
    }
}
