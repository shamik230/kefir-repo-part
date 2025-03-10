<?php

namespace Marketplace\BoxDrop\Updates;

use Marketplace\Tokens\Models\RedirectLinkSetting;
use October\Rain\Database\Updates\Seeder;

class SeedRedirectLinkSettings extends Seeder
{
    public function run()
    {
        RedirectLinkSetting::set('ar_link', 'https://projects.web-ar.studio/configurator/c8e2e62d4a/?id=29277_165158');
    }
}
