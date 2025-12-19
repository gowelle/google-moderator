<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Blocklists;

use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Gowelle\GoogleModerator\DTOs\BlocklistTerm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Database-based blocklist repository.
 *
 * Stores and retrieves blocklist terms from the database.
 * Uses caching for performance optimization.
 */
class DatabaseBlocklistRepository implements BlocklistRepository
{
    private string $table;

    private bool $cacheEnabled;

    private int $cacheTtl;

    private string $cachePrefix;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->table = $config['blocklists']['table'] ?? 'blocklist_terms';
        $this->cacheEnabled = $config['cache']['enabled'] ?? true;
        $this->cacheTtl = $config['cache']['ttl'] ?? 3600;
        $this->cachePrefix = $config['cache']['prefix'] ?? 'google_moderator_';
    }

    /**
     * @return array<BlocklistTerm>
     */
    public function getTerms(string $language): array
    {
        $cacheKey = $this->cachePrefix.'blocklist_db_'.$language;

        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $rows = DB::table($this->table)
            ->where('language', $language)
            ->get();

        $terms = [];

        foreach ($rows as $row) {
            $terms[] = new BlocklistTerm(
                language: $row->language,
                value: $row->value,
                severity: $row->severity,
                isRegex: (bool) $row->is_regex,
            );
        }

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $terms, $this->cacheTtl);
        }

        return $terms;
    }

    public function addTerm(string $language, string $value, string $severity, bool $isRegex = false): void
    {
        // Use upsert to avoid duplicates
        DB::table($this->table)->updateOrInsert(
            [
                'language' => $language,
                'value' => $value,
            ],
            [
                'severity' => $severity,
                'is_regex' => $isRegex,
                'updated_at' => now(),
            ],
        );

        $this->clearCache($language);
    }

    public function removeTerm(string $language, string $value): void
    {
        DB::table($this->table)
            ->where('language', $language)
            ->where('value', $value)
            ->delete();

        $this->clearCache($language);
    }

    public function hasTerm(string $language, string $value): bool
    {
        return DB::table($this->table)
            ->where('language', $language)
            ->where('value', $value)
            ->exists();
    }

    /**
     * @return array<string>
     */
    public function getLanguages(): array
    {
        return DB::table($this->table)
            ->distinct()
            ->pluck('language')
            ->toArray();
    }

    /**
     * Clear cache for a language.
     */
    private function clearCache(string $language): void
    {
        if ($this->cacheEnabled) {
            Cache::forget($this->cachePrefix.'blocklist_db_'.$language);
        }
    }

    /**
     * Clear all blocklist caches.
     */
    public function clearAllCaches(): void
    {
        if ($this->cacheEnabled) {
            foreach ($this->getLanguages() as $language) {
                $this->clearCache($language);
            }
        }
    }
}
