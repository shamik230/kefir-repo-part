<?php

namespace Marketplace\Tokens\Jobs;

use Marketplace\Tokens\Models\Token;
use Marketplace\Traits\Logger;

class ResizerGif
{
    use Logger;

    function fire($job, $data)
    {
        $this->logDebug('ResizerGif@fire', [
            'data' => $data,
        ]);
        $fileName = str_replace(".gif", ".webp", $data['file']);
        // exec('sudo su www-data  -s /bin/bash -c "convert ' . $data['file'] . ' -verbose -coalesce  -define webp:low-memory=true -define webp:method=3 -define webp:thread-level=0 -thumbnail 370x370 -quality 75 -layers OptimizePlus webp:' . $fileName . '"');
        exec('sudo su www-data -s /bin/bash -c "convert ' . $data['file'] . ' -verbose -coalesce  -define webp:low-memory=true -define webp:method=3 -define webp:thread-level=0 -thumbnail 370x370 -quality 90 ' . $fileName . '"');

        // $size_w = 370;
        // $size_h = 370;
        // foreach ($image as $k => $frame) {

        //     // $frame->cropImage($size_w, $size_h,$size_w,$size_h);
        //     $frame->thumbnailImage($size_w, $size_h);
        //     $frame->setImagePage($size_w, $size_h, 0, 0);
        //     // $frame->setImageCompression(\Imagick::COMPRESSION_LZW);
        //     // $frame->setImageCompressionQuality(50);
        // }

        // $image = $image->deconstructImages();

        // $image->writeImages($data['file'], true);

        $job->delete();
    }
}
