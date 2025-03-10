<?php

namespace Marketplace\Tokens\Generation;

use Auth;
use Exception;
use Illuminate\Support\Facades\DB;
use Input;
use Lang;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Collections\Models\Collection;
use Marketplace\Tokens\Controllers\TokenController;
use Marketplace\Tokens\ImageIpfs;
use Marketplace\Tokens\Models\Token;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use Queue;
use Validator;

class GenerateTokens
{

    public function generate()
    {
        ini_set('max_execution_time', 100);
        $dataValid =
            [
                'file' => 'required|mimes:mp4,mov,ogg,qt,jpg,png,gif,webm,mp3,wav,glb,gltf,obj|max:100000|dimensions:min_width=400,min_height=400',
                'name' => 'required|string',
                'description' => 'required|string',
                'preview' => 'image|max:10000|dimensions:min_width=400,min_height=400',
                'collection_id' => 'required|integer',
                'user_id' => 'required|integer',
                'count' => 'required|json',
                'royalty' => 'integer',
                'price' => 'required|numeric|min:100',
                'is_sale' => 'required|boolean',
                'external_reference' => 'url',
                'content_on_redemption' => 'string|min:1',
            ];
        $tokenController = new TokenController();
        if (Input::file('file') && Input::file('file')->getMimeType() == 'image/gif') {
            $dataValid['file'] = 'required|mimes:mp4,mov,ogg,qt,jpg,png,gif,webm,mp3,wav,glb,gltf,obj|max:100000|dimensions:min_width=400,min_height=400';
            $validator2 = Validator::make(
                Input::all(),
                $dataValid,
                Lang::get('marketplace.tokens::validation')
            );


            if ($validator2->fails()) {

                return response()->json($validator2->messages(), 422);
            }
        } else {
            if (Input::file('file') && $tokenController->checkType(Input::file('file')->getMimeType())) {
                $validator = Validator::make(
                    Input::all(),
                    $dataValid,
                    Lang::get('marketplace.tokens::validation')
                );

                if ($validator->fails()) {

                    return response()->json($validator->messages(), 422);
                }
            } else {
                $dataValid['file'] = 'required|mimes:mp4,mov,ogg,qt,jpg,png,gif,webm,mp3,wav,glb,gltf,obj|max:100000';
                $validator = Validator::make(
                    Input::all(),
                    $dataValid,
                    Lang::get('marketplace.tokens::validation')
                );

                if ($validator->fails()) {

                    return response()->json($validator->messages(), 422);
                }
            }
        }


        $collecton = Collection::where('id', Input::get('collection_id'))->where('user_id', Input::get('user_id'))->first();

        if ($collecton) {
            if ($collecton->moderation_status_id !== 3) {
                return response()->json(['collection_id' => ['Коллекция не прошла модерацию']], 400);
            }

            if (!$tokenController->checkType(Input::file('file')->getMimeType()) && !Input::file('preview')) {

                return response()->json([
                    "preview" => [
                        "Поле preview обязательно.",
                    ],
                ], 422);
            }


            $data = json_decode(Input::get('count'), true);
            DB::beginTransaction();
            try {
                for ($i = $data['start']; $i < $data['end']; $i++) {

                    $a = (int)$i + 1;
                    $token = Token::create([
                        'name' => Input::get('name') . (string)$a,
                        'description' => Input::get('description'),
                        'collection_id' => Input::get('collection_id'),
                        'content_on_redemption' => Input::get('content_on_redemption'),
                        'royalty' => 5,
                        'price' => Input::get('price'),
                        'hidden' => Input::get('hidden'),
                        'user_id' => Input::get('user_id'),
                        'is_sale' => false,
                        'type' => Input::file('file')->getMimeType(),
                        'author' => Input::get('user_id'),
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
                    Transaction::query()->createModerated(
                        Input::get('user_id'), $token->id, Input::get('price')
                    );

                    if (Input::get('is_sale')) {
                        $token->is_sale = Input::get('is_sale');
                        Transaction::create([
                            'from_user_id' => Input::get('user_id'),
                            'token_id' => $token->id,
                            'transaction_types_id' => TransactionTypes::TYPE_EXPOSED_FOR_SALE_ID,
                            'price' => Input::get('price'),

                        ]);
                    }


                    $token->moderation_status_id = 3;
                    $token->save();

                    Queue::push(ImageIpfs::class, [
                        'token' => $token->id,
                        'file' => $token->upload_file->getLocalPath(),
                        'filename' => $token->upload_file->file_name
                    ]);

                    if ($token->upload_file->extension == 'gif') {
                        $tokenController->gifResizer($token, Input::file('file'));
                    }
                }
                DB::commit();

            } catch (Exception $e) {
                // something went wrong
                DB::rollback();
            }


            return response()->json(['status' => 'success'], 200);
        }
        return response()->json(['error' => ['Forbidden']], 403);
    }

}
