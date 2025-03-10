<?php namespace Marketplace\Tokens\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * Tokens Form Widget
 */
class Tokens extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'marketplace_tokens_tokens';

    /**
     * @inheritDoc
     */
    public function init()
    {
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('tokens');
    }

    /**
     * prepareVars for view data
     */
    public function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['model'] = $this->model;
    }

    /**
     * @inheritDoc
     */
    public function loadAssets()
    {
        $this->addCss('css/tokens.css', 'Marketplace.Tokens');
        $this->addJs('js/tokens.js', 'Marketplace.Tokens');
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}
