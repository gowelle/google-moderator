<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Rules;

use Closure;
use Gowelle\GoogleModerator\Facades\Moderation;
use Illuminate\Contracts\Validation\ValidationRule;

class ModeratedText implements ValidationRule
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
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $result = Moderation::text($value);

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

        $fail("The :attribute contains unsafe content ({$categories}).");
    }
}
