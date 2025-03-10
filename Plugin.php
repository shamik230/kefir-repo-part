<?php

namespace Marketplace\Tokens;

use Marketplace\Tokens\Component\TokenComponent;
use Marketplace\Tokens\Component\TokenComponentInterface;
use Marketplace\Tokens\Component\TokenOwnershipComponent;
use Marketplace\Tokens\Component\TokenOwnershipComponentInterface;
use Marketplace\Tokens\Gateway\TokenGateway;
use Marketplace\Tokens\Gateway\TokenGatewayInterface;
use Marketplace\Tokens\Models\FactoryDropSetting;
use Marketplace\Tokens\Models\RedirectLinkSetting;
use Marketplace\Tokens\ReportWidgets\ReferralStatisticsWidget;
use Marketplace\Tokens\ReportWidgets\Tokens;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function registerComponents() {}


    public function boot()
    {
        $this->app->alias(TokenOwnershipComponent::class, TokenOwnershipComponentInterface::class);
        $this->app->alias(TokenGateway::class, TokenGatewayInterface::class);
        $this->app->alias(TokenComponent::class, TokenComponentInterface::class);

        $this->app->bind(TokenGateway::class, function () {
            return new TokenGateway();
        });

        $this->app->bind(TokenOwnershipComponent::class, function ($app) {
            return new TokenOwnershipComponent($app->make(TokenGatewayInterface::class));
        });

        $this->app->bind(TokenComponent::class, function ($app) {
            return new TokenComponent($app->make(TokenGatewayInterface::class));
        });
    }

    public function registerReportWidgets()
    {
        return [
            Tokens::class => [
                'label' => 'Токены',
                'context' => 'dashboard',
            ],
            ReferralStatisticsWidget::class => [
                'label' => 'Реферальная статистика',
                'context' => 'dashboard',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'QR code links',
                'description' => 'Ссылки для редиректа по QR',
                'category' => 'Настройки',
                'icon' => 'icon-cog',
                'class' => RedirectLinkSetting::class,
                'order' => 500,
                'keywords' => 'main-location',
                'permissions' => ['marketplace.tokens.*'],
            ]
        ];
    }
}
