<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyGuestbookEntry
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly int $homeId,
        private readonly int $groupId,
        private readonly string $message,
        private readonly mixed $createdAt,
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            (int) $row->user_id,
            (int) $row->home_id,
            (int) $row->group_id,
            (string) $row->message,
            $row->created_at ?? null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUser(): ?LegacyUserData
    {
        $user = User::query()->find($this->userId);

        return $user ? new LegacyUserData($user) : null;
    }

    public function getHomeId(): int
    {
        return $this->homeId;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getCreationDate(): string
    {
        if ($this->createdAt === null || $this->createdAt === '') {
            return '';
        }

        return Carbon::parse($this->createdAt)->format('M j, Y g:i:s A');
    }

    public function getMessage(): string
    {
        return self::formatMessage($this->message);
    }

    public static function create(int $userId, int $homeId, int $groupId, string $message): self
    {
        $createdAt = now();
        $id = DB::table('cms_guestbook_entries')->insertGetId([
            'user_id' => $userId,
            'home_id' => $homeId,
            'group_id' => $groupId,
            'message' => $message,
            'created_at' => $createdAt,
        ]);

        return new self((int) $id, $userId, $homeId, $groupId, $message, $createdAt);
    }

    public static function formatMessage(string $message): string
    {
        return self::formatLegacyMarkup(LegacyWordfilter::filterSentence($message));
    }

    public static function formatPreviewMessage(string $message): string
    {
        return mb_substr(self::formatLegacyMarkup($message), 0, 200);
    }

    private static function formatLegacyMarkup(string $message): string
    {
        $message = str_replace("\r", "\n", $message);
        $message = str_replace(["[/quote]\n\n", "[/quote]\n"], '[/quote]', $message);
        $message = str_replace("\n", '[br]', $message);
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

        return preg_replace(array_keys($replacements), array_values($replacements), $message) ?? $message;
    }
}
