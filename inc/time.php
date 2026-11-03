<?php
declare(strict_types=1);

/**
 * Formats a stored Philippine pickup time for people. Database/API values stay HH:MM.
 */
function format_ph_time(?string $time): string
{
    $value = trim((string) $time);
    foreach (['!H:i:s', '!H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $parsed->format('g:i A');
        }
    }
    return $value;
}
