<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingInfobusPollView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HousekeepingInfobusController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function polls(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/infobus_polls', $staff, [
            'pageName' => 'View Infobus Polls',
            'infobusPolls' => $this->pollsList(),
        ]);
    }

    public function create(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            DB::table('infobus_polls')->insert([
                'initiated_by' => $staff->id,
                'poll_data' => $this->pollDataJson($request),
                'created_at' => now(),
            ]);

            $this->alert($request, 'Infobus poll has been created successfully', 'success');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        return $this->render($template, 'housekeeping/infobus_polls_create', $staff, [
            'pageName' => 'Create Infobus Poll',
            'oneHourLater' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);
    }

    public function edit(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->query('id') === null) {
            $this->alert($request, 'There was no infobus poll selected to edit', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        $poll = $this->poll($this->integerQuery($request, 'id') ?? 0);

        if ($poll === null) {
            $this->alert($request, 'The infobus poll does not exist', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        if ($request->isMethod('post')) {
            if ($this->answersTotal($poll->getId()) > 0) {
                $this->alert($request, "You can't edit the poll if it has answers", 'danger');

                return redirect($this->housekeepingUrl('/infobus_polls'));
            } else {
                DB::table('infobus_polls')->where('id', $poll->getId())->update([
                    'poll_data' => $this->pollDataJson($request),
                ]);

                $this->alert($request, 'The infobus poll was successfully saved', 'success');

                return redirect($this->housekeepingUrl('/infobus_polls'));
            }
        }

        return $this->render($template, 'housekeeping/infobus_polls_edit', $staff, [
            'pageName' => 'Edit Infobus Poll',
            'poll' => $poll,
        ]);
    }

    public function delete(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'infobus/delete_own');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $poll = $this->poll($this->integerQuery($request, 'id') ?? 0);

        if ($poll === null) {
            $this->alert($request, 'The infobus poll does not exist', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        if ($poll->getInitiatedBy() !== (int) $staff->id && ! (new HousekeepingManagerView)->hasPermission((int) $staff->rank, 'infobus/delete_any')) {
            $this->alert($request, 'No permission to delete other polls', 'danger');

            return $this->redirectToHousekeeping();
        }

        if ($this->answersTotal($poll->getId()) > 0) {
            $this->alert($request, "You can't delete a poll with answers", 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        DB::table('infobus_polls')->where('id', $poll->getId())->delete();
        DB::table('infobus_polls_answers')->where('poll_id', $poll->getId())->delete();
        $this->alert($request, 'Successfully deleted the infobus poll', 'success');

        return redirect($this->housekeepingUrl('/infobus_polls'));
    }

    public function viewResults(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->query('id') === null) {
            $this->alert($request, 'There was no infobus poll selected to edit', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        $poll = $this->poll($this->integerQuery($request, 'id') ?? 0);

        if ($poll === null) {
            $this->alert($request, 'The infobus poll does not exist', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        return $this->render($template, 'housekeeping/infobus_polls_view', $staff, [
            'pageName' => 'View Infobus Poll Results',
            'poll' => $poll,
            'imageData' => 'data:image/png;base64,'.base64_encode($this->chartPlaceholder()),
            'noAnswers' => $this->answersTotal($poll->getId()) === 0,
        ]);
    }

    public function clearResults(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->query('id') === null) {
            $this->alert($request, 'There was no infobus poll selected to edit', 'danger');

            return redirect($this->housekeepingUrl('/infobus_polls'));
        }

        $poll = $this->poll($this->integerQuery($request, 'id') ?? 0);

        if ($poll === null) {
            $this->alert($request, 'The infobus poll does not exist', 'danger');
        } else {
            DB::table('infobus_polls_answers')->where('poll_id', $poll->getId())->delete();
            $this->alert($request, 'The infobus poll has had all answers cleared', 'success');
        }

        return redirect($this->housekeepingUrl('/infobus_polls'));
    }

    public function sendPoll(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($this->poll($this->integerQuery($request, 'id') ?? 0) === null) {
            $this->alert($request, 'The infobus poll does not exist', 'danger');
        } else {
            $this->alert($request, 'The infobus poll request has been sent', 'warning');
        }

        return redirect($this->housekeepingUrl('/infobus_polls'));
    }

    public function closeEvent(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $this->alert($request, 'The infobus status has been sent', 'success');

        return redirect($this->housekeepingUrl('/infobus_polls'));
    }

    public function doorStatus(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'infobus');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $this->alert($request, 'The infobus door status has been sent', 'success');

        return redirect($this->housekeepingUrl('/infobus_polls'));
    }

    private function requirePermission(Request $request, string $permission): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, $permission)) {
            return $this->redirectToHousekeeping();
        }

        return $user;
    }

    private function currentHousekeepingUser(Request $request): ?User
    {
        if (! $request->session()->get(self::SESSION_KEY, false)) {
            return null;
        }

        $userId = (int) $request->session()->get(self::USER_ID_KEY, 0);

        return $userId > 0 ? User::query()->find($userId) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function render(LegacyTemplate $template, string $view, User $staff, array $context): Response
    {
        $html = $template->render($view, array_merge([
            'housekeepingManager' => new HousekeepingManagerView,
            'playerDetails' => new HousekeepingPlayerView($staff),
        ], $context));

        request()->session()->forget('alertMessage');

        return response($html);
    }

    private function redirectToHousekeeping(): RedirectResponse
    {
        return redirect($this->housekeepingUrl());
    }

    private function housekeepingUrl(string $suffix = ''): string
    {
        return '/'.trim((string) config('havana.housekeeping_path'), '/').$suffix;
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    /** @return list<HousekeepingInfobusPollView> */
    private function pollsList(): array
    {
        return DB::table('infobus_polls')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (object $row): HousekeepingInfobusPollView => new HousekeepingInfobusPollView($row))
            ->all();
    }

    private function poll(int $id): ?HousekeepingInfobusPollView
    {
        $row = DB::table('infobus_polls')->where('id', $id)->first();

        return $row !== null ? new HousekeepingInfobusPollView($row) : null;
    }

    private function integerQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function answersTotal(int $pollId): int
    {
        return (int) DB::table('infobus_polls_answers')->where('poll_id', $pollId)->count();
    }

    private function pollDataJson(Request $request): string
    {
        $answers = $request->input('answers', $request->input('answers[]', []));
        $answers = is_array($answers) ? $answers : [$answers];
        $answers = array_map(
            fn (mixed $answer): string => (string) $answer,
            array_values($answers)
        );

        return json_encode([
            'question' => (string) $request->input('question', ''),
            'answers' => $answers,
        ], JSON_THROW_ON_ERROR);
    }

    private function chartPlaceholder(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true) ?: '';
    }
}
