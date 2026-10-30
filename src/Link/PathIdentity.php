<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker\Link;

final class PathIdentity
{
    public function identity(string $path, ?string $relativeTo = null): string
    {
        clearstatcache(true);
        if (!str_starts_with($path, '/')) {
            $base = $relativeTo ?? getcwd();
            $base = realpath($base) ?: $this->normalize($base);
            $path = rtrim($base, '/') . '/' . $path;
        }
        if (($real = realpath($path)) !== false) {
            return $this->normalize($real);
        }

        $parts = explode('/', trim($path, '/'));
        for ($count = count($parts) - 1; $count >= 0; --$count) {
            $prefix = '/' . implode('/', array_slice($parts, 0, $count));
            $realPrefix = realpath($prefix === '' ? '/' : $prefix);
            if ($realPrefix !== false) {
                $suffix = array_slice($parts, $count);
                return $this->normalize(rtrim($realPrefix, '/') . '/' . implode('/', $suffix));
            }
        }
        return $this->normalize($path);
    }

    public function entryIdentity(string $path): string
    {
        $parent = realpath(dirname($path));
        if ($parent === false) {
            $parent = $this->identity(dirname($path));
        }
        return $this->normalize($parent . '/' . basename($path));
    }

    public function normalize(string $absolutePath): string
    {
        $parts = [];
        foreach (explode('/', $absolutePath) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return '/' . implode('/', $parts);
    }
}
