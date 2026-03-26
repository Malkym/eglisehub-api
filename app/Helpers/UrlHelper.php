<?php

namespace App\Helpers;

class UrlHelper
{
    public static function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        if (str_starts_with($path, '/storage')) {
            return config('app.url') . $path;
        }
        return config('app.url') . '/storage/' . ltrim($path, '/');
    }
}
