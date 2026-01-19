<?php

namespace Digitonic\Photonic\Filament\Support;

use RuntimeException;

class EnvFileEditor
{
    /**
     * Get a value from an env file (best-effort).
     */
    public function get(string $path, string $key): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents);
        $lines = is_array($lines) ? $lines : [];

        foreach ($lines as $line) {
            $trimmed = ltrim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^(export\s+)?'.preg_quote($key, '/').'\s*=(.*)$/', $trimmed, $m) !== 1) {
                continue;
            }

            $raw = trim($m[2]);

            // Strip inline comments (#) if not quoted.
            if ($raw !== '' && ! str_starts_with($raw, '"') && ! str_starts_with($raw, "'")) {
                $raw = preg_split('/\s+#/', $raw, 2)[0] ?? $raw;
                $raw = trim($raw);
            }

            // Unquote simple quoted values.
            if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
                $raw = substr($raw, 1, -1);
                $raw = str_replace('\\"', '"', $raw);
                return $raw;
            }

            if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
                return substr($raw, 1, -1);
            }

            return $raw;
        }

        return null;
    }

    /**
     * Upsert KEY=VALUE pairs into an env file.
     *
     * - If the file doesn't exist and createIfMissing is false, throws.
     * - If a key exists and $force is false, it is left untouched.
     * - If a key doesn't exist, it's appended at the end.
     */
    public function upsert(string $path, array $values, bool $force = false, bool $createIfMissing = true): void
    {
        if (! file_exists($path)) {
            if (! $createIfMissing) {
                throw new RuntimeException("Env file not found at [$path].");
            }

            file_put_contents($path, "");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read env file at [$path].");
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents);
        $lines = is_array($lines) ? $lines : [];

        $remaining = $values;

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);

            // Skip comments / empty lines.
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Match KEY=... (allow export KEY=...)
            if (preg_match('/^(export\s+)?([A-Z0-9_]+)\s*=/', $trimmed, $m) !== 1) {
                continue;
            }

            $key = $m[2];

            if (! array_key_exists($key, $remaining)) {
                continue;
            }

            if (! $force) {
                unset($remaining[$key]);
                continue;
            }

            $lines[$i] = $key.'='.$this->encodeValue($remaining[$key]);
            unset($remaining[$key]);
        }

        if (! empty($remaining)) {
            // Ensure the file ends with a blank line before appending, for readability.
            if (! empty($lines) && trim((string) end($lines)) !== '') {
                $lines[] = '';
            }

            foreach ($remaining as $key => $value) {
                $lines[] = $key.'='.$this->encodeValue($value);
            }
        }

        $newContents = implode(PHP_EOL, $lines);
        if (! str_ends_with($newContents, PHP_EOL)) {
            $newContents .= PHP_EOL;
        }

        file_put_contents($path, $newContents);
    }

    private function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // Quote if contains spaces or env-special characters.
        if ($value === '' || preg_match('/\s|#|"|\'|=/', $value)) {
            $escaped = str_replace('"', '\\"', $value);
            return '"'.$escaped.'"';
        }

        return $value;
    }
}
