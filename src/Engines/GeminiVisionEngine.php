<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Engines;

use Gowelle\GoogleModerator\Contracts\ImageModerationEngine;
use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Image moderation engine using Google Gemini Vision API.
 *
 * This is an optional engine that provides advanced content classification
 * for images using Gemini's multimodal capabilities.
 *
 * @note Disabled by default. Enable via config['gemini']['enabled'] = true
 */
class GeminiVisionEngine implements ImageModerationEngine
{
    private string $apiKey;

    private string $model;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * @var array<string, float>
     */
    private array $thresholds;

    /**
     * Categories to analyze.
     */
    private const CATEGORIES = [
        'adult',
        'violence',
        'hate_symbols',
        'dangerous',
        'medical',
        'racy',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $geminiConfig = $config['gemini'] ?? [];
        $this->apiKey = $geminiConfig['api_key'] ?? '';
        $this->model = $geminiConfig['vision_model'] ?? 'gemini-pro-vision';
        $this->thresholds = $config['thresholds'] ?? [];

        if (empty($this->apiKey)) {
            throw ModerationException::configurationError('Gemini API key is required for vision');
        }
    }

    public function moderate(mixed $image): ModerationResult
    {
        try {
            $imageData = $this->prepareImageData($image);

            $prompt = $this->buildModerationPrompt();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $imageData['mime_type'],
                                    'data' => $imageData['data'],
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0,
                    'maxOutputTokens' => 1024,
                ],
            ]);

            if (!$response->successful()) {
                throw ModerationException::apiError(
                    'gemini',
                    'Vision API request failed: '.$response->body(),
                );
            }

            $data = $response->json();

            return $this->parseResponse($data);
        } catch (ModerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ModerationException::apiError('gemini', $e->getMessage(), $e);
        }
    }

    /**
     * Prepare image data for the API request.
     *
     * @param  string|resource  $image
     * @return array{mime_type: string, data: string}
     */
    private function prepareImageData(mixed $image): array
    {
        $content = '';
        $mimeType = 'image/jpeg';

        if (is_string($image)) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                // Fetch image from URL
                $response = Http::get($image);
                if (!$response->successful()) {
                    throw ModerationException::invalidImage('Could not fetch image from URL');
                }
                $content = $response->body();
                $mimeType = $response->header('Content-Type') ?? 'image/jpeg';
            } elseif (file_exists($image)) {
                $content = file_get_contents($image);
                if ($content === false) {
                    throw ModerationException::invalidImage('Could not read image file');
                }
                $mimeType = mime_content_type($image) ?: 'image/jpeg';
            } else {
                // Assume it's binary content
                $content = $image;
            }
        } elseif (is_resource($image)) {
            $content = stream_get_contents($image);
            if ($content === false) {
                throw ModerationException::invalidImage('Could not read image stream');
            }
        } else {
            throw ModerationException::invalidImage('Invalid image input type');
        }

        return [
            'mime_type' => $mimeType,
            'data' => base64_encode($content),
        ];
    }

    /**
     * Build the moderation prompt for Gemini Vision.
     */
    private function buildModerationPrompt(): string
    {
        $categories = implode(', ', self::CATEGORIES);

        return <<<PROMPT
Analyze this image for content moderation purposes.

Categories to evaluate: {$categories}

For each category, provide a confidence score from 0.0 to 1.0 indicating how likely the image contains that type of content.

Respond ONLY with a valid JSON object in this exact format:
{
  "scores": {
    "adult": 0.0,
    "violence": 0.0,
    "hate_symbols": 0.0,
    "dangerous": 0.0,
    "medical": 0.0,
    "racy": 0.0
  }
}
PROMPT;
    }

    /**
     * Parse the Gemini API response.
     *
     * @param  array<string, mixed>  $data
     */
    private function parseResponse(array $data): ModerationResult
    {
        $rawResponse = $data;
        $flags = [];

        // Extract the generated text
        $generatedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Try to parse JSON from the response
        $jsonMatch = [];
        if (preg_match('/\{[\s\S]*\}/', $generatedText, $jsonMatch)) {
            $scores = json_decode($jsonMatch[0], true);

            if (isset($scores['scores']) && is_array($scores['scores'])) {
                foreach ($scores['scores'] as $category => $confidence) {
                    $confidence = (float) $confidence;
                    $threshold = $this->thresholds[$category] ?? 0.7;

                    if ($confidence >= $threshold) {
                        $flags[] = new FlaggedTerm(
                            term: $category,
                            category: $category,
                            severity: $this->determineSeverity($confidence),
                            confidence: $confidence,
                            source: 'api',
                        );
                    }
                }
            }
        }

        $isSafe = count($flags) === 0;
        $maxConfidence = empty($flags) ? null : max(array_map(fn ($f) => $f->confidence ?? 0, $flags));

        return new ModerationResult(
            isSafe: $isSafe,
            confidence: $maxConfidence,
            flags: $flags,
            provider: 'google',
            engine: 'gemini',
            rawResponse: $rawResponse,
        );
    }

    /**
     * Determine severity based on confidence score.
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
}
