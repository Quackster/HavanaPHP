<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyMinimailRepository;
use App\Support\LegacyMinimailText;
use App\Support\LegacyWordfilter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MinimailController extends Controller
{
    public function __construct(
        private readonly LegacyMinimailRepository $minimailRepository,
    ) {}

    public function loadMessages(Request $request, LegacyTemplate $template): Response
    {
        $this->clearXssKey($request);

        if (! $this->currentUser($request)) {
            return response('');
        }

        return $this->messagesResponse($request, $template);
    }

    public function clientHabblet(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $label = 'inbox';
        $request->session()->put('minimailLabel', $label);

        return response($template->render('habblet/minimail', $this->minimailRepository->messagesContext(
            $user->id,
            $label,
            0,
            0,
            false,
            true,
        )));
    }

    public function recipients(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $friends = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $user->id)
            ->orderBy('users.username')
            ->get(['users.id', 'users.username'])
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->username])
            ->values()
            ->all();

        return response("/*-secure-\n".json_encode($friends, JSON_THROW_ON_ERROR)."\n */");
    }

    public function preview(Request $request): Response
    {
        if (! $this->currentUser($request)) {
            return response('');
        }

        return response(LegacyMinimailText::format((string) $request->input('body', '')));
    }

    public function sendMessage(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $statistics = DB::table('users_statistics')->where('user_id', $user->id)->first();
        $isMuted = $statistics && (int) $statistics->mute_expires_at > time();

        if (! $isMuted) {
            if ($request->filled('recipientIds')) {
                $this->sendNewMessages($request, $user);
            } elseif ($request->filled('messageId')) {
                $this->sendReply($request, $user);
            }
        }

        return $this->messagesResponse($request, $template, [
            'messageSent' => true,
            'isMuted' => $isMuted,
        ]);
    }

    public function loadMessage(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $messageIdInput = (string) $request->query('messageId', '');

        if (! preg_match('/^-?\d+$/', $messageIdInput)) {
            return response('1');
        }

        $messageId = (int) $messageIdInput;

        if ($messageId < 1) {
            return response('2');
        }

        $row = $this->messageForUser($messageId, $user->id);

        if (! $row) {
            return response('');
        }

        DB::table('cms_minimail')->where('id', $messageId)->update(['is_read' => true]);
        $messages = $this->minimailRepository->hydrateMessages(collect([$row->id => $row]));
        $message = $messages[0] ?? null;

        if ($message === null) {
            return response('');
        }

        return response($template->render('habblet/minimail/minimail_load_message', [
            'minimailLabel' => (string) $request->session()->get('minimailLabel', 'inbox'),
            'minimailMessage' => $message,
        ]));
    }

    public function deleteMessage(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $messageId = $this->integerInput($request, 'messageId') ?? 0;
        $row = $messageId > 0 ? $this->messageForUser($messageId, $user->id) : null;

        if (! $row) {
            return response('');
        }

        if (! (bool) $row->is_trash) {
            DB::table('cms_minimail')->where('id', $messageId)->update(['is_trash' => true]);
        } else {
            DB::table('cms_minimail')
                ->where('id', $messageId)
                ->where('target_id', (int) $row->target_id)
                ->update(['is_deleted' => true]);
        }

        return $this->messagesResponse($request, $template, ['messageDeleted' => true]);
    }

    public function undeleteMessage(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $messageId = $this->integerInput($request, 'messageId') ?? 0;
        $row = $messageId > 0 ? $this->messageForUser($messageId, $user->id) : null;

        if (! $row) {
            return response('');
        }

        DB::table('cms_minimail')->where('id', $messageId)->update(['is_trash' => false]);

        return $this->messagesResponse($request, $template, ['messageUndeleted' => true]);
    }

    public function emptyTrash(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        DB::table('cms_minimail')
            ->where('target_id', $user->id)
            ->where('is_trash', true)
            ->update(['is_deleted' => true]);

        return $this->messagesResponse($request, $template, ['trashEmptied' => true]);
    }

    /** @param array<string, bool> $flags */
    private function messagesResponse(Request $request, LegacyTemplate $template, array $flags = []): Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        if (! $this->hasIntegerInput($request, 'start') || ! $this->hasBooleanInput($request, 'unreadOnly')) {
            return response('');
        }

        $label = (string) $request->input('label', '');
        if ($label === '') {
            $label = (string) $request->session()->get('minimailLabel', 'inbox');
            if ($label === 'conversation' && ! $request->filled('conversationId')) {
                $label = 'inbox';
            }
        }

        $start = (int) $request->input('start', 0);
        $unreadOnly = filter_var($request->input('unreadOnly', false), FILTER_VALIDATE_BOOL);
        $request->session()->put('minimailLabel', $label);
        $context = $this->minimailRepository->messagesContext(
            $user->id,
            $label,
            $this->integerInput($request, 'conversationId') ?? 0,
            $start,
            $unreadOnly,
            false,
        );
        $total = $context['totalMessages'];

        $response = response($template->render('habblet/minimail/minimail_messages', $context));

        $message = null;
        if (($flags['messageSent'] ?? false) && ($flags['isMuted'] ?? false)) {
            $message = 'You are muted and cannot send messages.';
        } elseif ($flags['messageSent'] ?? false) {
            $message = 'Message sent successfully.';
        } elseif ($flags['messageDeleted'] ?? false) {
            $message = 'The message has been moved to the trash. You can undelete it, if you wish';
        } elseif ($flags['messageUndeleted'] ?? false) {
            $message = 'Message undeleted';
        } elseif ($flags['trashEmptied'] ?? false) {
            $message = 'The trash has been emptied. Good Job!';
        }

        $json = ['totalMessages' => $total];
        if ($message !== null) {
            $json = ['message' => $message, 'totalMessages' => $total];
        }

        return $response->header('X-JSON', json_encode($json, JSON_THROW_ON_ERROR));
    }

    private function messageForUser(int $messageId, int $userId): ?object
    {
        return DB::table('cms_minimail')
            ->where('id', $messageId)
            ->where(function ($query) use ($userId): void {
                $query->where('target_id', $userId)->orWhere('sender_id', $userId);
            })
            ->first();
    }

    private function sendNewMessages(Request $request, User $user): void
    {
        $subject = (string) $request->input('subject', '');
        $message = (string) $request->input('body', '');

        if (LegacyWordfilter::filterSentence($message) !== $message) {
            return;
        }

        $friendIds = DB::table('messenger_friends')
            ->where('to_id', $user->id)
            ->pluck('from_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach (explode(',', (string) $request->input('recipientIds', '')) as $recipient) {
            if (preg_match('/^\d+$/', $recipient) !== 1) {
                continue;
            }

            $toId = (int) $recipient;

            if ($toId < 1 || ! in_array($toId, $friendIds, true)) {
                continue;
            }

            $this->insertPair($user->id, $toId, $subject, $message, 0);
        }
    }

    private function sendReply(Request $request, User $user): void
    {
        $messageId = $this->integerInput($request, 'messageId') ?? 0;
        $row = $messageId > 0 ? $this->messageForUser($messageId, $user->id) : null;

        if (! $row) {
            return;
        }

        $conversationId = (int) $row->id;
        $message = (string) $request->input('body', '');

        if (LegacyWordfilter::filterSentence($message) !== $message) {
            return;
        }

        DB::table('cms_minimail')->where('id', $row->id)->update(['conversation_id' => $conversationId]);
        $this->insertPair($user->id, (int) $row->sender_id, 'Re: '.(string) $row->subject, $message, $conversationId);
    }

    private function insertPair(int $senderId, int $toId, string $subject, string $message, int $conversationId): void
    {
        DB::table('cms_minimail')->insert([
            [
                'target_id' => $senderId,
                'sender_id' => $senderId,
                'to_id' => $toId,
                'subject' => $subject,
                'message' => $message,
                'conversation_id' => $conversationId,
                'date_sent' => now(),
            ],
            [
                'target_id' => $toId,
                'sender_id' => $senderId,
                'to_id' => $toId,
                'subject' => $subject,
                'message' => $message,
                'conversation_id' => $conversationId,
                'date_sent' => now(),
            ],
        ]);
    }

    private function currentUser(Request $request): ?User
    {
        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }

    private function hasIntegerInput(Request $request, string $key): bool
    {
        return $this->integerInput($request, $key) !== null;
    }

    private function hasBooleanInput(Request $request, string $key): bool
    {
        $value = $request->input($key);

        if (is_bool($value)) {
            return true;
        }

        return is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true);
    }

    private function clearXssKey(Request $request): void
    {
        $request->session()->forget(['xssKey', 'xssSeed', 'xssRequested']);
    }

    private function integerInput(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
