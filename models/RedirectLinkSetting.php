<?php

namespace Marketplace\Tokens\Models;

use Model;
use System\Behaviors\SettingsModel;

/**
 * RedirectLinkSetting Model
 */
class RedirectLinkSetting extends Model
{
    public $implement = [
        SettingsModel::class
    ];

    public $settingsCode = 'redirect_link_setting';

    public $settingsFields = 'fields.yaml';
}
