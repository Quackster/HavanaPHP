<?php

namespace App\Services;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

class LegacyFilesystemLoader implements LoaderInterface
{
    /** @param array<string, string> $locale */
    public function __construct(
        private readonly string $root,
        private readonly array $locale = [],
    ) {}

    public function getSourceContext(string $name): Source
    {
        $path = $this->path($name);

        if (! is_file($path)) {
            throw new LoaderError(sprintf('Unable to find template "%s".', $name));
        }

        return new Source($this->normalizeSource((string) file_get_contents($path)), $name, $path);
    }

    public function getCacheKey(string $name): string
    {
        return $this->path($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        $path = $this->path($name);

        return is_file($path) && filemtime($path) <= $time;
    }

    public function exists(string $name): bool
    {
        try {
            return is_file($this->path($name));
        } catch (LoaderError) {
            return false;
        }
    }

    private function path(string $name): string
    {
        $name = str_replace('\\', '/', $name);

        while (str_starts_with($name, '../')) {
            $name = substr($name, 3);
        }

        $path = $this->root.DIRECTORY_SEPARATOR.ltrim($name, '/');

        if (! is_file($path) && str_starts_with($name, 'base/email_')) {
            $path = $this->root.DIRECTORY_SEPARATOR.'account/email/'.ltrim($name, '/');
        }

        $realRoot = realpath($this->root);
        $realPath = realpath($path);

        if ($realRoot === false || $realPath === false || ! str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)) {
            throw new LoaderError(sprintf('Unable to find template "%s".', $name));
        }

        return $realPath;
    }

    private function normalizeSource(string $source): string
    {
        $source = $this->inlineLocaleTemplateFragments($source);
        $source = preg_replace('/\s+equals\s+/', ' == ', $source) ?? $source;
        $source = preg_replace(
            '/\(([^()]+)\)\s*\+\s*\([\'"]_+[\'"]\)\s*\+\s*\(([^()]+)\)/',
            '($1) ~ \'_\' ~ ($2)',
            $source
        ) ?? $source;
        $source = preg_replace(
            '/\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s+is\s+(not\s+)?present\s*\)/',
            '($1 is $2present)',
            $source
        ) ?? $source;
        $source = str_replace(
            "{% set id = (badgeData.getKey()) + ('_') +  (badge) %}",
            "{% set id = badgeData.getKey() ~ '_' ~ badge %}",
            $source
        );
        $source = preg_replace(
            '/document\.habboLoggedIn\s*=\s*\{\{\s*session\.loggedIn\s*\}\};/',
            "document.habboLoggedIn = {{ session.loggedIn ? 'true' : 'false' }};",
            $source
        ) ?? $source;
        $source = str_replace(
            ["|escape('js')", '|escape("js")', "|e('js')", '|e("js")'],
            ["|escape('legacy_js')", '|escape("legacy_js")', "|e('legacy_js')", '|e("legacy_js")'],
            $source
        );

        return preg_replace('/([A-Za-z_][A-Za-z0-9_]*)\.size\(\)/', '$1|length', $source) ?? $source;
    }

    private function inlineLocaleTemplateFragments(string $source): string
    {
        return preg_replace_callback(
            '/\{\{\s*locale\.([A-Za-z0-9_]+)(?:\|[^}]*)?\s*\}\}/',
            function (array $matches): string {
                $value = $this->locale[$matches[1]] ?? null;

                if ($value === null || (! str_contains($value, '{{') && ! str_contains($value, '{%'))) {
                    return $matches[0];
                }

                return $value;
            },
            $source,
        ) ?? $source;
    }
}
