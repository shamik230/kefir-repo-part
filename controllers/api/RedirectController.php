<?php

namespace Marketplace\Tokens\Controllers\Api;

use Illuminate\Http\Request;
use Marketplace\Tokens\Models\RedirectLinkSetting;


/**
 * RedirectController API Controller
 */
class RedirectController
{
    public function redirectToLink(Request $request, string $link)
    {
        $url = (string) RedirectLinkSetting::get($link);
        return redirect($url);
    }
}
