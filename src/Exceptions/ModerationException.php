<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when moderation operations fail.
 */
class ModerationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $engine = 'unknown',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for API errors.
     */
    public static function apiError(string $engine, string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: "Google {$engine} API error: {$message}",
            engine: $engine,
            previous: $previous,
        );
    }

    /**
     * Create an exception for invalid image input.
     */
    public static function invalidImage(string $message): self
    {
        return new self(
            message: "Invalid image: {$message}",
            engine: 'vision',
        );
    }

    /**
     * Create an exception for configuration errors.
     */
    public static function configurationError(string $message): self
    {
        return new self(
            message: "Configuration error: {$message}",
            engine: 'configuration',
        );
    }

    /**
     * Create an exception for unsupported engine.
     */
    public static function unsupportedEngine(string $type, string $engine): self
    {
        return new self(
            message: "Unsupported {$type} engine: {$engine}",
            engine: $engine,
        );
    }

    /**
     * Create an exception for blocklist errors.
     */
    public static function blocklistError(string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: "Blocklist error: {$message}",
            engine: 'blocklist',
            previous: $previous,
        );
    }
}
