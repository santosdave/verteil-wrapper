<?php

namespace Santosdave\VerteilWrapper\Security;

trait  SanitizesInput
{
    /**
     * Sanitize input data by removing potentially harmful content
     * 
     * @param array $input
     * @return array
     */
    protected function sanitize(array $input): array
    {
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = $this->sanitizeString($value);
            }
        });
        return $input;
    }

    /**
     * Sanitize a single string value
     * 
     * @param string $value
     * @return string
     */
    protected function sanitizeString(string $value): string
    {
        // Requests go out as JSON, which already encodes every character safely. HTML
        // escaping (and tag stripping) here changed the data itself: a passenger called
        // O'Brien reached the airline as "O&apos;Brien". Only control characters, which no
        // NDC field allows, are removed, and whitespace is normalised.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * Validate that a string contains only allowed characters
     * 
     * @param string $value
     * @param string $pattern
     * @return bool
     */
    protected function validatePattern(string $value, string $pattern): bool
    {
        return (bool) preg_match($pattern, $value);
    }
}
