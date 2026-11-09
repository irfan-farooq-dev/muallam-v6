<?php

namespace App\Services;

/**
 * Adds "?v=…" to this site's own CSS/JS addresses, so browsers fetch a changed file at once
 * instead of reusing an old cached copy.
 */
class AssetVersions
{
    /**
     * @var array<string, string>
     */
    private array $versions = [];

    public function url(string $path): string
    {
        return asset($path).'?v='.($this->versions[$path] ??= $this->versionFor($path));
    }

    private function versionFor(string $path): string
    {
        $file = public_path($path);

        if (is_file($file)) {
            return (string) filemtime($file);
        }

        // On cPanel the public files live in public_html, outside the app folder; every uploaded release has a new code instead.
        $release = base_path('RELEASE');

        return is_file($release) ? rawurlencode(trim((string) file_get_contents($release))) : '1';
    }
}
