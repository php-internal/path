<?php

declare(strict_types=1);

namespace Internal\Path\Tests\Unit\Stub;

/**
 * Mirror image of {@see DenyingFileWrapper}: replaces the built-in `file://` stream wrapper and reports
 * every path as an existing regular file.
 *
 * Registered for the duration of a single call to prove that a {@see \Internal\Path} method answers from the
 * path value alone instead of consulting the real filesystem. Method names are snake_case because the
 * streams layer looks them up by those exact names.
 */
final class ClaimingFileWrapper
{
    /** @var resource|null Assigned by the streams layer. */
    public $context;

    /**
     * The `mode` field carries the `S_IFREG` bit (`0100000`), which is what makes `is_file()` answer true.
     *
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
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
