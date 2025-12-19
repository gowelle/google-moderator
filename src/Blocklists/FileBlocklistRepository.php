<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Blocklists;

use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Gowelle\GoogleModerator\DTOs\BlocklistTerm;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Cache;

/**
 * File-based blocklist repository.
 *
 * Reads blocklist terms from JSON files stored in the configured directory.
 * Each language has its own file (e.g., en.json, sw.json).
 */
class FileBlocklistRepository implements BlocklistRepository
{
    private string $basePath;

    private bool $cacheEnabled;

    private int $cacheTtl;

    private string $cachePrefix;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->basePath = $config['blocklists']['file_path'] ?? storage_path('blocklists');
        $this->cacheEnabled = $config['cache']['enabled'] ?? true;
        $this->cacheTtl = $config['cache']['ttl'] ?? 3600;
        $this->cachePrefix = $config['cache']['prefix'] ?? 'google_moderator_';
    }

    /**
     * @return array<BlocklistTerm>
     */
    public function getTerms(string $language): array
    {
        $cacheKey = $this->cachePrefix.'blocklist_file_'.$language;

        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $filePath = $this->getFilePath($language);

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw ModerationException::blocklistError("Could not read blocklist file: {$filePath}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ModerationException::blocklistError(
                "Invalid JSON in blocklist file {$filePath}: ".json_last_error_msg(),
            );
        }

        $terms = [];

        foreach ($data['terms'] ?? [] as $termData) {
            $terms[] = BlocklistTerm::fromArray($termData, $language);
        }

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $terms, $this->cacheTtl);
        }

        return $terms;
    }

    public function addTerm(string $language, string $value, string $severity, bool $isRegex = false): void
    {
        $filePath = $this->getFilePath($language);
        $data = $this->loadFileData($filePath, $language);

        // Check if term already exists
        foreach ($data['terms'] as $term) {
            if ($term['value'] === $value) {
                return; // Already exists
            }
        }

        $data['terms'][] = [
            'value' => $value,
            'severity' => $severity,
            'is_regex' => $isRegex,
        ];

        $this->saveFileData($filePath, $data);
        $this->clearCache($language);
    }

    public function removeTerm(string $language, string $value): void
    {
        $filePath = $this->getFilePath($language);

        if (!file_exists($filePath)) {
            return;
        }

        $data = $this->loadFileData($filePath, $language);

        $data['terms'] = array_values(array_filter(
            $data['terms'],
            fn ($term) => $term['value'] !== $value,
        ));

        $this->saveFileData($filePath, $data);
        $this->clearCache($language);
    }

    public function hasTerm(string $language, string $value): bool
    {
        $terms = $this->getTerms($language);

        foreach ($terms as $term) {
            if ($term->value === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    public function getLanguages(): array
    {
        if (!is_dir($this->basePath)) {
            return [];
        }

        $files = glob($this->basePath.'/*.json');
        $languages = [];

        foreach ($files as $file) {
            $languages[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $languages;
    }

    /**
     * Get the file path for a language.
     */
    private function getFilePath(string $language): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$language.'.json';
    }

    /**
     * Load data from file or create default structure.
     *
     * @return array<string, mixed>
     */
    private function loadFileData(string $filePath, string $language): array
    {
        if (!file_exists($filePath)) {
            return [
                'language' => $language,
                'terms' => [],
            ];
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw ModerationException::blocklistError("Could not read blocklist file: {$filePath}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ModerationException::blocklistError(
                "Invalid JSON in blocklist file {$filePath}: ".json_last_error_msg(),
            );
        }

        return $data;
    }

    /**
     * Save data to file.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveFileData(string $filePath, array $data): void
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw ModerationException::blocklistError('Could not encode blocklist data as JSON');
        }

        $result = file_put_contents($filePath, $json);

        if ($result === false) {
            throw ModerationException::blocklistError("Could not write to blocklist file: {$filePath}");
        }
    }

    /**
     * Clear cache for a language.
     */
    private function clearCache(string $language): void
    {
        if ($this->cacheEnabled) {
            Cache::forget($this->cachePrefix.'blocklist_file_'.$language);
        }
    }
}
