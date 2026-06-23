<?php

namespace App\Support;

final class RelativeUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        return route($name, $parameters, absolute: false);
    }

    public static function normalize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "{$path}?{$query}" : $path;
    }
}
