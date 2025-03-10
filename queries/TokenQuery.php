<?php

namespace Marketplace\Tokens\Queries;

use Auth;
use Db;
use Marketplace\Collections\Queries\CollectionQuery;
use Marketplace\Fiat\Queries\FiatQuery;
use Marketplace\Fiat\Queries\PaymentSystemSbpQuery;
use Marketplace\Moderationstatus\Models\ModerationStatus as ModStatus;
use Marketplace\Promo\queries\TokenPromoQuery;
use Marketplace\Traits\FullSearchPrepareParam;
use October\Rain\Database\Builder;

class TokenQuery extends Builder
{
    use FullSearchPrepareParam;

    function bonus(): self
    {
        $kefiriumCollectionId = env('COLLECTION_ID_KOTKEFIRIUM') ?: 0;
        return $this->whereRaw("marketplace_tokens_tokens.collection_id = $kefiriumCollectionId");
    }

    /**
     * Tokens with "Moderating" moderation status
     * @return self
     */
    function inModeration(): self
    {
        return $this->whereRaw('marketplace_tokens_tokens.moderation_status_id = ' . ModStatus::MODERATING_ID);
    }

    /**
     * Tokens with "Denied" moderation status
     * @param bool $isDenied
     * @return self
     */
    function deniedModeration(bool $isDenied = true): self
    {
        return $this->when($isDenied, function (self $q) {
            $q->whereRaw('marketplace_tokens_tokens.moderation_status_id = ' . ModStatus::DENIED_ID);
        }, function (self $q) {
            $q->whereRaw('marketplace_tokens_tokens.moderation_status_id != ' . ModStatus::DENIED_ID);
        });
    }

    /**
     * Tokens with "Moderated" moderation status
     * @param bool $isModerated
     * @return self
     */
    function moderated(bool $isModerated = true): self
    {
        return $this->when($isModerated, function (self $q) {
            $q->whereRaw('marketplace_tokens_tokens.moderation_status_id = ' . ModStatus::MODERATED_ID);
        }, function (self $q) {
            $q->whereRaw('marketplace_tokens_tokens.moderation_status_id != ' . ModStatus::MODERATED_ID);
        });
    }

    function promotedToBanner(): self
    {
        return $this->whereHas('promos', function ($q) {
            /** @var TokenPromoQuery $q */
            $q->banner();
        });
    }

    function promotedToNewest(): self
    {
        return $this->whereHas('promos', function ($q) {
            /** @var TokenPromoQuery $q */
            $q->newest();
        });
    }

    /**
     * With relations needed to show in main page
     *
     * @return self
     */
    function representative(): self
    {
        return $this->moderated()
            ->hidden(false)
            ->whereHas('collection', function ($q) {
                /** @var CollectionQuery $q */
                $q->moderated();
            })
            ->with([
                'author' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
                'blockchaintoken' => function ($q) {
                    $q->whereNull('blockchain_id')
                        ->with('blockchain.logo');
                },
                'collection',
                'galleries',
                'moderation_status',
                'user' => function ($q) {
                    $q->with(['legal', 'avatar']);
                },
            ])
            ->withCount('favorites');
    }

