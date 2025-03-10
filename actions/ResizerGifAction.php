<?php

namespace Marketplace\Tokens\Actions;


use Marketplace\Traits\Logger;

class ResizerGifAction
{
    use Logger;

    function resize(string $preview_upload): void
    {
        $this->logDebug('ResizerGif@fire', [
            'data' => $preview_upload,
        ]);
        $fileName = str_replace(".gif", ".webp", $preview_upload);

        exec('sudo su www-data -s /bin/bash -c "convert ' . $preview_upload . ' -verbose -coalesce  -define webp:low-memory=true -define webp:method=3 -define webp:thread-level=0 -thumbnail 370x370 -quality 90 ' . $fileName . '"');
    }
}
