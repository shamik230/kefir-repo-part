<?php

namespace Marketplace\Tokens\Updates;

use Marketplace\Tokens\Models\BoxType;
use October\Rain\Database\Updates\Seeder;

class SeedBoxTypes extends Seeder
{
    public function run()
    {
        $items = [
            [
                'code' => BoxType::KEFIRIUS,
                'name' => 'Кефириус',
            ],
            [
                'code' => BoxType::SPOTTY,
                'name' => 'Крипто Спотти от ВКонтакте',
            ],
            [
                'code' => BoxType::FACTORY_PASS,
                'name' => 'Пропуск на закрытую распродажу миницеха',
            ],
            [
                'code' => BoxType::MINIFACTORY1,
                'name' => 'Бак с молоком',
            ],
            [
                'code' => BoxType::MINIFACTORY2,
                'name' => 'Модуль пастеризации',
            ],
            [
                'code' => BoxType::MINIFACTORY3,
                'name' => 'Охладительная установка',
            ],
            [
                'code' => BoxType::MINIFACTORY4,
                'name' => 'Резервуар закваски',
            ],
            [
                'code' => BoxType::MINIFACTORY5,
                'name' => 'Линия розлива',
            ],
        ];

        foreach ($items as $item) {
            BoxType::firstOrCreate(
                [
                    'code' => $item['code']
                ],
                [
                    'name' => $item['name']
                ]
            );
        }
    }
}
