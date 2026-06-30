<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HavanaConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ImagingController extends Controller
{
    public function avatarImage(Request $request, HavanaConfig $config): Response
    {
        return $this->proxyOrEmpty($request, $config, 'png');
    }

    public function badge(Request $request, HavanaConfig $config, string $path): Response
    {
        return $this->proxyOrEmpty($request, $config, str_ends_with($path, '.png') ? 'png' : 'gif');
    }

    public function badgeFill(Request $request, HavanaConfig $config, string $path): Response
    {
        return $this->proxyOrEmpty($request, $config, str_ends_with($path, '.png') ? 'png' : 'gif');
    }

    private function proxyOrEmpty(Request $request, HavanaConfig $config, string $extension): Response
    {
        $endpoint = rtrim($config->string('site.imaging.endpoint', ''), '/');

        if ($endpoint !== '') {
            try {
                $proxy = Http::timeout($this->timeoutSeconds($config))
                    ->withHeader('User-Agent', 'Imager')
                    ->get($endpoint.'/'.$request->path(), $request->query());

                if ($proxy->successful()) {
                    return response($proxy->body(), 200)
                        ->header('Content-Type', $proxy->header('Content-Type') ?: $this->contentType($extension));
                }
            } catch (ConnectionException) {
                // Fall through to the legacy no-content image response.
            }
        }

        return response('', 204)->header('Content-Type', $this->contentType($extension));
    }

    private function contentType(string $extension): string
    {
        return $extension === 'gif' ? 'image/gif' : 'image/png';
    }

    private function timeoutSeconds(HavanaConfig $config): int
    {
        $timeout = $config->integer('site.imaging.endpoint.timeout');

        if ($timeout >= 1000) {
            return max(1, (int) ceil($timeout / 1000));
        }

        return max(1, $timeout ?: 30);
    }
}
