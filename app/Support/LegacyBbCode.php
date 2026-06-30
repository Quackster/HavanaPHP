<?php

namespace App\Support;

class LegacyBbCode
{
    public static function format(string $message, bool $allowImages = false): string
    {
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = str_replace(['javascript:', 'document.write'], '', $message);
        $sitePath = (string) config('havana.site_path', config('app.url', ''));

        $replacements = [
            '/\[b\](.*?)\[\/b\]/is' => '<b>$1</b>',
            '/\[i\](.*?)\[\/i\]/is' => '<i>$1</i>',
            '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is' => '<s>$1</s>',
            '/\[strike\](.*?)\[\/strike\]/is' => '<strike>$1</strike>',
            '/\[link=(.*?)\](.*?)\[\/link\]/is' => '<a href="$1">$2</a>',
            '/\[url=(.*?)\](.*?)\[\/url\]/is' => '<a href="$1">$2</a>',
            '/\[color=(orange|red|yellow|green|cyan|blue|gray|black|white)\](.*?)\[\/color\]/is' => '<font color="$1">$2</font>',
            '/\[color=(#[0-9a-fA-F]{6})\](.*?)\[\/color\]/is' => '<font color="$1">$2</font>',
            '/\[size=small\](.*?)\[\/size\]/is' => '<span style="font-size: 9px;">$1</span>',
            '/\[size=large\](.*?)\[\/size\]/is' => '<span style="font-size: 14px;">$1</span>',
            '/\[code\](.*?)\[\/code\]/is' => '<pre>$1</pre>',
            '/\[habbo=(.*?)\](.*?)\[\/habbo\]/is' => '<a href="'.$sitePath.'/home/$1/id">$2</a>',
            '/\[room=(.*?)\](.*?)\[\/room\]/is' => '<a onclick="roomForward(this, \'$1\', \'private\'); return false;" target="client" href="'.$sitePath.'/client?forwardId=2&roomId=$1">$2</a>',
            '/\[group=(.*?)\](.*?)\[\/group\]/is' => '<a href="'.$sitePath.'/groups/$1/id">$2</a>',
        ];

        $message = preg_replace(array_keys($replacements), array_values($replacements), $message) ?? $message;

        for ($i = 0; $i < 10; $i++) {
            $message = preg_replace('/\[quote\](.*?)\[\/quote\]/is', '<div class="bbcode-quote">$1</div>', $message) ?? $message;
        }

        $message = str_replace('[br]', '<br>', $message);

        if ($allowImages) {
            $imageReplacements = [
                '/\[img=(.*?)\](.*?)\[\/img\]/is' => '<img alt="$1" src="$2"/>',
                '/\[img height=&#039;(.*?)&#039; width=&#039;(.*?)&#039;\](.*?)\[\/img\]/is' => '<img height="$1" width="$2" src="$3"/>',
                '/\[img\](.*?)\[\/img\]/is' => '<img src="$1"/>',
                '/\[article_images\](.*?)\[\/article_images\]/is' => '<div class="article-images clearfix">$1</div>',
                '/\[article_image\](.*?)\[\/article_image\]/is' => '<a href="$1" style="background-image: url($1); background-position: -0px -0px"></a>',
                '/\[article_image x=(.*?) y=(.*?)\](.*?)\[\/article_image\]/is' => '<a href="$3" style="background-image: url($3); background-position: $1 $2"></a>',
                '/\[center\](.*?)\[\/center\]/is' => '<center>$1</center>',
            ];
            $message = preg_replace(array_keys($imageReplacements), array_values($imageReplacements), $message) ?? $message;
            $message = str_replace('<br><br>', '<br>', $message);
        }

        return $message;
    }
}
