<?php

namespace Marketplace\Tokens\Jobs;

use Imagick;
use Marketplace\Tokens\Models\Token;
use Str;

class ResizerTokenPreview
{

    public function fire($job, $data)
    {
        $tokens = Token::where('id', 91)->get();

        foreach ($tokens as $token) {
            if ($token->preview_upload && ($token->preview_upload->extension == 'png' || $token->preview_upload->extension == 'jpg' || $token->preview_upload->extension == 'jpeg') && file_exists($token->preview_upload->getLocalPath())) {
                $name = mb_strtolower(Str::random(30) . rand(0, 9999));

                $image = new Imagick();
                $image->readImage($token->preview_upload->getLocalPath());

                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->setOption('webp:lossless', 'true');
                $image->writeImage('/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp');
                $token->preview_upload = '/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp';
                $token->save();

            }
        }
        $job->delete();
    }
}
