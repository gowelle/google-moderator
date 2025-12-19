<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Rules;

use Closure;
use Gowelle\GoogleModerator\Facades\Moderation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Throwable;

class ModeratedImage implements ValidationRule
{
    /**
     * @param  string|null  $message  Custom failure message
     */
    public function __construct(
        protected ?string $message = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $imagePath = $this->resolveImagePath($value);

        if (!$imagePath) {
            $fail('The :attribute must be a valid image.');

            return;
        }

        try {
            $result = Moderation::image($imagePath);

            if ($result->isSafe()) {
                return;
            }

            if ($this->message) {
                $fail($this->message);

                return;
            }

            $categories = implode(', ', array_map(
                fn ($flag) => $flag->category ?? 'unknown',
                $result->flags(),
            ));

            $fail("The :attribute contains unsafe media ({$categories}).");
        } catch (Throwable $e) {
            // If moderation fails (e.g. API error), we can choose to fail closed or open.
            // For safety, failing typically makes sense, or logging and passing depending on config.
            // Usage here suggests we should probably just let the exception bubble or fail safely?
            // Existing validators usually don't crash whole app.
            // Let's rethrow for now as the user can handle it, or we could fail validation.
            // Given the task is just "custom validators", let's fail validation for now to be safe.
            $fail("Unable to validate :attribute: {$e->getMessage()}");
        }
    }

    protected function resolveImagePath(mixed $value): ?string
    {
        if ($value instanceof UploadedFile) {
            return $value->getPathname();
        }

        if (is_string($value)) {
            return $value;
        }

        return null; // Binary not easily supported unless we save it tmp or pass directly if engine supports
    }
}
