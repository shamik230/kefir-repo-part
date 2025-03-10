<?php

namespace Marketplace\Tokens\Models;

use Auth;
use BackendAuth;
use Cache;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Log;
use Marketplace\Bearer\Facades\BearerAuthFacade;
use Marketplace\Blockchaintokens\Models\BlockchainToken;
use Marketplace\Collections\Actions\UpdateCollectionSumAction;
use Marketplace\Collections\Controllers\CollectionController;
use Marketplace\Collections\Models\Collection;
use Marketplace\Factory\Models\Factory;
use Marketplace\Favorites\Models\Favorite;
use Marketplace\Fiat\Models\Fiat;
use Marketplace\Fiat\Models\PaymentSystemSBP;
use Marketplace\Galleries\Models\Gallery;
use Marketplace\KefiriumCashback\Models\KefiriumCashback;
use Marketplace\Lots\models\Lot;
use Marketplace\Moderation\Models\Moderation;
use Marketplace\Moderationhistory\Models\ModeartionHistory;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Payments\Models\Payment;
use Marketplace\Promo\Models\TokenPromo;
use Marketplace\ReasonsRejection\Models\ReasonsRejection;
use Marketplace\Module\Models\Module;
use Marketplace\Tokens\Controllers\TokenController;
use Marketplace\Tokens\Queries\TokenQuery;
use Marketplace\TokenVerification\Models\TokenVerification;
use Marketplace\Traits\SendEmail;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactions\Models\TransactionSettings;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use Model;
use October\Rain\Argon\Argon;
use October\Rain\Database\Relations\AttachOne;
use October\Rain\Database\Relations\BelongsTo;
use October\Rain\Database\Relations\BelongsToMany;
use October\Rain\Database\Relations\HasMany;
use October\Rain\Database\Relations\HasOne;
use October\Rain\Database\Relations\HasOneThrough;
use October\Rain\Database\Relations\MorphToMany;
use October\Rain\Database\Traits\SoftDelete;
use Project\Traits\DeleteWithMe;
use RainLab\User\Classes\UpdateTokenEvent;
use RainLab\User\Models\User;
use Session;
use Str;
use System\Models\File;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string $external_address
 * @property string $transaction_hash
 * @property Argon $created_at
 * @property Argon $updated_at
 * @property Argon|null $pending_at
 * @property $user_id
 * @property $collection_id
 * @property $file
 * @property $currency
 * @property $name
 * @property $description
 * @property $external_reference
 * @property float $price
 * @property $royalty
 * @property $hidden
 * @property $type
 * @property $author_id
 * @property bool $is_hidden
 * @property $is_sale
 * @property File $preview
 * @property $in_progress
 * @property $is_booked
 * @property $moderation_status_id
 * @property $modarated_at
 * @property $comment
 * @property $reasons_rejection_id
 * @property $secret_key
 * @property $deleted_at
 * @property $content_on_redemption
 * @property $is_utilitarian
 * @property $is_produced
 * @property float|null $buyer_percent Buyer cashback percent
 * @property float|null $seller_percent Seller cashback percent
 *
 * @property-read float $buy_percent Вычисляемое св-во, возвращает процент из настроек в отсутствие у токена
 * @property-read float $sale_percent Вычисляемое св-во, возвращает процент из настроек в отсутствие у токена
 * @property-read string $link
 *
 * @property User $author
 * @property BlockchainToken $blockchaintoken
 * @property KefiriumCashback $cashback
 * @property Collection $collection
 * @property \October\Rain\Database\Collection<Favorite> $favorites
 * @property \October\Rain\Database\Collection<Gallery> $galleries
 * @property \October\Rain\Database\Collection<TokenPromo> $promos
 * @property \October\Rain\Database\Collection<Payment> $payments
 * @property File $preview_upload
 * @property \October\Rain\Database\Collection<Transaction> $transactions
 * @property File $upload_file
 * @property User $user
 * @property PaymentSystemSBP|null $sbp
 * @property Fiat|null $wallet
 *
 * @method BelongsTo collection()
 * @method BelongsTo author()
 * @method HasMany cashback()
 * @method AttachOne upload_file()
 * @method AttachOne preview_upload()
 * @method MorphToMany favorites()
 * @method BelongsToMany galleries()
 * @method HasMany payments()
 * @method HasMany promos()
 * @method HasOne blockchaintoken()
 * @method HasMany transactions()
 * @method HasOneThrough sbp()
 * @method HasOneThrough wallet()
 *
 * // todo перенести скоупы в TokenQuery
 * @method static TokenQuery|Token hiddenTokensCollection(int $collectionId)
 * @method static TokenQuery|Token Hidden(int $userId) Scope
 * @method static TokenQuery|Token Verification(int $userId) Scope
 * @method static TokenQuery|Token tokenItem(int $userId) Scope
 * @method static TokenQuery|Token queryToken(string $search) Scope
 * @method static TokenQuery|Token tokenIsSale(bool $isSale) Scope
 * @method static TokenQuery|Token filterTokens(string $filterAS) Scope
 *
 * @mixin TokenQuery
 * @method static TokenQuery|Token query()
 */
