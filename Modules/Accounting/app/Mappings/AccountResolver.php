<?php

namespace Modules\Accounting\Mappings;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Finds the account for a purpose, e.g. resolve('sales.revenue', [$product, $category]).
 * Scopes are tried in the given order (most specific first), then the default mapping.
 */
#[ModuleApi]
class AccountResolver
{
    /** @var array<string, Account> */
    private array $cache = [];

    /**
     * @param  array<int, Model|null>  $scopes
     */
    public function resolve(string $key, array $scopes = []): Account
    {
        $scopes = array_values(array_filter($scopes));
        $cacheKey = $key.'|'.implode(',', array_map(fn (Model $m) => $m->getMorphClass().':'.$m->getKey(), $scopes));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $mappings = AccountMapping::with('account')
            ->where('key', $key)
            ->where(function ($query) use ($scopes) {
                $query->whereNull('scope_type');
                foreach ($scopes as $scope) {
                    $query->orWhere(fn ($q) => $q->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey()));
                }
            })
            ->get();

        foreach ($scopes as $scope) {
            $match = $mappings->first(fn (AccountMapping $m) => $m->scope_type === $scope->getMorphClass() && $m->scope_id === $scope->getKey());

            if ($match) {
                return $this->cache[$cacheKey] = $match->account;
            }
        }

        $default = $mappings->firstWhere('scope_type', null)
            ?? throw PostingException::because('missing_mapping', ['key' => $key]);

        return $this->cache[$cacheKey] = $default->account;
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
