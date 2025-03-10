<?php namespace Marketplace\Tokens\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Marketplace\Payments\Models\Payment;
use Marketplace\Tokens\Models\Token;

class Tokens extends ReportWidgetBase
{
    function render()
    {
        $tokens = Token::query()->count();
        $tokens_sale = Token::query()->where('is_sale', true)->count();
        $tokens_moder = Token::query()->where('moderation_status_id', 1)->count();
        $tokens_order = Payment::query()
            ->saleCompleted()
            ->count();

        return $this->makePartial('widget', [
            'tokens' => $tokens,
            'tokens_sale' => $tokens_sale,
            'tokens_moder' => $tokens_moder,
            'tokens_order' => $tokens_order,
        ]);
    }
}
