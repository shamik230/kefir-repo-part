<?php

namespace Marketplace\Tokens\Dto;

use Marketplace\Tokens\Models\DropMiniShopSettings;

class DropPhaseDto
{
    /** @var array|null */
    protected $phase;

    function __construct(?array $phase)
    {
        $this->phase = $phase;
    }

    function openSales(): bool
    {
        return in_array(DropMiniShopSettings::PHASE_OPEN_SALES, $this->phase);
    }

    function freeDrop(): bool
    {
        return in_array(DropMiniShopSettings::PHASE_FREE_DROP, $this->phase);
    }

    function enclosedSales(): bool
    {
        return in_array(DropMiniShopSettings::PHASE_ENCLOSED_SALES, $this->phase);
    }

    function inactive(): bool
    {
        return !$this->phase;
    }
}
