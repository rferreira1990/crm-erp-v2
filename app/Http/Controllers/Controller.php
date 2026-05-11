<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @param list<string> $allowedPrefixes
     */
    protected function assertAllowedLocalStoragePath(?string $path, array $allowedPrefixes): string
    {
        $normalizedPath = trim((string) $path);
        $normalizedPath = ltrim($normalizedPath, '/');

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            $normalizedPrefix = trim((string) $prefix, '/');
            if ($normalizedPrefix === '') {
                continue;
            }

            if ($normalizedPath === $normalizedPrefix || str_starts_with($normalizedPath, $normalizedPrefix.'/')) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed || ! Storage::disk('local')->exists($normalizedPath)) {
            abort(404);
        }

        return $normalizedPath;
    }

    /**
     * @param list<string> $allowedPrefixes
     */
    protected function localDiskResponse(string $path, string $filename, array $allowedPrefixes): StreamedResponse
    {
        $safePath = $this->assertAllowedLocalStoragePath($path, $allowedPrefixes);

        return Storage::disk('local')->response($safePath, $filename);
    }

    /**
     * @param list<string> $allowedPrefixes
     */
    protected function localDiskDownload(string $path, string $filename, array $allowedPrefixes): StreamedResponse
    {
        $safePath = $this->assertAllowedLocalStoragePath($path, $allowedPrefixes);

        return Storage::disk('local')->download($safePath, $filename);
    }
}
