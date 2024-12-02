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
        // Remove HTML and PHP tags
        $value = strip_tags($value);

        // Convert special characters to HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove null bytes
        $value = str_replace(chr(0), '', $value);

        // Normalize whitespace
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return $value;
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
