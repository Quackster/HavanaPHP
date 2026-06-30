<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyTraxWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TraxController extends Controller
{
    public function selectSong(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $row = $widgetId !== null
            ? DB::table('cms_stickers')
                ->join('cms_stickers_catalogue', 'cms_stickers_catalogue.id', '=', 'cms_stickers.sticker_id')
                ->where('cms_stickers.id', $widgetId)
                ->where('cms_stickers_catalogue.data', 'traxplayerwidget')
                ->first(['cms_stickers.*'])
            : null;

        if (! $row) {
            return response('');
        }

        $widget = new LegacyTraxWidget($row);

        $ownerId = $widget->ownerId();

        if ($ownerId !== (int) $user->id) {
            return response('');
        }

        $songId = $this->integerInput($request, 'songId') ?? -1;
        $songExists = collect($widget->getSongs())
            ->contains(fn ($song): bool => $song->getId() === $songId);
        $extraData = $songId > 0 && $songExists ? (string) $songId : '';

        DB::table('cms_stickers')->where('id', (int) $row->id)->update(['extra_data' => $extraData]);
        $row = DB::table('cms_stickers')->where('id', (int) $row->id)->first();

        return response($template->render('homes/widget/habblet/trax_song', [
            'sticker' => new LegacyTraxWidget($row),
        ]));
    }

    public function getSong(string $song): Response
    {
        if (! ctype_digit($song)) {
            return response('');
        }

        $row = DB::table('soundmachine_songs')->where('id', (int) $song)->first();

        if (! $row) {
            return response('');
        }

        $data = (string) $row->data;
        $data = $data !== '' ? substr($data, 0, -1) : '';
        $trackData = str_replace([':4:', ':3:', ':2:', '1:'], ['&track4=', '&track3=', '&track2=', '&track1='], $data);
        $author = (string) DB::table('users')->where('id', (int) $row->user_id)->value('username');

        return response('status=0&name='.(string) $row->title.'&author='.$author.$trackData);
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

    private function currentUser(Request $request): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return User::query()->find((int) $user->id);
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }
}
