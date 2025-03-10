<?php

namespace Marketplace\Tokens\Updates;

use Marketplace\Tokens\Models\TokenType;
use October\Rain\Database\Updates\Seeder;

class SeedKefirBarrels extends Seeder
{
    public function run()
    {
        $items = [
            [
                'id' => TokenType::BARREL_TOKEN,
                'name' => 'Barrel',
            ],
        ];

        foreach ($items as $item) {
            TokenType::firstOrCreate(
                [
                    'id' => $item['id']
                ],
                [
                    'name' => $item['name']
                ]
            );
        }
    }
}