<?php

namespace Marketplace\Tokens\Controllers;

use Backend\Behaviors\FormController;
use Backend\Classes\Controller;
use BackendMenu;

class DropMiniShopSettings extends Controller
{
    public $implement = [
        FormController::class,
    ];

    public $formConfig = 'config_form.yaml';

    function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Marketplace.Tokens', 'main-menu-drop-mini-shop-settings');
    }
}
