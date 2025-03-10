<?php

namespace Marketplace\Tokens\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ImportExportController;
use Backend\Behaviors\ListController;
use Backend\Behaviors\RelationController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * @mixin ListController
 * @mixin FormController
 * @mixin RelationController
 * @mixin ImportExportController
 */
class Token extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
        RelationController::class,
        ImportExportController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $importExportConfig = 'config_import_export.yaml';
    public $relationConfig = 'config_relation.yaml';

    function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Marketplace.Tokens', 'main-menu-tokens');
    }
}
