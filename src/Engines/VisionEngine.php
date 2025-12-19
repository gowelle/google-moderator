<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Engines;

use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Likelihood;
use Gowelle\GoogleModerator\Contracts\ImageModerationEngine;
use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Throwable;

/**
 * Image moderation engine using Google Cloud Vision API.
 *
 * Uses SafeSearch detection to identify adult, violence, racy,
 * medical, and spoof content in images.
 */
class VisionEngine implements ImageModerationEngine
{
    private ImageAnnotatorClient $client;

    /**
     * @var array<string, string|int>
     */
    private array $thresholds;

    /**
     * Mapping of threshold strings to minimum likelihood level.
     */
    private const THRESHOLD_LEVELS = [
        'VERY_UNLIKELY' => 1,
        'UNLIKELY' => 2,
        'POSSIBLE' => 3,
        'LIKELY' => 4,
        'VERY_LIKELY' => 5,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->thresholds = $config['thresholds'] ?? [];
        $this->client = $this->createClient($config);
    }

    public function moderate(mixed $image): ModerationResult
    {
        try {
            $imageObject = $this->prepareImage($image);

            $feature = (new Feature)->setType(Type::SAFE_SEARCH_DETECTION);

            $response = $this->client->annotateImage($imageObject, [$feature]);

            if ($response->hasError()) {
                $error = $response->getError();
                $message = $error !== null ? $error->getMessage() : 'Unknown error';
                throw ModerationException::apiError('vision', $message);
            }

            $safeSearch = $response->getSafeSearchAnnotation();

            if ($safeSearch === null) {
                return ModerationResult::safe('google', 'vision');
            }

            $flags = [];
            $rawResponse = [];

            // Check each SafeSearch category
            $categories = [
                'adult' => $safeSearch->getAdult(),
                'violence' => $safeSearch->getViolence(),
                'racy' => $safeSearch->getRacy(),
                'spoof' => $safeSearch->getSpoof(),
                'medical' => $safeSearch->getMedical(),
            ];

            foreach ($categories as $category => $likelihood) {
                $likelihoodName = Likelihood::name($likelihood);
                $rawResponse[$category] = $likelihoodName;

                if ($this->exceedsThreshold($category, $likelihood)) {
                    $flags[] = new FlaggedTerm(
                        term: $category,
                        category: $category,
                        severity: $this->determineSeverity($likelihood),
                        confidence: $this->likelihoodToConfidence($likelihood),
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
                engine: 'vision',
                rawResponse: $rawResponse,
            );
        } catch (ModerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ModerationException::apiError('vision', $e->getMessage(), $e);
        }
    }

    /**
     * Prepare the image for the API request.
     *
     * @param  string|resource  $image  Path, URL, or binary content
     */
    private function prepareImage(mixed $image): Image
    {
        $imageObject = new Image;

        if (is_string($image)) {
            // Check if it's a URL
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $imageObject->setSource(
                    (new \Google\Cloud\Vision\V1\ImageSource)->setImageUri($image),
                );
            }
            // Check if it's a file path
            elseif (file_exists($image)) {
                $content = file_get_contents($image);
                if ($content === false) {
                    throw ModerationException::invalidImage('Could not read image file');
                }
                $imageObject->setContent($content);
            }
            // Assume it's binary content
            else {
                $imageObject->setContent($image);
            }
        } elseif (is_resource($image)) {
            $content = stream_get_contents($image);
            if ($content === false) {
                throw ModerationException::invalidImage('Could not read image stream');
            }
            $imageObject->setContent($content);
        } else {
            throw ModerationException::invalidImage('Invalid image input type');
        }

        return $imageObject;
    }

    /**
     * Check if the likelihood exceeds the configured threshold.
     */
    private function exceedsThreshold(string $category, int $likelihood): bool
    {
        $threshold = $this->thresholds[$category] ?? 'LIKELY';

        if (is_string($threshold)) {
            $thresholdLevel = self::THRESHOLD_LEVELS[$threshold] ?? 4;

            return $likelihood >= $thresholdLevel;
        }

        // Numeric threshold (0-5)
        return $likelihood >= $threshold;
    }

    /**
     * Convert likelihood enum to confidence score.
     */
    private function likelihoodToConfidence(int $likelihood): float
    {
        return match ($likelihood) {
            Likelihood::VERY_LIKELY => 0.95,
            Likelihood::LIKELY => 0.8,
            Likelihood::POSSIBLE => 0.5,
            Likelihood::UNLIKELY => 0.2,
            Likelihood::VERY_UNLIKELY => 0.05,
            default => 0.0,
        };
    }

    /**
     * Determine severity based on likelihood.
     */
    private function determineSeverity(int $likelihood): string
    {
        return match ($likelihood) {
            Likelihood::VERY_LIKELY => 'high',
            Likelihood::LIKELY => 'high',
            Likelihood::POSSIBLE => 'medium',
            default => 'low',
        };
    }

    /**
     * Create the Google Cloud Vision client.
     *
     * @param  array<string, mixed>  $config
     */
    private function createClient(array $config): ImageAnnotatorClient
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

        return new ImageAnnotatorClient($clientOptions);
    }

    /**
     * Close the client connection.
     */
    public function close(): void
    {
        $this->client->close();
    }
}
