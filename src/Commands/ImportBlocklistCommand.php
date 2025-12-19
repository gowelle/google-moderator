<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Commands;

use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Import blocklist terms from a JSON file into the configured storage.
 */
class ImportBlocklistCommand extends Command
{
    protected $signature = 'moderator:blocklist:import
                            {file : Path to the JSON file to import}
                            {--language= : Override the language code from the file}
                            {--force : Import without confirmation}';

    protected $description = 'Import blocklist terms from a JSON file';

    public function handle(BlocklistRepository $repository): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            error("File not found: {$file}");

            return self::FAILURE;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            error("Could not read file: {$file}");

            return self::FAILURE;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error('Invalid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $language = $this->option('language') ?? $data['language'] ?? null;

        if ($language === null) {
            error('Language code is required. Specify --language or include "language" in the JSON file.');

            return self::FAILURE;
        }

        $terms = $data['terms'] ?? [];

        if (empty($terms)) {
            warning('No terms found in the file.');

            return self::SUCCESS;
        }

        info('Found '.count($terms)." terms for language '{$language}'");

        if (!$this->option('force') && !confirm('Do you want to import these terms?')) {
            info('Import cancelled.');

            return self::SUCCESS;
        }

        $imported = 0;

        foreach ($terms as $term) {
            $value = $term['value'] ?? null;

            if ($value === null) {
                continue;
            }

            $repository->addTerm(
                language: $language,
                value: $value,
                severity: $term['severity'] ?? 'medium',
                isRegex: $term['is_regex'] ?? $term['isRegex'] ?? false,
            );

            $imported++;
        }

        info("Successfully imported {$imported} terms.");

        return self::SUCCESS;
    }
}
