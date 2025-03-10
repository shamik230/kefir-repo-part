<?php

namespace Marketplace\Tokens\Updates;

use Marketplace\Tokens\Models\TokenType;
use October\Rain\Database\Updates\Seeder;

class SeedTokenTypesBox extends Seeder
{
    public function run()
    {
        TokenType::firstOrCreate(
            [
                'id' => TokenType::BOX_TOKEN,
            ],
            [
                'name' => 'Box',
            ]
        );

        TokenType::firstOrCreate(
            [
                'id' => TokenType::FACTORY_PASS_TOKEN,
            ],
            [
                'name' => 'FactoryPass',
            ]
        );
    }
}
