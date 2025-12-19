<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Commands;

use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Export blocklist terms to a JSON file.
 */
class ExportBlocklistCommand extends Command
{
    protected $signature = 'moderator:blocklist:export
                            {--language= : Language code to export (exports all if not specified)}
                            {--output= : Output file path (defaults to stdout)}
                            {--format=json : Output format (json)}';

    protected $description = 'Export blocklist terms to a JSON file';

    public function handle(BlocklistRepository $repository): int
    {
        $language = $this->option('language');
        $output = $this->option('output');
        $format = $this->option('format');

        if ($format !== 'json') {
            error("Unsupported format: {$format}. Only 'json' is supported.");

            return self::FAILURE;
        }

        if ($language !== null) {
            $languages = [$language];
        } else {
            $languages = $repository->getLanguages();

            if (empty($languages)) {
                warning('No languages found in blocklist.');

                return self::SUCCESS;
            }
        }

        $exportData = [];

        foreach ($languages as $lang) {
            $terms = $repository->getTerms($lang);

            if (empty($terms)) {
                continue;
            }

            $langData = [
                'language' => $lang,
                'terms' => array_map(fn ($term) => [
                    'value' => $term->value,
                    'severity' => $term->severity,
                    'is_regex' => $term->isRegex,
                ], $terms),
            ];

            if (count($languages) === 1) {
                $exportData = $langData;
            } else {
                $exportData[$lang] = $langData;
            }
        }

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            error('Failed to encode JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        if ($output !== null) {
            $result = file_put_contents($output, $json);

            if ($result === false) {
                error("Failed to write to file: {$output}");

                return self::FAILURE;
            }

            info("Exported blocklist to: {$output}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
