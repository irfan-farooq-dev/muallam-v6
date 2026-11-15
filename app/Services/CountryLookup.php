<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Finds the country of a visitor's IP address with a free lookup service (config muallam.country_lookup_url,
 * country.is by default). Answers are cached for 30 days under a hash of the IP, so each address is looked up
 * once a month at most. Local and private addresses, failures and timeouts give null ("unknown").
 */
class CountryLookup
{
    private const CACHE_DAYS = 30;

    private const TIMEOUT_SECONDS = 3;

    public function country(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        // An empty string is cached for "unknown", so a failed address is not asked about on every view.
        $code = Cache::remember(
            'country-lookup:'.hash('sha256', $ip),
            now()->addDays(self::CACHE_DAYS),
            fn (): string => $this->lookUp($ip) ?? '',
        );

        return $code === '' ? null : $code;
    }

    private function lookUp(string $ip): ?string
    {
        $url = str_replace('{ip}', rawurlencode($ip), (string) config('muallam.country_lookup_url'));

        $code = rescue(
            fn () => Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()->get($url)->throw()->json('country'),
            null,
            report: false,
        );

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }

    /**
     * "Pakistan" for "PK" when PHP's intl extension is installed, otherwise the code itself.
     */
    public static function countryName(?string $code): string
    {
        if ($code === null) {
            return 'Unknown';
        }

        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'en') : '';

        return $name !== '' && $name !== $code ? $name : $code;
    }
}
