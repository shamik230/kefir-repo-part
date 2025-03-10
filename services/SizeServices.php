<?php

namespace Marketplace\Tokens\Services;


class SizeServices
{


    public function get()
    {


        $data = [
            'token_preview' => [
                'min' => explode("||", env('TOKEN_PREVIEW_MIN')),
                'max' => explode("||", env('TOKEN_PREVIEW_MAX')),
            ],
            'collection_preview' => [
                'min' => explode("||", env('COLLECTION_PREVIEW_MIN')),
                'max' => explode("||", env('COLLECTION_PREVIEW_MAX')),
            ],
            'collection_background' => [
                'min' => explode("||", env('COLLECTION_BAKGROUND_MIN')),
                'max' => explode("||", env('COLLECTION_BAKGROUND_MAX')),
            ],
            'gallery_preview' => [
                'min' => explode("||", env('GALLERY_PREVIEW_MIN')),
                'max' => explode("||", env('GALLERY_PREVIEW_MAX')),
            ],
            'gallery_background' => [
                'min' => explode("||", env('GALLERY_BAKGROUND_MIN')),
                'max' => explode("||", env('GALLERY_BAKGROUND_MAX')),
            ],
            'user_banner' => [
                'min' => explode("||", env('USER_BANNER_MIN')),
                'max' => explode("||", env('USER_BANNER_MAX')),
            ],
            'user_avatar' => [
                'min' => explode("||", env('USER_AVATAR_MIN')),
                'max' => explode("||", env('USER_AVATAR_MAX')),
            ],
        ];
        return response()->json($data);
    }
}