class Token extends Model
{
    use SoftDelete, DeleteWithMe, SendEmail;

    const COMMISSION_FOR_TRANSFER = 0;
    public $table = 'marketplace_tokens_tokens';

    protected $dates = ['deleted_at'];

    protected $hidden = [
        'content_on_redemption',
        'search_by_name_description',
        'hidden_comment'
    ];

    public $attributes = [
        'moderation_status_id' => ModerationStatus::MODERATING_ID,
        'is_sale' => false,
    ];

    protected $appends = [
        'is_favorited',
        "galleries_full",
        "original_file",
        "moderation_image",
        'commission_percent',
        'sale_percent',
        'buy_percent',
        'link',
        'name_id',
    ];

    protected $casts = [
        'external_id' => 'string',
    ];

    protected $fillable = [
        'name',
        'description',
        'file',
        'collection_id',
        'royalty',
        'price',
        'hidden',
        'user_id',
        'type',
        'author',
        'contract',
        'is_hidden',
        'is_sale',
        'external_reference',
        'is_booked',
        'mint',
        'moderation_status_id',
        'content_on_redemption',
        'is_utilitarian',
        'is_produced',
        'external_address',
        'external_id',
        'transaction_hash',
        'pending_at',
        'modarated_at',
        'buyer_percent',
        'seller_percent',
        'currency',
        'token_type_id',
        'barrel_volume',
        'ipfs_json_uri',
        'ipfs_img_uri',
        'file_path'
    ];

    public $belongsTo = [
        'user' => User::class,
        'collection' => Collection::class,
        'author' => User::class,
        'moderation_status' => ModerationStatus::class,
        'reasons_rejection' => ReasonsRejection::class,
        'token_type' => TokenType::class,
    ];

    public $attachOne = [
        'upload_file' => [\System\Models\File::class, 'delete' => false],
        'preview_upload' => [\System\Models\File::class, 'delete' => false],
        'import_file' => \System\Models\File::class,
    ];

    public $hasOne = [
        'blockchaintoken' => BlockchainToken::class,
        'module' => Module::class,
    ];

    public $hasOneThrough = [
        'wallet' => [
            Fiat::class,
            'through' => User::class,
            'key' => 'id',
            'throughKey' => 'user_id',
            'otherKey' => 'user_id',
            'secondOtherKey' => 'id',
        ],
        'sbp' => [
            PaymentSystemSBP::class,
            'through' => User::class,
            'key' => 'id',
            'throughKey' => 'user_id',
            'otherKey' => 'user_id',
            'secondOtherKey' => 'id',
        ],
        'factory' => [
            Factory::class,
            'through' => Module::class,
            'key' => 'token_id',
            'throughKey' => 'id',
            'otherKey' => 'id',
            'secondOtherKey' => 'factory_id',
        ],
    ];
    public $belongsToMany = [
        'galleries' => [Gallery::class, 'table' => 'marketplace_galleries_gallery_tokens'],
        'lots' => [Lot::class, 'table' => 'marketplace_lot_token'],
    ];

