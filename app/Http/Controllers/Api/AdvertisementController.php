<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdvertisementController extends Controller
{
    public function getImg(Request $request): Response|RedirectResponse
    {
        return $this->redirectField($request, 'image');
    }

    public function getUrl(Request $request): Response|RedirectResponse
    {
        return $this->redirectField($request, 'url');
    }

    private function redirectField(Request $request, string $field): Response|RedirectResponse
    {
        if (! $request->has('ad')) {
            return response('');
        }

        $value = DB::table('rooms_ads')
            ->where('id', (int) $request->query('ad'))
            ->value($field);

        if (! is_string($value) || $value === '') {
            return response('');
        }

        return redirect()->away($value);
    }
}
