<?php

namespace Marketplace\Tokens\Jobs;

use Imagick;
use Marketplace\Collections\Models\Collection;
use Str;

class ResizerCollectionPreview
{

    public function fire($job, $data)
    {
        $collections = Collection::get();

        // foreach ($collections as $collection) {
        //     if ($collection->preview && ($collection->preview->extension == 'png' || $collection->preview->extension == 'jpg' || $collection->preview->extension == 'jpeg') && file_exists($collection->preview->getLocalPath())) {
        //         $name = mb_strtolower(Str::random(30) . rand(0, 9999));
        //         $image = new \Imagick();
        //         $image->readImage($collection->preview->getLocalPath());
        //         $image->setImageFormat('webp');
        //         $image->setImageCompressionQuality(80);
        //         $image->setOption('webp:lossless', 'true');
        //         $image->writeImage( '/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp');
        //         $collection->preview =  '/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp';

        //         $collection->save();

        //     }

        // }

        foreach ($collections as $collection) {
            if ($collection->background && $collection->background->extension == 'png' && file_exists($collection->background->getLocalPath())) {
                $name = mb_strtolower(Str::random(30) . rand(0, 9999));
                $image = new Imagick();
                $image->readImage($collection->background->getLocalPath());
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->setOption('webp:lossless', 'true');
                $image->writeImage('/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp');
                $collection->background = '/var/www/kefir-backend/backend/storage/app/media/' . $name . '.webp';

                $collection->save();

            }

        }

        $job->delete();
    }
}
