<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Engines;

use Gowelle\GoogleModerator\Contracts\TextModerationEngine;
use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Text moderation engine using Google Gemini API.
 *
 * This is an optional engine that provides advanced content classification
 * using Gemini's large language model capabilities.
 *
 * @note Disabled by default. Enable via config['gemini']['enabled'] = true
 */
class GeminiTextEngine implements TextModerationEngine
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
        'toxic',
        'hate_speech',
        'harassment',
        'sexually_explicit',
        'dangerous',
        'violence',
        'self_harm',
        'spam',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $geminiConfig = $config['gemini'] ?? [];
        $this->apiKey = $geminiConfig['api_key'] ?? '';
        $this->model = $geminiConfig['model'] ?? 'gemini-pro';
        $this->thresholds = $config['thresholds'] ?? [];

        if (empty($this->apiKey)) {
            throw ModerationException::configurationError('Gemini API key is required');
        }
    }

    public function moderate(string $text, ?string $language = null): ModerationResult
    {
        if (trim($text) === '') {
            return ModerationResult::safe('google', 'gemini');
        }

        try {
            $prompt = $this->buildModerationPrompt($text, $language);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
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
                    'API request failed: '.$response->body(),
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
     * Build the moderation prompt for Gemini.
     */
    private function buildModerationPrompt(string $text, ?string $language): string
    {
        $categories = implode(', ', self::CATEGORIES);
        $languageContext = $language ? " The text is in language code '{$language}'." : '';

        return <<<PROMPT
Analyze the following text for content moderation.{$languageContext}

Categories to evaluate: {$categories}

For each category, provide a confidence score from 0.0 to 1.0 indicating how likely the text contains that type of content.

Respond ONLY with a valid JSON object in this exact format:
{
  "scores": {
    "toxic": 0.0,
    "hate_speech": 0.0,
    "harassment": 0.0,
    "sexually_explicit": 0.0,
    "dangerous": 0.0,
    "violence": 0.0,
    "self_harm": 0.0,
    "spam": 0.0
  }
}

Text to analyze:
---
{$text}
---
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
