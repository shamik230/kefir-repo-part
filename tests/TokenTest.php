<?php

namespace Marketplace\Tokens\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Input;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Tokens\Models\Token;
use PluginTestCase;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;

class TokenTest /*extends PluginTestCase*/
{
    use DatabaseTransactions;

    function testCreateCollection()
    {
        Auth::login(User::first());

        $token = Token::create([
            'name' => Input::get('name'),
            'description' => Input::get('description'),
            'collection_id' => Input::get('collection_id'),
            'royalty' => 5,
            'price' => Input::get('price'),
            'hidden' => Input::get('hidden'),
            'user_id' => Auth::user()->id,
            'type' => Input::file('file')->getMimeType(),
            'author' => Auth::user()->id,
            'is_sale' => false,
            'moderation_status_id' => 1,
            'external_reference' => Input::get('external_reference'),
        ]);

        $token->upload_file = Input::file('file');

        if (Input::file('preview')) {
            $resizer = new CollectionController();
            $token->preview_upload = $resizer->resizer(Input::file('preview'), 370, 370);
        } else {

            $resizer = new CollectionController();
            $token->preview_upload = $resizer->resizer(Input::file('file'), 370, 370);
        }

        $token->save();
        $this->assertEquals(1, $token->id);
    }
}
