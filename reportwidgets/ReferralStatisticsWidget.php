<?php

namespace Marketplace\Tokens\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Marketplace\KefiriumCashback\Models\KefiriumCashback as Cashback;
use RainLab\User\Models\ReferralCode;

class ReferralStatisticsWidget extends ReportWidgetBase
{
    function render()
    {
        return $this->makePartial('widget', [
            'views' => ReferralCode::query()->sum('viewed'),
            'registrations' => ReferralCode::query()->sum('registered'),
            'weekly_kefirium' => Cashback::query()
                ->referralTypes()
                ->fromDaysAgo(7)
                ->sum('amount')
            ,
            'monthly_kefirium' => Cashback::query()
                ->referralTypes()
                ->fromDaysAgo(30)
                ->sum('amount')
            ,
            'all_kefirium' => Cashback::query()
                ->referralTypes()
                ->sum('amount')
            ,
        ]);
    }
}
