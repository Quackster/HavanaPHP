<?php

namespace App\Services;

class LegacyLocale
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $path = rtrim((string) config('havana.template_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.config('havana.locale_file', 'locale-en.ini');

        if (! is_file($path)) {
            return $this->values = [];
        }

        $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);

        return $this->values = is_array($parsed) ? array_map(
            fn ($value): string => $this->decodePropertiesValue((string) $value),
            $parsed,
        ) : [];
    }

    private function decodePropertiesValue(string $value): string
    {
        $decoded = (string) preg_replace_callback('/\\\\(?:u([0-9a-fA-F]{4})|(.))/s', function (array $matches): string {
            if (($matches[1] ?? '') !== '') {
                return mb_chr((int) hexdec($matches[1]), 'UTF-8');
            }

            return match ($matches[2]) {
                't' => "\t",
                'n' => "\n",
                'r' => "\r",
                'f' => "\f",
                default => $matches[2],
            };
        }, $value);

        return str_replace(['\\<', '\\>', "\\'"], ['<', '>', "'"], $decoded);
    }
}
