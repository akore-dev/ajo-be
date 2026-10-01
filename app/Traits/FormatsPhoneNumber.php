<?php

namespace App\Traits;

trait FormatsPhoneNumber
{
    /**
     * Format and sanitize phone number to E.164 without standard '+' prefix (e.g. 2348012345678).
     *
     * @param  string|null  $phone
     * @param  string  $defaultCountryCode
     * @return string
     */
    public function sanitizePhoneNumber(?string $phone, string $defaultCountryCode = '234'): string
    {
        if (empty($phone)) {
            return '';
        }

        // Strip non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Convert local '080...', '070...', '090...' to international '23480...'
        if (str_starts_with($cleaned, '0')) {
            return $defaultCountryCode . substr($cleaned, 1);
        }

        // If already starts with country code or full length without leading zero
        return $cleaned;
    }
}
