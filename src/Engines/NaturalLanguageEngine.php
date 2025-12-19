<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Engines;

use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Google\Cloud\Language\V1\Client\LanguageServiceClient;
use Google\Cloud\Language\V1\ModerateTextRequest;
use Gowelle\GoogleModerator\Contracts\TextModerationEngine;
use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Throwable;

/**
 * Text moderation engine using Google Cloud Natural Language API.
 *
 * Uses the moderateText API to detect harmful content across 16 safety attributes
 * including toxicity, violence, profanity, and more.
 */
class NaturalLanguageEngine implements TextModerationEngine
{
    private LanguageServiceClient $client;

    /**
     * @var array<string, float>
     */
    private array $thresholds;

    /**
     * Mapping of API category names to normalized category keys.
     */
    private const CATEGORY_MAP = [
        'Toxic' => 'toxic',
        'Severe Toxic' => 'severe_toxic',
        'Insult' => 'insult',
        'Profanity' => 'profanity',
        'Derogatory' => 'derogatory',
        'Sexual' => 'sexual',
        'Death, Harm & Tragedy' => 'death_harm_tragedy',
        'Violent' => 'violent',
        'Firearms & Weapons' => 'firearms_weapons',
        'Public Safety' => 'public_safety',
        'Health' => 'health',
        'Religion & Belief' => 'religion_belief',
        'Illicit Drugs' => 'illicit_drugs',
        'War & Conflict' => 'war_conflict',
        'Politics' => 'politics',
        'Finance' => 'finance',
        'Legal' => 'legal',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->thresholds = $config['thresholds'] ?? [];
        $this->client = $this->createClient($config);
    }

    public function moderate(string $text, ?string $language = null): ModerationResult
    {
        if (trim($text) === '') {
            return ModerationResult::safe('google', 'natural_language');
        }

        try {
            $document = (new Document)
                ->setContent($text)
                ->setType(Type::PLAIN_TEXT);

            if ($language !== null) {
                $document->setLanguage($language);
            }

            $request = (new ModerateTextRequest())
                ->setDocument($document);

            $response = $this->client->moderateText($request);
            $categories = $response->getModerationCategories();

            $flags = [];
            $rawResponse = [];

            foreach ($categories as $category) {
                $name = $category->getName();
                $confidence = $category->getConfidence();

                $rawResponse[$name] = $confidence;

                $normalizedKey = self::CATEGORY_MAP[$name] ?? strtolower(str_replace([' ', '&', ','], ['_', '', ''], $name));
                $threshold = $this->thresholds[$normalizedKey] ?? 0.7;

                if ($confidence >= $threshold) {
                    $flags[] = new FlaggedTerm(
                        term: $name,
                        category: $normalizedKey,
                        severity: $this->determineSeverity($confidence),
                        confidence: $confidence,
                        source: 'api',
                    );
                }
            }

            $isSafe = count($flags) === 0;
            $maxConfidence = empty($flags) ? null : max(array_map(fn ($f) => $f->confidence ?? 0, $flags));

            return new ModerationResult(
                isSafe: $isSafe,
                confidence: $maxConfidence,
                flags: $flags,
                provider: 'google',
                engine: 'natural_language',
                rawResponse: $rawResponse,
            );
        } catch (Throwable $e) {
            throw ModerationException::apiError('natural_language', $e->getMessage(), $e);
        }
    }

    /**
     * Determine severity level based on confidence score.
     */
    private function determineSeverity(float $confidence): string
    {
        if ($confidence >= 0.9) {
            return 'high';
        }

        if ($confidence >= 0.7) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Create the Google Cloud Language client.
     *
     * @param  array<string, mixed>  $config
     */
    private function createClient(array $config): LanguageServiceClient
    {
        $authConfig = $config['auth'] ?? [];
        $clientOptions = [];

        // Priority 1: Inline JSON credentials
        if (!empty($authConfig['credentials_json'])) {
            $json = $authConfig['credentials_json'];

            // Handle base64 encoded credentials
            if (!str_starts_with($json, '{')) {
                $json = base64_decode($json, true) ?: $json;
            }

            $clientOptions['credentials'] = json_decode($json, true);
        }
        // Priority 2: Path to credentials file
        elseif (!empty($authConfig['credentials_path'])) {
            $clientOptions['credentials'] = $authConfig['credentials_path'];
        }
        // Priority 3: Project ID for ADC
        elseif (!empty($authConfig['project_id'])) {
            $clientOptions['projectId'] = $authConfig['project_id'];
        }
        // Fallback: Use Application Default Credentials

        return new LanguageServiceClient($clientOptions);
    }

    /**
     * Close the client connection.
     */
    public function close(): void
    {
        $this->client->close();
    }
}
