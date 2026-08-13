<?php

declare(strict_types=1);

namespace Internal\Path\Tests\Unit\Stub;

/**
 * Replacement for the built-in `file://` stream wrapper that denies every stat and open request.
 *
 * Registered for the duration of a single call to prove that a {@see \Internal\Path} method answers
 * from the path value alone instead of consulting the real filesystem. Method names are snake_case
 * because the streams layer looks them up by those exact names.
 */
final class DenyingFileWrapper
{
    /** @var resource|null Assigned by the streams layer. */
    public $context;

    public function url_stat(string $path, int $flags): false
    {
        return false;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): false
    {
        return false;
    }

    public function dir_opendir(string $path, int $options): false
    {
        return false;
    }
}
