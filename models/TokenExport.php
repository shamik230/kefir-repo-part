<?php

namespace Marketplace\Tokens\Models;

use Backend\Models\ExportModel;
use Illuminate\Support\Facades\DB;
use Marketplace\Collections\Models\Collection;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use RainLab\User\Models\User;

class TokenExport extends ExportModel
{
    public function exportData($columns, $sessionKey = null)
    {
        $tokens_arr = [];

        Token::orderBy('id', 'desc')->chunk(100, function ($tokens) use (&$tokens_arr) {


            foreach ($tokens as $token) {

                if ($token->type) {
                    $value = DB::table('marketplace_tokens_tokens')->where('id', $token['id'])->first();
                    $file = stripos($value->file, 'ipfs') ? 'https://gateway.pinata.cloud' . $value->file : 'https://gateway.pinata.cloud/ipfs/' . $value->file;
                    $token->type = $file;
                }

                if ($token['collection_id']) {
                    $collection = Collection::where('id', $token['collection_id'])->first();
                    if ($collection) {
                        $token['collection_id'] = $collection['name'];
                    }
                }
                if ($token['user_id']) {
                    $user = User::where('id', $token['user_id'])->first();
                    if ($user) {
                        $token['user_id'] = $user['email'];
                    }
                }
                if ($token['is_sale']) {
                    $token['is_sale'] = 'Да';
                } else {
                    $token['is_sale'] = 'Нет';
                }

                if ($token['is_hidden']) {
                    $token['is_hidden'] = 'Да';
                } else {
                    $token['is_hidden'] = 'Нет';
                }

                $token['in_progress'] = env('APP_URL') . '/collection/token/' . $token['id'];

                if ($token['moderation_status_id']) {
                    $moder = ModerationStatus::where('id', $token['moderation_status_id'])->first();
                    if ($moder) {
                        $token['moderation_status_id'] = $moder['name'];
                    }
                }
                if ($token['author_id']) {
                    $user = User::where('id', $token['author_id'])->first();
                    if ($user) {
                        $token['author_id'] = $user['email'];
                    }
                }
                array_push($tokens_arr, $token);
            }
        });
        return $tokens_arr;
    }
}
