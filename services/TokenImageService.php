<?php

namespace Marketplace\Tokens\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Tokens\Actions\ResizerGifAction;
use Marketplace\Tokens\Jobs\ResizerMp4;
use Marketplace\Tokens\Jobs\UploadImage;
use Marketplace\Tokens\Models\Token;
use Marketplace\Traits\Logger;
use Queue;
use RainLab\User\Services\PushWebsocketService;

class TokenImageService
{
    use Logger;

    /**
     * Загружает изображение токена и вызывает обработку через джобу
     * @param Token $token
     * @param string $originalUrl Путь к изображению для загрузки
     * @return void
     */
    function processAndUploadImage(Token $token, string $originalUrl): void
    {
        /** @var IpfsUrlService $ipfsUrlService */
        $ipfsUrlService = app(IpfsUrlService::class);
        $urlData = $ipfsUrlService->processUrl($originalUrl);

        if ($urlData) {
            $ipfs = $urlData['ipfs'];
            $normalizedUrl = $urlData['normalizedUrl'];

            /** @var Token $tokenImageMatch */
            $tokenImageMatch = Token::query()
                ->moderated()
                ->where('file', $ipfs)
                ->first();

            if ($tokenImageMatch && $token->file != $tokenImageMatch->file) {
                $newPreviewUpload = $tokenImageMatch->preview_upload->replicate();
                $newUploadFile = $tokenImageMatch->upload_file->replicate();
                $newPreviewUpload->save();
                $newUploadFile->save();
                $token->preview_upload = $newPreviewUpload;
                $token->upload_file = $newUploadFile;
                $token->type = $tokenImageMatch->type;
                $this->logDebug('Ранее обнаружена загрузка данного изображения ',
                    ['originalUrl' => $originalUrl,
                        'tokenId' => $token->id,
                        'findTokenId' => $tokenImageMatch->id,
                    ]);
            } elseif ($ipfs && $ipfs == $token->file) {
                $this->logDebug('Картинки совпадают обновления не требуется',
                    [
                        'originalUrl' => $originalUrl,
                        'tokenId' => $token->id
                    ]);
            } else {
                Queue::push(UploadImage::class, ['tokenId' => $token->id, 'original_image_url' => $normalizedUrl]);
            }
            $token->file = $ipfs;
            $token->save();

            /** @var PushWebsocketService $pushWebsocketService */
            $pushWebsocketService = app(PushWebsocketService::class);
            $pushWebsocketService->pushToken($token);
        } else {
            $this->logDebug('Ошибка при обработке ссылки на изображение ', $originalUrl);
        }
    }

    /**
     * Обрабатывает изображение токена
     * @param UploadedFile $uploadFile
     * @param Token $token
     * @param CollectionController $resizerImage
     * @param ResizerGifAction $resizerGif
     * @param ResizerMp4 $resizerMp4
     * @return bool
     */
    function adaptationImage(
        UploadedFile         $uploadFile,
        Token                $token,
        CollectionController $resizerImage,
        ResizerGifAction     $resizerGif,
        ResizerMp4           $resizerMp4,
        UploadedFile         $preview = null
    ): bool
    {
        $mimeType = $uploadFile->getMimeType();
        $extension = $uploadFile->getClientOriginalExtension();

        try {
            $originalFile = clone $uploadFile;
            $token->upload_file = $originalFile;
            $token->type = $mimeType;

            if ($extension === 'mp4') {
                $data['type'] = "video";
                $resizedFile = $resizerMp4->resize(['file' => $preview ?? $uploadFile]);
                $token->preview_upload = $resizedFile;
            } elseif ($extension === 'gif') {
                $data['type'] = "gif";
                $token->preview_upload = $preview ?? $uploadFile;
                $resizerGif->resize($token->preview_upload->getPathname());
            } else {
                $data['type'] = "img";
                $token->preview_upload = $resizerImage->resizer($preview ?? $uploadFile, 370, 370);
            }
            $token->save();

            /** @var PushWebsocketService $pushWebsocketService */
            $pushWebsocketService = app(PushWebsocketService::class);
            $pushWebsocketService->pushToken($token);
            return true;
        } catch (Exception $e) {
            $this->logDebug('@adaptationImage', [
                'tokenId' => $token->id ?? null,
                'originalImageUrl' => $uploadFile->getClientOriginalName() ?? null,
                'type' => $data['type'] ?? null,
                'exception' => $e->getMessage(),
                'exceptionTrace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
