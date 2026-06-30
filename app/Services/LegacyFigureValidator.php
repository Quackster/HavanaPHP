<?php

namespace App\Services;

class LegacyFigureValidator
{
    /** @var array<string, array{paletteId: int, mandatory: bool}>|null */
    private ?array $setTypes = null;

    /** @var array<string, array<int, array{type: string, id: string, gender: string, club: bool, selectable: bool}>>|null */
    private ?array $setsByType = null;

    /** @var array<int, array<string, true>>|null */
    private ?array $palettes = null;

    public function validate(string $figure, string $gender, bool $hasClub = false): bool
    {
        $this->load();

        $parts = explode('.', $figure);

        if ($parts === [] || $figure === '') {
            return false;
        }

        $submittedTypes = [];

        foreach ($parts as $part) {
            $tokens = explode('-', $part);

            if (count($tokens) < 2 || count($tokens) > 3) {
                return false;
            }

            $submittedTypes[] = $tokens[0];
        }

        foreach ($this->setTypes ?? [] as $type => $setType) {
            if (strcasecmp($type, 'sh') === 0) {
                continue;
            }

            if ($setType['mandatory'] && ! in_array($type, $submittedTypes, true)) {
                return false;
            }
        }

        foreach ($parts as $part) {
            $tokens = explode('-', $part);
            $type = $tokens[0];
            $setId = $tokens[1];
            $paletteId = $tokens[2] ?? '';
            $set = $this->findSet($type, $setId, $gender);

            if ($set === null) {
                return false;
            }

            if ($set['club'] && ! $hasClub) {
                return false;
            }

            if (! $set['selectable']) {
                return false;
            }

            if ($paletteId !== '') {
                $setType = $this->setTypes[$type] ?? null;

                if ($setType === null || ! isset($this->palettes[$setType['paletteId']][$paletteId])) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array{type: string, id: string, gender: string, club: bool, selectable: bool}|null */
    private function findSet(string $type, string $setId, string $gender): ?array
    {
        foreach ($this->setsByType[$type] ?? [] as $set) {
            if ($set['id'] === $setId && (strcasecmp($set['gender'], $gender) === 0 || strcasecmp($set['gender'], 'U') === 0)) {
                return $set;
            }
        }

        return null;
    }

    private function load(): void
    {
        if ($this->setTypes !== null && $this->setsByType !== null && $this->palettes !== null) {
            return;
        }

        $this->setTypes = [];
        $this->setsByType = [];
        $this->palettes = [];

        $file = $this->figureDataPath();

        if ($file === null) {
            return;
        }

        $xml = simplexml_load_file($file);

        if ($xml === false) {
            return;
        }

        foreach ($xml->colors->palette ?? [] as $palette) {
            $paletteId = (int) $palette['id'];
            $this->palettes[$paletteId] = [];

            foreach ($palette->color ?? [] as $color) {
                $this->palettes[$paletteId][(string) $color['id']] = true;
            }
        }

        foreach ($xml->sets->settype ?? [] as $setType) {
            $type = (string) $setType['type'];
            $this->setTypes[$type] = [
                'paletteId' => (int) $setType['paletteid'],
                'mandatory' => (string) $setType['mandatory'] === '1',
            ];

            foreach ($setType->set ?? [] as $set) {
                $this->setsByType[$type][] = [
                    'type' => $type,
                    'id' => (string) $set['id'],
                    'gender' => (string) $set['gender'],
                    'club' => (string) $set['club'] === '1',
                    'selectable' => (string) $set['selectable'] === '1',
                ];
            }
        }
    }

    private function figureDataPath(): ?string
    {
        $base = (string) config('havana.base_path');
        $candidates = [
            $base.'/figuredata.xml',
            $base.'/tools/figuredata.xml',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
