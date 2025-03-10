<?php

namespace Marketplace\Tokens\Actions;

use Auth;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Tokens\Jobs\ResizerGif;
use Marketplace\Tokens\Jobs\ResizerMp4;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Services\TokenImageService;
use Marketplace\TokensToImport\Models\TokenToImport;
use Marketplace\Traits\Logger;
use Marketplace\Transactions\Models\Transaction;
use Queue;
use System\Models\File;
use Throwable;

class CreateTokenFromTokenToImportAction
{
    use Logger;

    /** @var CollectionController */
    protected $resizer;

    function __construct(CollectionController $collectionController)
    {
        $this->resizer = $collectionController;
    }

    function execute(
        TokenToImport $tti,
        int           $collectionId,
        ?string       $transactionHash,
        int           $tokenPrice = 100,
        bool          $isSale = true,
        bool   $moderated = false,
        string $currency = Transaction::CURRENCY_RUR_TYPE
    ): Token
    {
        if ($tti->mime) {
            $mimeType = $tti->mime;
        } else {
            $mimeType = get_headers($tti->preview_url, true)['Content-Type']; // fixme если запрос не пройдёт, импорт сломается
        }
        $this->logDebug('CreateTokenFromTokenToImportAction', [
            'tti' => $tti,
            'args' => compact('collectionId', 'transactionHash', 'tokenPrice', 'isSale', 'moderated'),
        ]);
        $extension = explode('/', $mimeType)[1];
        $file = (new File)->fromUrl($tti->preview_url, "$tti->id.$extension");
        $uploadFile = (new UploadedFile($file->getLocalPath(), $file->getFilename()));

        try {
            DB::beginTransaction();

            $token = new Token;
            $token->external_id = $tti->external_id;
            $token->external_address = $tti->external_address;
            $token->transaction_hash = $transactionHash;
            $token->user_id = $token->author_id = $tti->user_id;
            $token->collection_id = $collectionId;
            $token->file = Str::afterLast($tti->ipfs_url, '//'); // link without ipfs://
            $token->name = $tti->name;
            $token->description = $tti->description;
            $token->price = $tokenPrice;
            $token->type = $mimeType;
            $token->is_hidden = false;
            $token->is_sale = $isSale;
            $token->currency = $currency;
            $token->moderation_status_id = $moderated ? ModerationStatus::MODERATED_ID : ModerationStatus::MODERATING_ID;
            $token->external_id = $tti->external_id;
            $token->royalty = 0;
            $token->external_address = $tti->external_address;
            $token->save();
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
                throw new Exception("Image adaptation failed for token ID: {$token->id}");
            }

            $tti->update(['existing_token_id' => $token->id, 'existing_token_user_id' => $token->user_id]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->logDebug('CreateTokenFromTokenToImportAction token created', $token);
        return $token;
    }
}
