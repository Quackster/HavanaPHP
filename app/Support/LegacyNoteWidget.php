<?php

namespace App\Support;

class LegacyNoteWidget
{
    public function __construct(private readonly object $row) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getX(): int
    {
        return (int) $this->row->x;
    }

    public function getY(): int
    {
        return (int) $this->row->y;
    }

    public function getZ(): int
    {
        return (int) $this->row->z;
    }

    public function getSkin(): string
    {
        return self::skinName((int) $this->row->skin_id);
    }

    public function getFormattedText(): string
    {
        return self::formatText((string) $this->row->text);
    }

    public static function skinName(int $skinId): string
    {
        return match ($skinId) {
            1 => 'defaultskin',
            2 => 'speechbubbleskin',
            3 => 'metalskin',
            4 => 'noteitskin',
            5 => 'notepadskin',
            6 => 'goldenskin',
            7 => 'hc_machineskin',
            8 => 'hc_pillowskin',
            default => 'nakedskin',
        };
    }

    public static function formatText(string $text): string
    {
        $text = mb_substr(trim($text), 0, 500);

        return self::formatLegacyMarkup($text);
    }

    public static function formatPreviewText(string $text): string
    {
        return mb_substr(self::formatLegacyMarkup($text), 0, 500);
    }

    private static function formatLegacyMarkup(string $text): string
    {
        $text = str_replace("\r", "\n", $text);
        $text = str_replace(["[/quote]\n\n", "[/quote]\n"], '[/quote]', $text);
        $text = str_replace("\n", '[br]', $text);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $replacements = [
            '/\[b\](.*?)\[\/b\]/is' => '<b>$1</b>',
            '/\[i\](.*?)\[\/i\]/is' => '<i>$1</i>',
            '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is' => '<s>$1</s>',
            '/\[strike\](.*?)\[\/strike\]/is' => '<strike>$1</strike>',
            '/\[color=(orange|red|yellow|green|cyan|blue|gray|black|white)\](.*?)\[\/color\]/is' => '<font color="$1">$2</font>',
            '/\[color=(#[0-9a-fA-F]{6})\](.*?)\[\/color\]/is' => '<font color="$1">$2</font>',
            '/\[size=small\](.*?)\[\/size\]/is' => '<span style="font-size: 9px;">$1</span>',
            '/\[size=large\](.*?)\[\/size\]/is' => '<span style="font-size: 14px;">$1</span>',
            '/\[code\](.*?)\[\/code\]/is' => '<pre>$1</pre>',
            '/\[br\]/i' => '<br>',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $text) ?? $text;
    }
}
