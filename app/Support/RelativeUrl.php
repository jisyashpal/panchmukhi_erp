<?php

namespace App\Support;

final class RelativeUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        return route($name, $parameters);
    }

    public static function normalize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $basePath = request()->getBasePath();

        if (str_starts_with($url, '/')) {
            if ($basePath !== '' && str_starts_with($url, $basePath)) {
                $relative = substr($url, strlen($basePath));
                return $relative !== '' ? $relative : '/';
            }

            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
            if ($path === '') {
                $path = '/';
            }
        }

        return $query ? "{$path}?{$query}" : $path;
    }
}

