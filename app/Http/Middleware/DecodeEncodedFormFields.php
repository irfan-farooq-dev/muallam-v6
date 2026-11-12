<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Some hosts run a web firewall (ModSecurity) that blocks ordinary post content such as HTML, links or
 * code-like words with a 403. The post editor therefore sends its free-text fields as one base64-encoded
 * JSON object in "encoded_fields". This turns it back into normal fields before validation.
 *
 * Only the fields named on the route are taken from it, and only string or null values.
 * Requests without "encoded_fields" pass through unchanged.
 */
class DecodeEncodedFormFields
{
    public const FIELD = 'encoded_fields';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$allowedFields): Response
    {
        $encoded = $request->input(self::FIELD);
        $request->request->remove(self::FIELD);

        foreach ($this->decode($encoded) as $field => $value) {
            if (in_array($field, $allowedFields, true) && (is_string($value) || $value === null)) {
                $request->merge([$field => $value]);
            }
        }

        return $next($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(mixed $encoded): array
    {
        if (! is_string($encoded) || $encoded === '') {
            return [];
        }

        $json = base64_decode($encoded, true);
        $values = $json === false ? null : json_decode($json, true);

        return is_array($values) ? $values : [];
    }
}