    /**
     * Search using optimized psql search functions
     * @param string $search
     * @return self
     */
    function fullTextSearch(string $search): self
    {
        $prepareSearch = $this->prepareSearchGlobal($search);

        $this->select('marketplace_tokens_tokens.*', DB::raw(
            "(ts_rank_cd(search_by_name_description, websearch_to_tsquery('russian', '{$search}'), 2|4)
        + ts_rank_cd(search_by_name_description, to_tsquery('russian', '{$prepareSearch}'), 2|4)) AS rank_total"
        ))
            ->whereRaw(
                "(search_by_name_description @@ websearch_to_tsquery('russian', " . DB::getPdo()->quote($search) . ")
        OR search_by_name_description @@ to_tsquery('russian', " . DB::getPdo()->quote($prepareSearch) . "))"
            )
            ->orderByRaw('rank_total DESC, name ASC');

        return $this;
    }

    /**
     * Show only moderated tokens to other people and all except denied - for owner
     * @param int|null $ofUserId
     * @return self
     */
    function moderationAccessChecked(?int $ofUserId): self
    {
        // todo проверить, допускать ли null для $ofUserId
        if (Auth::id() && Auth::id() == $ofUserId) {
            return $this->whereRaw(
                'marketplace_tokens_tokens.moderation_status_id != ' . ModStatus::DENIED_ID
            );
        } else {
            return $this->moderated();
        }
    }

    /**
     * Tokens exists only in kefir
     * @return self
     */
    function internal(): self
    {
        return $this->whereNull(DB::raw('marketplace_tokens_tokens.external_id'))
            ->whereNull(DB::raw('marketplace_tokens_tokens.external_address'));
    }

    /**
     * Tokens exists in external blockchain
     * @return self
     */
    function polygon(): self
    {
        return $this->whereNotNull(DB::raw('marketplace_tokens_tokens.external_id'))
            ->whereNotNull(DB::raw('marketplace_tokens_tokens.external_address'));
    }

    /**
     * Exported tokens which are not for sale on kefirium anymore
     * @param bool $isExported
     * @return self
     */
    function exported(bool $isExported = true): self
    {
        return $this->when($isExported, function (self $q) {
            $q->whereHas('blockchaintoken');
        }, function (self $q) {
            $q->whereDoesntHave('blockchaintoken');
        });
    }

    /**
     * Фильтр токенов по сетям
     * @param array $nets
     * @return self
     */
    function filterByNets(array $nets): self
    {
        $filterByNetFn = function (self $q, string $net, bool $or = false): self {
            switch ($net) {
                case 'polygon':
                    if ($or) {
                        return $q->orWhere(function (TokenQuery $q) {
                            $q->polygon();
                        });
                    } else {
                        return $q->polygon();
                    }
                case 'kefir':
                    if ($or) {
                        return $q->orWhere(function (TokenQuery $q) {
                            return $q->internal();
                        });
                    } else {
                        return $q->internal();
                    }
                default:
                    return $q;
            }
        };

        return $this->where(function ($q) use ($filterByNetFn, $nets) {
            $q->where(function ($q) use ($nets, $filterByNetFn) {
                foreach (array_values($nets) as $i => $net) {
                    if ($i == 0) {
                        // на первом заходе - использовать where, чтобы фильтр вообще имел смысл
                        $filterByNetFn($q, $net);
                    } else {
                        // на втором - использовать orWhere
                        $filterByNetFn($q, $net, true);
                    }
                }
            });
        });
    }

    // todo use instead of hiddenChecked & moderationAccessChecked
    function accessibleByAuthUser(int $tokensOfUserId): self
    {
        return $this->hiddenChecked($tokensOfUserId)
            ->moderationAccessChecked($tokensOfUserId);
    }

    /**
     * Hidden tokens
     * @param bool $isHidden
     * @return self
     */
    function hidden(bool $isHidden = true): self
    {
        return $this->whereRaw('marketplace_tokens_tokens.is_hidden = ' . ($isHidden ? 'true' : 'false'));
    }

    /**
     * Don't show hidden tokens to others
     * @param int $ofUserId
     * @return self
     */
    function hiddenChecked(int $ofUserId): self
    {
        return $this->when(Auth::id() !== $ofUserId, function (self $q) {
            $q->hidden(false);
        });
    }

    /**
     * Search by part of name
     * @param string $searchStr
     * @return self
     */
    function searchByName(string $searchStr): self
    {
        return $this->where(DB::raw('marketplace_tokens_tokens.name'), 'ilike', "%$searchStr%");
    }

    /**
     * Favorite tokens of given user
     * @param int|null $userId
     * @return self
     */
    function favoriteOf(?int $userId): self
    {
        if (!$userId) return $this;

        return $this->whereHas('favorites', function ($q) use ($userId) {
            $q->typeTokens()->whereRaw("marketplace_favorites_.user_id = $userId");
        });
    }

    /**
     * Tokens with given author
     * @param int $userId
     * @param bool $isAuthor
     * @return self
     */
    function ofAuthor(int $userId, bool $isAuthor = true): self
    {
        $sign = $isAuthor ? '=' : '!=';
        return $this->whereRaw("marketplace_tokens_tokens.author_id $sign $userId");
    }

    /**
     * Tokens owned by user
     * @param int $userId
     * @return self
     */
    function ofUser(int $userId): self
    {
        return $this->whereRaw("marketplace_tokens_tokens.user_id = $userId");
    }

    /**
     * Tokens on sale
     * @param bool
     * @return self
     */
    function onSale(bool $onSale = true): self
    {
        return $this->where(
            DB::raw('marketplace_tokens_tokens.is_sale'),
            $onSale ? '=' : '!=',
            true
        );
    }

    /**
     * Tokens of given collections
     * @param array $collectionsIds
     * @param bool $orderedByCollection
     * @return self
     */
    function ofCollections(array $collectionsIds, bool $orderedByCollection = false): self
    {
        return $this->whereIn(DB::raw('marketplace_tokens_tokens.collection_id'), $collectionsIds)
            ->when($orderedByCollection, function ($q) {
                $q->orderByRaw('marketplace_tokens_tokens.collection_id');
            });
    }

    function withPaymentSystemsStatuses(): self
    {
        return $this->withCount([
            'sbp as sbp' => function ($q) {
                /** @var PaymentSystemSbpQuery $q */
                $q->active()->limit(1);
            },
            'wallet as wallet' => function ($q) {
                /** @var FiatQuery $q */
                $q->active()->limit(1);
            },
        ]);
    }

    /**
     * Tokens ordered by last payment
     * @param string $direction
     * @return self
     */
    function orderedByLastPayment(string $direction = 'desc'): self
    {
        return $this->leftJoin(
            'marketplace_payments_',
            'marketplace_tokens_tokens.id',
            '=',
            'marketplace_payments_.token_id'
        )
            ->addSelect(
                'marketplace_tokens_tokens.*',
                DB::raw('MAX(marketplace_payments_.created_at) as max_created_at')
            )
            ->groupBy('marketplace_tokens_tokens.id')
            ->orderByRaw("MAX(marketplace_payments_.created_at) $direction NULLS LAST");
    }
}
