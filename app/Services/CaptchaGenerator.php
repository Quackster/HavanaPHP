<?php

namespace App\Services;

class CaptchaGenerator
{
    public function text(int $length = 7): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $characters = '';

        // Havana-Web calls generateText(7), whose Java implementation returns 6 chars.
        for ($i = 0; $i < max(1, $length - 1); $i++) {
            $characters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return strtolower($characters);
    }

    public function png(string $text): string
    {
        $width = 200;
        $height = 50;
        $fontSize = 5;
        $image = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $noise = imagecolorallocate($image, 90, 90, 90);

        imagefill($image, 0, 0, $white);

        for ($i = 0; $i < 8; $i++) {
            imageline(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $noise
            );
        }

        $x = 18;
        foreach (str_split($text) as $character) {
            imagestring($image, $fontSize, $x, random_int(14, 26), $character, $black);
            $x += 26 + random_int(0, 5);
        }

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
