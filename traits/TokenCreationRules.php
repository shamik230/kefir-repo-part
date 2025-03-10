<?php

namespace Marketplace\Tokens\Traits;

use Illuminate\Validation\Rule;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Tokens\Rules\CustomCollectionNameRule;
use Marketplace\Tokens\Rules\MaxLength;
use Marketplace\Tokens\Rules\MaxSizeRule;

trait TokenCreationRules
{
    function getTokenCreationRules($file): array
    {
        $rules = [
            'file' => [
                'bail',
                'required',
                'file',
                'mimes:mp4,mov,ogg,qt,jpg,png,gif,webm,webp,mp3,wav,glb,gltf,obj',
                new MaxSizeRule(100),
            ],
            'name' => 'required|string|max:255',
            'description' => 'required|string|min:5|max:2000',
            'preview' => [
                'bail',
                'required',
                'image',
                'dimensions:min_width=400,min_height=400,max_width=4096,max_height=4096',
                new MaxSizeRule(100),
            ],
            'collection_id' => [
                'integer',
                Rule::exists('marketplace_collections_collections', 'id'),
            ],
            'collection_name' => [
                'required_without:collection_id',
                'string',
                new MaxLength(255),
                new CustomCollectionNameRule,
            ],
            'collection_preview' => [
                'bail',
                'required_without:collection_id',
                'image',
                'mimes:jpg,png,gif',
                new MaxSizeRule(55),
                'dimensions:min_width=400,min_height=400',
                function ($attribute, $value, $fail) {
                    $maxSizeForGif = 1024;
                    $extension = $value->getClientOriginalExtension();
                    if ($extension === 'gif' && $value->getSize() > $maxSizeForGif * 1024) {
                        $fail("Максимальный размер для GIF файлов - " . ($maxSizeForGif / 1024) . "МБ");
                    }
                },
            ],
            'royalty' => 'integer',
            'price' => 'required|numeric|min:100|max:1000000000',
            'external_reference' => 'url',
            'content_on_redemption' => 'string|min:1|max:500',
            'hidden' => 'string|max:500',
        ];

        if ($file) {
            $fileType = explode("/", $file);
            if ($fileType[0] == "image") {
                $rules['file'] = [
                    'bail',
                    'required',
                    'mimes:mp4,mov,ogg,qt,jpg,png,gif,webm,mp3,wav,glb,gltf,obj',
                    new MaxSizeRule(100),
                    'dimensions:min_width=400,min_height=400',
                ];
            }
        }

        return $rules;
    }
}
