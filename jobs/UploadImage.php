<?php

namespace Marketplace\Tokens\Jobs;

use Illuminate\Http\UploadedFile;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Tokens\Actions\ResizerGifAction;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Services\TokenImageService;
use Marketplace\Traits\Logger;
use System\Models\File;
use Exception;

class UploadImage
{
    use Logger;

    /**
     * @throws Exception
     */
    function fire($job, $data)
    {
        $this->logDebug('UploadImage@fire', [
            'data' => $data,
        ]);

        /** @var Token $token */
        $token = Token::find($data['tokenId']);
        $originalImageUrl = $data['original_image_url'];

        $mimeType = get_headers($originalImageUrl, true)['Content-Type'];

        $extension = explode('/', $mimeType)[1];
        $file = (new File)->fromUrl($originalImageUrl, "$token->id.$extension");
        $uploadFile = (new UploadedFile($file->getLocalPath(), $file->getFilename()));

        /** @var TokenImageService $tokenImageService */
        $tokenImageService = app(TokenImageService::class);
        $resultAdaptation = $tokenImageService->adaptationImage(
            $uploadFile,
            $token,
            app(CollectionController::class),
            app(ResizerGifAction::class),
            app(ResizerMp4::class)
        );
        if (!$resultAdaptation) {
            throw new Exception('Обработка изображения не удалась');
        }
        $job->delete();
    }
}
