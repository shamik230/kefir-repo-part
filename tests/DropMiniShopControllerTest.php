<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class DropMiniShopControllerTest extends TestCase
{
    use DatabaseTransactions;

    function testIndexShouldReturnSettingsData()
    {
        $this->getJson("/api/v1/drop_mini_shop")
            ->assertSuccessful()
            ->assertJsonStructure([
                'free_drop',
                'enclosed_sales',
                'open_sales',
            ]);
    }
}
