<?php

namespace Marketplace\Tokens\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Marketplace\Tokens\Models\Box;

/**
 * Box Tokens Backend Controller
 */
class BoxTokens extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        // \Backend\Behaviors\RelationController::class
    ];

    public $formConfig = 'config_form.yaml';

    public $listConfig = 'config_list.yaml';

    // public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Marketplace.BoxDrop', 'main-boxes', 'side-boxtokens');
    }

    public function listExtendQuery($query)
    {
        $query->withTrashed()
            ->with(['tokenable', 'tokenable.boxable'])
            ->where('tokenable_type', Box::class);
    }

    public function formExtendQuery($query)
    {
        $query->withTrashed()
            ->with(['tokenable', 'tokenable.boxable'])
            ->where('tokenable_type', Box::class);
    }
}