    public $hasMany = [
        'cashback' => KefiriumCashback::class,
        'promos' => TokenPromo::class,
        'payments' => [Payment::class, 'softDelete' => true],
        'transactions' => [Transaction::class, 'softDelete' => true],
        'token_verifacation' => TokenVerification::class,
        'moderation_history' => [
            ModeartionHistory::class,
            'order' => 'created_at desc'
        ],
    ];

    public $morphTo = [
        'tokenable' => []
    ];

    public $morphOne = [];

    public $morphToMany = [
        'favorites' => [
            Favorite::class,
            'name' => 'favoritable',
            'table' => 'marketplace_favoritables_',
        ],
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function (self $token) {
            if ($token->wasChanged('is_hidden') || $token->wasChanged('is_sale')) {
                (new UpdateCollectionSumAction)->execute($token->collection);
            }
        });
        static::addGlobalScope('CollectionRejected', function (Builder $builder) {
            $builder->whereHas('collection', function ($q) {
                $q->where('moderation_status_id', '!=', ModerationStatus::DENIED_ID);
            });
        });
    }

    public function getAttributes()
    {
        $attributes = parent::getAttributes();

        if ($attributes['moderation_status_id'] == ModerationStatus::MODERATING_ID) {
            $attributes['preview'] = url(Moderation::IN_MODERATION_IMG_URL);
        }

        return $attributes;
    }

    public function toArray()
    {
        $array = parent::toArray();

        // Проверяем условие: если moderation_status_id равен 1, устанавливаем другое значение для preview
        if ($this->attributes['moderation_status_id'] == ModerationStatus::MODERATING_ID) {
            $array['preview'] = url(Moderation::IN_MODERATION_IMG_URL);
            $array['file'] = url(Moderation::IN_MODERATION_IMG_URL);
        }

        return $array;
    }

    // Getters & Setters

    function getLinkAttribute(): string
    {
        return url("/collection/token/$this->id");
    }

    function getNameIdAttribute(): string
    {
        return "$this->name ($this->id)";
    }

    function setBuyerPercentAttribute(?float $value): void
    {
        if (is_null($value)) {
            $this->attributes['buyer_percent'] = null;
        } else {
            $this->attributes['buyer_percent'] = round($value, 2);
        }
    }

    function getBuyPercentAttribute(): float
    {
        return $this->buyer_percent ?: TransactionSettings::buyerPercent();
    }

    function setSellerPercentAttribute(?float $value): void
    {
        if (is_null($value)) {
            $this->attributes['seller_percent'] = null;
        } else {
            $this->attributes['seller_percent'] = round($value, 2);
        }
    }

    /**
     * Возвращает отфильтрованные токены для лота.
     *
     * @return array
     */
    public static function getFilteredLotTokens()
    {
        return self::whereDoesntHave('transactions', function ($q) {
            $q->where('status', Transaction::STATUS_PENDING);
        })
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->id => "{$item->name} ({$item->id})"];
            })
            ->toArray();
    }

    function getSalePercentAttribute(): float
    {
        return $this->seller_percent ?: TransactionSettings::sellerPercent();
    }

    function getIsDefaultPercentsAttribute(): bool
    {
        return is_null($this->attributes['buyer_percent'] ?? null)
            && is_null($this->attributes['seller_percent'] ?? null);
    }

    function getCommissionPercentAttribute(): float
    {
        return (float)config('kefir.commission_percent', 0);
    }

    function getBonusPercentAttribute(): float
    {
        return (float)config('kefir.kefirium_bonus_percent', 0);
    }

    public function getOriginalFileAttribute()
    {
        if ($this->isModerated()) {
            if ($fileUrl = $this->attributes['file']) {
                return stripos($fileUrl, 'ipfs')
                    ? 'https://gateway.pinata.cloud' . $fileUrl
                    : 'https://gateway.pinata.cloud/ipfs/' . $fileUrl;
            } else {
                return is_object($this->upload_file) ? $this->upload_file->path : null;
            }
        } elseif ($this->inModeration()) {
            return url(Moderation::IN_MODERATION_IMG_URL);
        } else/*if ($this->isDeniedModeration())*/ {
            return url(Moderation::MOD_REJECTED_IMG_URL);
        }
    }

    public function getSecretKeyAttribute($value)
    {
        if (BearerAuthFacade::isAuthenticated()) {
            return $value;
        }
        if ((BackendAuth::getUser() && BackendAuth::getUser()->role_id != 3) || (Auth::check() && Auth::user()->id === $this->user_id)) {
            return $value;
        }
        return null;
    }

    public function getNotPAymentAttribute($value)
    {
        if (!Payment::where('token_id', $this->id)->where('type', Payment::TYPE_SALE)->exists()) {
            return true;
        }
        return false;
    }

    /**
     * @param int $value
     * @return int
     */
    protected function getCollectionIdAttribute($value)
    {
        return $value;
    }

    /**
     * @param int $value
     * @return int
     */
    protected function getAuthorIdAttribute($value)
    {
        return $value;
    }

    /**
     * @return int
     */
    public function getCollectionId()
    {
        return $this->collection_id;
    }

    /**
     * @return int
     */
    public function getAuthorId()
    {
        return $this->author_id;
    }
    function getFileAttribute($value)
    {
        if ($this->inModeration() && $this->user_id && $this->user_id != Auth::id()) {
            $this->preview = null;
            return null;
        }

        if ($this->upload_file && !$this->type) {
            $this->update(['type' => $this->upload_file->content_type]);
        }

        if (!$value && $this->upload_file && $this->type) {
            $value = $this->upload_file->path;
        }

        if ($this->attributes['moderation_status_id'] == ModerationStatus::MODERATING_ID) {
            return url(Moderation::IN_MODERATION_IMG_URL);
        }

        return $value;
    }

    public function setPriceAttribute($value)
    {
        $numericValue = (int)ltrim($value, '0');
        $this->attributes['price'] = max($numericValue, 100);
    }

    public function getModerationImageAttribute()
    {

        if ($this->moderation_status_id == 1) {
            return url(Moderation::IN_MODERATION_IMG_URL);
        }
        // if($this->moderation_status_id == 2){
        //     $token = Moderation::where('id',$this->id)->first();


        //     if($this->moderation_status_id == 2 && $token->upload_file && $token->preview_upload){
        //         $token->fileNew($this,'file');
        //         $token->fileNew($this,'preview');

        //     }elseif($this->moderation_status_id == 2 ){
        //         $token->upload_file->file_name != $this->upload_file->file_name ? $token->fileNew($this,'file') : "";
        //         $token->preview_upload->file_name != $this->preview_upload->file_name ? $token->fileNew($this,'preview') : "";
        //     }

        // }

    }

    public function getHiddenAttribute($value)
    {

        if ((BackendAuth::getUser() && BackendAuth::getUser()->role_id != 3) || (Auth::check() && $this->attributes['user_id'] == Auth::user()->id)) {

            return $value;
        } elseif ($value) {
            return false;
        }
    }

    function scopeHidden($query, $id)
    {
        if (Auth::check() && Auth::id() != $id) {
            return $query->where('is_hidden', false)->where('moderation_status_id', 3);
        } elseif (!Auth::check()) {
            return $query->where('is_hidden', false)->where('moderation_status_id', 3);
        }

        return $query->where('moderation_status_id', '!=', 2);
    }

    public function scopeVerification($query, $id)
    {
        if (Auth::check() && Token::where('author_id', Auth::id())->exists()) {
            return $query->withCount('token_verifacation');
        }
    }

    public function scopeTokenItem($query, $id)
    {
        if (!Auth::check() || !Token::where('id', $id)->where('user_id', Auth::user()->id)->exists()) {
            return $query->where('is_hidden', false);
        }
    }

    function scopeTokenIsSale($query, $isSale)
    {
        if ($isSale) {
            $query->where('is_sale', true);
        }
    }

    public function scopeHiddenTokensCollection($q, $collectionId)
    {
        $isBelongsToCurrentUser = Auth::check()
            && Collection::query()
            ->where('user_id', Auth::id())
            ->where('id', $collectionId)
            ->exists();
        if ($isBelongsToCurrentUser) {
            $q->where('collection_id', $collectionId)->where('moderation_status_id', '!=', 2);
        } else {
            $q->where('is_hidden', false)->where('moderation_status_id', 3);
        }
    }

    public function scopeQueryToken($query, $string)
    {
        $query->where('name', 'ilike', '%' . $string . '%');
    }

    public function scopeFilterTokens($query, $string)
    {
        switch ($string) {
            case "price_asc":
                $query->orderBy('price', 'ASC')->orderBy('id');
                break;
            case "price_desc":
                $query->orderBy('price', 'DESC')->orderBy('id');
                break;
            case "price_desc_sale":
                $query->where('is_sale', true)->orderBy('price', 'DESC')->orderBy('id');
                break;
            case 'created_at':
                $query->orderBy('created_at', 'DESC');
                break;
            case 'created_at_sale':
                $query->where('is_sale', true)->orderBy('created_at', 'DESC');
                break;
            case 'payment_date':
                $query->with([
                    'payments' => function ($query) {
                        $query->orderBy('created_at');
                    },
                ]);
                break;
            case 'payment_date_sale':
                $query->where('is_sale', true)->with([
                    'payments' => function ($query) {
                        $query->orderBy('created_at');
                    },
                ]);
                break;
            case 'name':
                $query->orderBy('name', 'ASC');
                break;
            case 'name_sale':
                $query->where('is_sale', true)->orderBy('name', 'ASC');
                break;
            case "price_asc_sale":
                $query->where('is_sale', true)->orderBy('price', 'ASC')->orderBy('id');
                break;
            default:
                $query->orderBy('is_sale', 'desc')->orderBy('id');
        }
    }

    public function scopeHiddenTokensGallery($query, $id)
    {
        if (!Auth::check() || Auth::user()->id !== Gallery::where('id', $id)->first()['user_id']) {
            // $query->where('is_hidden', false)->where('is_sale', true)->where('moderation_status_id', 3);
            $query->where('is_hidden', false)->where('moderation_status_id', 3);
            // $tokens = DB::table('marketplace_galleries_gallery_tokens')->where('gallery_id', $id)->pluck('token_id');
            // Token::whereIN('id', $tokens)->where('user_id', Auth::user()->id)->first() ?: $query->where('is_hidden', false)->where('is_sale', true)->where('moderation_status_id', 3);
        } else {
            $query->where('moderation_status_id', 3);
        }
    }
    // public function scopeArsd($query){
    //     $this->test = "asdad";
    //     $query->where('is_hidden', false);

    // }

    /**
     * @return string|null
     */
    public function getSecretKey()
    {
        return $this->secret_key;
    }

    function getPreviewAttribute($value)
    {
        if (!\App::runningInBackend()) { // check
            if ($this->inModeration()) {
                return url(Moderation::IN_MODERATION_IMG_URL);
            } elseif ($this->isDeniedModeration()) {
                return url(Moderation::MOD_REJECTED_IMG_URL);
            }
        }

        if ($this->preview_upload && $this->preview_upload->extension == 'gif') {
            if (file_exists(str_replace(".gif", ".webp", $this->preview_upload->getLocalPath()))) {
                return str_replace(".gif", ".webp", $this->preview_upload->path);
            } else {
                return $this->preview_upload->path;
            }
        }

        if ($this->preview_upload && $this->preview_upload->extension == 'webp') {
            return $this->preview_upload->path;
        }

        if (!$value && $this->preview_upload) {
            $this->preview = $this->preview_upload->getThumb(370, 370, ['mode' => 'crop']);
            $this->save();
        }

        if ($value && $this->preview_upload) {
            return $this->preview_upload->getThumb(370, 370, ['mode' => 'crop']);
        }

        if ((new TokenController)->checkType($this->type)) {
            return $this->file;
        }

        return $value;
    }

    public function getGalleriesFullAttribute($value)
    {
        if (Auth::check()) {

            $tokenGallery = DB::table('marketplace_galleries_gallery_tokens')->where('token_id', $this->id)->pluck('gallery_id')->toArray();
            $gallery = Gallery::where('user_id', Auth::user()->id)->get()->map(function ($value, $key) use ($tokenGallery) {
                if (in_array($value['id'], $tokenGallery)) {
                    $value['added'] = true;
                } else {
                    $value['added'] = false;
                }


                return $value;
            })->toArray();

            usort($gallery, function ($a, $b) {
                return ($b['added'] - $a['added']);
            });

            return $gallery;
        }
        return null;
    }

    public function getTypeAttribute($value)
    {
        // if(!$value){
        //     $file = new File;
        //     $file->fromUrl("https://kefirus.tk/storage/app/uploads/public/625/d1d/7e3/625d1d7e333ab703363253.png");
        //     $this->type =mime_content_type($file);
        //     $this->save();
        // }
        return $value;
    }

    function getIsBookedAttribute($value): bool
    {
        if ($value) {
            $value_booked = Cache::get('token_process_new' . $this->attributes['id']);
            if ($value_booked) {
                return $value;
            } else {
                self::query()->where('id', $this->id)->update(['is_booked' => false]);
                $this->attributes['is_booked'] = false;
            }
            return false;
        }
        return false;
    }

    // fixme нельзя так жить...
    function getIsFavoritedAttribute($value): bool
    {
        return Favorite::query()
            ->where('user_id', Auth::id() ?: 0)
            ->whereHas('tokens', function ($q) {
                $q->whereRaw('marketplace_tokens_tokens.id = ' . ($this->id ?: 0));
            })
            ->exists();
    }


    // Events

    function beforeCreate()
    {
        if (strpos(request()->url(), 'backend') !== false || $this->collection_id == env('COLLECTION_ID_KOTKEFIRIUM')) {
            $this->moderation_status_id = ModerationStatus::MODERATED_ID;
            $this->modarated_at = now();
        }
    }

    public function afterCreate()
    {
        // Barrel Token
        if ($this->token_type_id === TokenType::BARREL_TOKEN) {
            return;
        }

        // Default Token
        // Collection KOTKEFIRIUM
        if (
            $this->collection_id == env('COLLECTION_ID_KOTKEFIRIUM')
            && $this->external_address == (env('KEFIRIUMKOT_ADDRESS'))
        ) {
            Transaction::query()->createSale(
                null,
                $this->user_id,
                null,
                $this->id,
                $this->price,
                $this->transaction_hash ?: null,
                null,
                null,
                $this->currency ?? Transaction::CURRENCY_RUR_TYPE
            );
        }

        // Get transaction by hash
        $repeatTransaction = Transaction::where('transaction_hash', $this->transaction_hash)
            ->where('transaction_types_id', TransactionTypes::TYPE_MINT)
            ->whereDoesntHave('token')
            ->first();

        if (!$repeatTransaction) {
            Transaction::create([
                'from_user_id' => $this->user_id,
                'token_id' => $this->id,
                'transaction_types_id' => $this->external_address ? TransactionTypes::TYPE_IMPORT_TOKEN_ID : TransactionTypes::TYPE_CREATION_ID,
                'price' => $this->price,
                'status' => Transaction::STATUS_SUCCESS,
                'transaction_hash' => $this->transaction_hash ?: null,
                'currency' => $this->currency ?? Transaction::CURRENCY_RUR_TYPE
            ]);
        } else {
            $repeatTransaction->update([
                'status' => Transaction::STATUS_SUCCESS,
                'token_id' => $this->id,
                'transaction_types_id' => TransactionTypes::TYPE_IMPORT_TOKEN_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create sale transaction
        if ($this->is_sale) {
            Transaction::create([
                'from_user_id' => $this->user_id,
                'token_id' => $this->id,
                'transaction_types_id' => TransactionTypes::TYPE_EXPOSED_FOR_SALE_ID,
                'price' => $this->price,
                'transaction_hash' => $this->transaction_hash ?: null,
                'currency' => $this->currency ?? Transaction::CURRENCY_RUR_TYPE
            ]);
        }

        // Create moderation transaction
        Transaction::create([
            'from_user_id' => $this->user_id,
            'token_id' => $this->id,
            'transaction_types_id' => TransactionTypes::TYPE_IN_MODERATION_ID,
            'price' => $this->price,
            'currency' => $this->currency ?? Transaction::CURRENCY_RUR_TYPE
        ]);

        $this->cacheClear();

        $this->secret_key = Str::random(10);

        $this->save();

        // Update cache
        $collection = new CollectionController();
        $collection->collectionAmount($this->collection_id, $this->price);
        Cache::forget('collection_newstokenss_filterpageid' . $this->collection_id);
        Cache::forget('collection_newstokenss_' . 'id' . $this->collection_id);

        // Create history
        $data = $this->moderation_status_id == ModerationStatus::MODERATED_ID
            ? [
                'token_id' => $this->id,
                'moderator_id' => BackendAuth::id() ? BackendAuth::id() : 1,
                'moderation_status_id' => ModerationStatus::MODERATED_ID,
                'comment' => "Прошел проверку",
                'type' => 'Токен',
            ]
            : [
                'token_id' => $this->id,
                'moderation_status_id' => ModerationStatus::MODERATING_ID,
                'comment' => "На проверке",
                'type' => 'Токен',
            ];
        ModeartionHistory::create($data);
    }

    function beforeSave()
    {
        //        $tokenOld= Token::find($this->id)->refresh();
        //        $originalUploadFile = $tokenOld->upload_file->id;
        //        $originalPreviewUpload = $this->getOriginal('preview_upload');
        //
        //
        //        $newUploadFile = $this->upload_file;
        //        $newPreviewUpload = $this->preview_upload;
        //
        //        Log::info('старые ', ['orig'=>$originalUploadFile]);
        //        Log::info('старые ', ['new'=>$newUploadFile]);
        //
        //
        //        if ($this->exists) {
        //
        //            $this->originalPreviewUploadId = $this->upload_file;
        //            $this->originalUploadFileId = $this->preview_upload;
        //        }

        // <p>Первая строка</p><p>Строка на новой строке</p><p><br></p><p>Строка через два переноса</p><p>Многа &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; прабелав</p>
        if ($this->isDirty('description') && $this->description) {
            $cleanTextFn = function (string $text): string {
                if (!Str::startsWith($text, '<p>')) $text = '<p>' . $text;
                if (!Str::endsWith($text, '</p>')) $text .= '</p>';
                $text = strip_tags($text, '<p>');
                $text = preg_replace([
                    '/(\r\n)|\r|\n/', // Заменить новую строку на теги нового параграфа
                    '/(<\/p><p>){2,}/', // Заменить новую строку, повторяющуюся 2 и более раз, на два переноса строки
                    '/(\s|(&nbsp;)){2,}/', // Заменить много пробелов одним
                ], [
                    '</p><p>',
                    '</p><p>&nbsp;</p><p>',
                    ' ',
                ], $text);

                return $text;
            };

            $this->attributes['description'] = $cleanTextFn($this->description);
        }

        $tokenHasSales = Transaction::query()
            ->ofToken($this->id)
            ->whereNotNull('sale_completed_payment_id')
            ->exists();
        if (
            isset($this->attributes['is_default_percents'])
            && $this->attributes['is_default_percents']
            && !$tokenHasSales
        ) {
            $this->buyer_percent = $this->seller_percent = null;
        } elseif ($this->isDirty(['buyer_percent', 'seller_percent']) && $tokenHasSales) {
            // Если у токена уже были продажи - не трогать
            $this->buyer_percent = $this->getOriginal('buyer_percent');
            $this->seller_percent = $this->getOriginal('seller_percent');
        }
        unset($this->attributes['is_default_percents']);
    }

    public function afterSave() {}

    function beforeUpdate()
    {
        if ($this->isDirty('moderation_status_id')) {
            ModeartionHistory::create([
                'token_id' => $this->id,
                'moderator_id' => BackendAuth::getUser() ? BackendAuth::getUser()->id : 1,
                'moderation_status_id' => $this->moderation_status_id,
                'reasons_rejection_id' => $this->reasons_rejection_id,
                'comment' => $this->comment,
                'type' => 'Токен',
            ]);
            if ($this->isModerated()) {
                Transaction::query()->createModerated($this->user_id, $this->id, $this->price, $this->currency ?? Transaction::CURRENCY_RUR_TYPE);
            }
            if ($this->isDeniedModeration()) {
                Transaction::create([
                    'from_user_id' => $this->user_id,
                    'token_id' => $this->id,
                    'transaction_types_id' => TransactionTypes::TYPE_DENIED_BY_MODERATOR_ID,
                    'price' => $this->price,
                ]);
                $this->description = sprintf(
                    "Отклонено модератором %s %s %s %s\n\n$this->description",
                    BackendAuth::getUser()->first_name,
                    BackendAuth::getUser()->last_name,
                    now(),
                    $this->comment
                );
            }

            $this->modarated_at = now();
            if (BackendAuth::getUser() && BackendAuth::getUser()->role_id == 3) {
                $this->sendEmail(
                    'rainlab.token::token.maderation',
                    [
                        'status' => $this->moderation_status->name,
                        'comment' => $this->comment,
                        'date' => now(),
                        'name' => $this->name,
                        'link' => env('APP_URL') . '/collection/token/' . $this->id,
                    ],
                    $this->user->email,
                    'Moderation@BeforeUpdate',
                    true
                );
            }
        }

        if ($this->isDirty('user_id')) {
            $this->secret_key = Str::random(10);
        }
        if ($this->isDirty('is_sale')) {
            $this->cacheClear();
        }
        $this->cacheClearPaginate('tokens_users_id_pay' . $this->id);
    }

    public function cacheClear()
    {
        Cache::forget('collectionCounts' . $this->collection_id);
        Cache::forget('collectionCountsSales' . $this->collection_id);
        Cache::forget('tokens_users_id_pay' . $this->user_id);
        Cache::forget('tokens_author_id_pay' . $this->user_id);
        $this->cacheClearPaginate('collection_user_profile' . $this->user_id);
        $this->cacheClearPaginate('tokens_users_id_pay' . $this->user_id);
        $this->cacheClearPaginate('tokens_author_id_pay' . $this->author_id);
    }

    private function cacheClearPaginate($prefix)
    {
        for ($i = 1; $i < 100; $i++) {
            $key = $prefix . $i;
            if (Cache::has($key)) {
                Cache::forget($key);
            } else {
                break;
            }
        }
    }

    function beforeDelete()
    {
        $this->removeMyDescendants([
            'upload_file',
            'preview_upload',
            'import_file',
            'blockchaintoken',
            'transactions',
            'payments',
            'token_verifacation',
        ], [
            'galleries',
            'favorites',
        ]);
    }

    // Methods

    function inModeration(): bool
    {
        return $this->moderation_status_id == ModerationStatus::MODERATING_ID;
    }

    function isModerated(): bool
    {
        return $this->moderation_status_id == ModerationStatus::MODERATED_ID;
    }

    function isDeniedModeration(): bool
    {
        return $this->moderation_status_id == ModerationStatus::DENIED_ID;
    }

    // Misc

    function filterFields($fields, $context = null)
    {
        $isTokenUpdateUrl = Str::contains(request()->url(), 'token/update');
        if ($isTokenUpdateUrl && $context == 'update' && $this->cashback()->exists()) {
            $fields->buyer_percent->readOnly = 1;
            $fields->seller_percent->readOnly = 1;
            $fields->is_default_percents->readOnly = 1;
        }
    }


    function newEloquentBuilder($query): TokenQuery
    {
        return new TokenQuery($query);
    }
}
