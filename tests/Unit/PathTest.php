<?php

declare(strict_types=1);

namespace Internal\Path\Tests\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(Path::class)]
final class PathTest
{
    public static function providePathsForAbsoluteDetection(): \Generator
    {
        yield 'windows absolute path' => ['C:/Users/test', true];
        yield 'windows drive letter' => ['C:', true];
        yield 'windows relative path' => ['Users/test', false];
        yield 'windows implicit relative' => ['./test', false];
        yield 'unix absolute path' => ['/home/user', true];
        yield 'unix relative path' => ['home/user', false];
        yield 'unix implicit relative' => ['./test', false];
        yield 'dot path' => ['.', false];
        yield 'double dot path' => ['..', false];
    }

    public static function providePathsForParent(): \Generator
    {
        yield ['.', '..'];
        yield ['..', '../..'];
        yield ['path/to/..', '.'];
        yield ['/home', '/.'];
        yield ['C:/Users', 'C:/.'];
        yield ['C:/.', 'C:/.'];
        yield ['filename.txt', '.'];
        yield ['some/path/file.txt', 'some/path'];
    }

    public static function providePathsForMatch(): \Generator
    {
        yield 'exact match' => ['test/file.txt', 'test/file.txt', true];
        yield 'wildcard asterisk matches multiple chars' => ['test/file.txt', 'test/*.txt', true];
        yield 'wildcard asterisk at start' => ['test/file.txt', '*/file.txt', true];
        yield 'wildcard asterisk in middle' => ['test/some/file.txt', 'test/*/file.txt', true];
        yield 'multiple wildcards' => ['test/path/file.txt', 'test/*/*.txt', true];
        yield 'wildcard question mark matches single char' => ['test/file1.txt', 'test/file?.txt', true];
        yield 'character class matches' => ['test/file1.txt', 'test/file[123].txt', true];
        yield 'character class does not match' => ['test/file4.txt', 'test/file[123].txt', false];
        yield 'no match different extension' => ['test/file.php', 'test/*.txt', false];
        yield 'no match different path' => ['other/file.txt', 'test/*.txt', false];
        yield 'wildcard double asterisk simulation' => ['test/deep/nested/file.txt', 'test/*/nested/*.txt', true];
        yield 'pattern with no wildcards no match' => ['test/file.txt', 'test/other.txt', false];
    }

    public static function provideAbsoluteWithCwd(): \Generator
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';

        yield 'relative path with absolute cwd' => [
            'relativePath' => 'src/Controller.php',
            'cwd' => $isWindows ? 'C:/Projects/myapp' : '/var/www/myapp',
            'expected' => $isWindows ? 'C:/Projects/myapp/src/Controller.php' : '/var/www/myapp/src/Controller.php',
        ];

        yield 'relative path with relative cwd' => [
            'relativePath' => 'lib/helpers.php',
            'cwd' => 'project/app',
            'usesCurrentDir' => true, // needs special handling
        ];

        yield 'absolute path with matching cwd' => [
            'relativePath' => $isWindows ? 'C:/var/www/app/src/file.php' : '/var/www/app/src/file.php',
            'cwd' => $isWindows ? 'C:/var/www/app' : '/var/www/app',
            'expected' => $isWindows ? 'C:/var/www/app/src/file.php' : '/var/www/app/src/file.php',
        ];

        yield 'absolute path with parent matching cwd' => [
            'relativePath' => $isWindows ? 'C:/var/www/app/src/deep/file.php' : '/var/www/app/src/deep/file.php',
            'cwd' => $isWindows ? 'C:/var/www' : '/var/www',
            'expected' => $isWindows ? 'C:/var/www/app/src/deep/file.php' : '/var/www/app/src/deep/file.php',
        ];
    }

    public static function provideAbsoluteWithCwdErrors(): \Generator
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';

        yield 'absolute path with non-matching cwd' => [
            'absolutePath' => $isWindows ? 'C:/var/www/app/src/file.php' : '/var/www/app/src/file.php',
            'cwd' => $isWindows ? 'C:/home/user' : '/home/user',
        ];

        yield 'absolute path with partial matching cwd' => [
            'absolutePath' => $isWindows ? 'C:/var/www/application/src/file.php' : '/var/www/application/src/file.php',
            'cwd' => $isWindows ? 'C:/var/www/app' : '/var/www/app',
        ];
    }

    public static function provideTryRelative(): \Generator
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';
        $root = $isWindows ? 'C:' : '';

        yield 'path inside base' => [
            'path' => "$root/var/www/app/src/file.php",
            'base' => "$root/var/www/app",
            'expected' => 'src/file.php',
        ];

        yield 'path directly in base' => [
            'path' => "$root/var/www/app/file.php",
            'base' => "$root/var/www/app",
            'expected' => 'file.php',
        ];

        yield 'path equals base' => [
            'path' => "$root/var/www/app",
            'base' => "$root/var/www/app",
            'expected' => '.',
        ];

        yield 'sibling directory' => [
            'path' => "$root/var/www/other",
            'base' => "$root/var/www/app",
            'expected' => '../other',
        ];

        yield 'parent of base' => [
            'path' => "$root/var/www",
            'base' => "$root/var/www/app/src",
            'expected' => '../..',
        ];

        yield 'deep traversal upwards' => [
            'path' => "$root/var/log/nginx/error.log",
            'base' => "$root/var/www/app/src",
            'expected' => '../../../log/nginx/error.log',
        ];

        yield 'base is root' => [
            'path' => "$root/var/www",
            'base' => "$root/",
            'expected' => 'var/www',
        ];

        yield 'path is root and base is root' => [
            'path' => "$root/",
            'base' => "$root/",
            'expected' => '.',
        ];

        yield 'path is root and base is nested' => [
            'path' => "$root/",
            'base' => "$root/var/www",
            'expected' => '../..',
        ];

        yield 'partially matching segment is not a prefix' => [
            'path' => "$root/var/www/application/src",
            'base' => "$root/var/www/app",
            'expected' => '../application/src',
        ];
    }

    public static function provideTryRelativeUnrelatedRoots(): \Generator
    {
        yield 'different windows drives' => [
            'path' => 'D:/var/www/app',
            'base' => 'C:/var/www/app',
        ];

        yield 'unix root against windows drive' => [
            'path' => '/var/www/app',
            'base' => 'C:/var/www/app',
        ];

        yield 'windows drive against unix root' => [
            'path' => 'C:/var/www/app',
            'base' => '/var/www/app',
        ];
    }

    public static function provideIsWithin(): \Generator
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';
        $root = $isWindows ? 'C:' : '';

        yield 'path directly in base' => [
            'path' => "$root/var/www/app/file.php",
            'base' => "$root/var/www/app",
            'expected' => true,
        ];

        yield 'path deep inside base' => [
            'path' => "$root/var/www/app/src/deep/file.php",
            'base' => "$root/var/www/app",
            'expected' => true,
        ];

        yield 'path equals base' => [
            'path' => "$root/var/www/app",
            'base' => "$root/var/www/app",
            'expected' => true,
        ];

        yield 'sibling directory' => [
            'path' => "$root/var/www/other",
            'base' => "$root/var/www/app",
            'expected' => false,
        ];

        yield 'parent of base' => [
            'path' => "$root/var/www",
            'base' => "$root/var/www/app",
            'expected' => false,
        ];

        yield 'partially matching segment' => [
            'path' => "$root/var/www-old",
            'base' => "$root/var/www",
            'expected' => false,
        ];

        yield 'partially matching segment with children' => [
            'path' => "$root/var/www-old/app/file.php",
            'base' => "$root/var/www",
            'expected' => false,
        ];

        yield 'base is root' => [
            'path' => "$root/var/www/app",
            'base' => "$root/",
            'expected' => true,
        ];

        yield 'root is within root' => [
            'path' => "$root/",
            'base' => "$root/",
            'expected' => true,
        ];

        yield 'root is not within nested base' => [
            'path' => "$root/",
            'base' => "$root/var",
            'expected' => false,
        ];

        yield 'different windows drives' => [
            'path' => 'D:/var/www/app',
            'base' => 'C:/var/www',
            'expected' => false,
        ];

        yield 'unix root against windows drive' => [
            'path' => '/var/www/app',
            'base' => 'C:/var/www',
            'expected' => false,
        ];

        yield 'windows drive against unix root' => [
            'path' => 'C:/var/www/app',
            'base' => '/var/www',
            'expected' => false,
        ];
    }

    public function testCreateReturnsPathInstance(): void
    {
        $path = Path::create('test/path');

        Assert::instanceOf($path, Path::class);
    }

    public function testCreateWithEmptyPathReturnsCurrentDirectory(): void
    {
        $path = Path::create('');

        Assert::same((string) $path, '.');
    }

    public function testCreateNormalizesDirectorySeparators(): void
    {
        $path = Path::create('test\\path/mixed/separators\\here');

        Assert::same((string) $path, 'test/path/mixed/separators/here');
    }

    public function testCreateRemovesMultipleSeparators(): void
    {
        $path = Path::create('test//path///extra//separators');

        Assert::same((string) $path, 'test/path/extra/separators');
    }

    public function testCreateResolvesCurrentDirectorySegments(): void
    {
        $path = Path::create('test/./path/./current');

        Assert::same((string) $path, 'test/path/current');
    }

    public function testCreateResolvesParentDirectorySegments(): void
    {
        $path = Path::create('test/parent/../path');

        Assert::same((string) $path, 'test/path');
    }

    public function testCreateThrowsExceptionForInvalidParentNavigation(): never
    {
        Expect::exception(\LogicException::class)
            ->withMessageContaining('Cannot go up from root');

        Path::create('/test/../..');
    }

    public function testJoinPathComponents(): void
    {
        $path = Path::create('base/path');

        $result = $path->join('additional', 'components');

        Assert::same((string) $result, 'base/path/additional/components');
    }

    public function testJoinWithEmptyComponentsIgnoresThem(): void
    {
        $path = Path::create('base/path');

        $result = $path->join('', 'component', '');

        Assert::same((string) $result, 'base/path/component');
    }

    public function testJoinWithAbsolutePathObjectThrows(): never
    {
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        Expect::exception(\LogicException::class)
            ->withMessage('Joining an absolute path is not allowed.');

        $path->join($additionalPath->absolute());
    }

    public function testJoinWithRelativePathObjects(): void
    {
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        $result = $path->join($additionalPath);

        Assert::same((string) $result, 'base/path/additional/path');
    }

    public function testJoinWithAbsolutePathStringThrows(): never
    {
        $path = Path::create('base/path');

        Expect::exception(\LogicException::class)
            ->withMessage('Joining an absolute path is not allowed.');

        $path->join('/absolute/path');
    }

    public function testName(): void
    {
        $path = Path::create('some/path/file.txt');

        $name = $path->name();

        Assert::same($name, 'file.txt');
    }

    public function testNameWithNoDirectoryComponents(): void
    {
        $path = Path::create('file.txt');

        $name = $path->name();

        Assert::same($name, 'file.txt');
    }

    public function testStem(): void
    {
        $path = Path::create('some/path/file.txt');

        $stem = $path->stem();

        Assert::same($stem, 'file');
    }

    public function testStemWithNoExtension(): void
    {
        $path = Path::create('some/path/file');

        $stem = $path->stem();

        Assert::same($stem, 'file');
    }

    public function testStemWithMultipleDots(): void
    {
        $path = Path::create('some/path/file.config.json');

        $stem = $path->stem();

        Assert::same($stem, 'file.config');
    }

    public function testStemWithHiddenFile(): void
    {
        $path = Path::create('some/path/.hidden');

        $stem = $path->stem();

        Assert::same($stem, '.hidden');
    }

    public function testExtension(): void
    {
        $path = Path::create('some/path/file.txt');

        $extension = $path->extension();

        Assert::same($extension, 'txt');
    }

    public function testExtensionWithMultipleDots(): void
    {
        $path = Path::create('some/path/file.config.json');

        $extension = $path->extension();

        Assert::same($extension, 'json');
    }

    public function testExtensionWithNoExtension(): void
    {
        $path = Path::create('some/path/file');

        $extension = $path->extension();

        Assert::same($extension, '');
    }

    public function testExtensionWithHiddenFile(): void
    {
        $path = Path::create('some/path/.hidden');

        $extension = $path->extension();

        Assert::same($extension, 'hidden');
    }

    #[DataProvider('providePathsForParent')]
    public function testParent(string $inputPath, string $expectedParent): void
    {
        $path = Path::create($inputPath);

        $parent = $path->parent();

        Assert::same((string) $parent, $expectedParent);
    }

    #[DataProvider('providePathsForAbsoluteDetection')]
    public function testIsAbsolute(string $pathString, bool $expected): void
    {
        $path = Path::create($pathString);

        $isAbsolute = $path->isAbsolute();

        Assert::same($isAbsolute, $expected, "Path '$pathString' should be " . ($expected ? 'absolute' : 'relative'));
    }

    public function testIsRelative(): void
    {
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');
        $relativePath = Path::create('relative/path');

        Assert::false($absolutePath->isRelative());
        Assert::true($relativePath->isRelative());
    }

    /**
     * This test uses real filesystem access to check if a path exists.
     * It creates a temporary file and checks its existence.
     */
    public function testExists(): void
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        Assert::true(\is_string($tempFile), 'Failed to create temp file');

        $path = Path::create($tempFile);
        $nonExistingPath = Path::create('non/existing/path/file.txt');

        try {
            Assert::true($path->exists());
            Assert::false($nonExistingPath->exists());
        } finally {
            @\unlink($tempFile);
        }
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isDir without requiring an actual directory to exist.
     */
    public function testIsDir(): void
    {
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        Assert::true($currentDirPath->isDir());
        Assert::true($parentDirPath->isDir());
        Assert::false($filePath->isDir());
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isFile without requiring an actual file to exist.
     */
    public function testIsFile(): void
    {
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        Assert::true(\is_string($tempFile), 'Failed to create temp file');
        $realFilePath = Path::create($tempFile);

        try {
            Assert::false($currentDirPath->isFile());
            Assert::false($parentDirPath->isFile());
            Assert::false($filePath->isFile()); // Doesn't exist yet
            Assert::true($realFilePath->isFile(), "Temporary file should be a file `$realFilePath`");
        } finally {
            @\unlink($tempFile);
        }
    }

    public function testAbsoluteForAlreadyAbsolutePath(): void
    {
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');

        $result = $absolutePath->absolute();

        Assert::same((string) $result, (string) $absolutePath);
    }

    public function testAbsoluteForRelativePath(): void
    {
        $relativePath = Path::create('relative/path');

        $cwd = \getcwd();
        $cwd === false and throw new SkipTest('Cannot get current working directory');

        $expected = Path::create($cwd . DIRECTORY_SEPARATOR . 'relative/path');

        $result = $relativePath->absolute();

        Assert::same((string) $result, (string) $expected);
    }

    public function testCreateWindowsTmpFile(): void
    {
        $path = Path::create('C:\Users\roxbl\AppData\Local\Temp\patB6E7.tmp');

        Assert::same((string) $path, 'C:/Users/roxbl/AppData/Local/Temp/patB6E7.tmp');
    }

    public function testToString(): void
    {
        $path = Path::create('some/path/file.txt');

        $result = (string) $path;

        Assert::same($result, 'some/path/file.txt');
    }

    /**
     * @param non-empty-string $pattern
     */
    #[DataProvider('providePathsForMatch')]
    public function testMatch(string $pathString, string $pattern, bool $expected): void
    {
        $path = Path::create($pathString)->absolute();

        $result = $path->match($pattern);

        Assert::same($result, $expected, "Path '$pathString' should " . ($expected ? 'match' : 'not match') . " pattern '$pattern'");
    }

    public function testMatchWithPathObject(): void
    {
        $path = Path::create('test/file.txt')->absolute();
        $pattern = Path::create('test/*.txt');

        $result = $path->match($pattern);

        Assert::true($result, 'Path should match pattern when pattern is Path object');
    }

    public function testMatchWithRelativePaths(): void
    {
        $path = Path::create('test/file.txt');
        $pattern = 'test/*.txt';

        $result = $path->match($pattern);

        Assert::true($result, 'Relative path should match pattern after conversion to absolute');
    }

    public function testMatchWithComplexPattern(): void
    {
        $path = Path::create('src/Common/Path.php')->absolute();
        $pattern = 'src/Common/*.php';

        $result = $path->match($pattern);

        Assert::true($result, 'Path should match complex pattern');
    }

    public function testMatchCaseSensitive(): void
    {
        $path = Path::create('Test/File.TXT')->absolute();
        $pattern = 'test/file.txt';

        $result = $path->match($pattern);

        // On Windows, filesystem is case-insensitive, on Unix it's case-sensitive.
        // This test documents the actual behavior.
        if (DIRECTORY_SEPARATOR === '\\') {
            Assert::true($result, 'On Windows, match should be case-insensitive');
        } else {
            Assert::false($result, 'On Unix, match should be case-sensitive');
        }
    }

    public function testMatchWithCaseSensitiveFlag(): void
    {
        $path = Path::create('Test/File.TXT')->absolute();
        $pattern = 'test/file.txt';

        Assert::false($path->match($pattern, true), 'Should not match when case-sensitive is true');
        Assert::true($path->match($pattern, false), 'Should match when case-sensitive is false');
    }

    public function testMatchWithCaseSensitiveFlagExactCase(): void
    {
        $path = Path::create('Test/File.TXT')->absolute();
        $patternExact = 'Test/File.TXT';
        $patternLower = 'test/file.txt';

        Assert::true($path->match($patternExact, true), 'Should match exact case with case-sensitive flag');
        Assert::false($path->match($patternLower, true), 'Should not match different case with case-sensitive flag');
    }

    public function testMatchCaseInsensitiveOnAllOS(): void
    {
        $path = Path::create('Documents/FILE.txt')->absolute();

        Assert::true($path->match('*/file.TXT', false), 'Should match with case-insensitive flag');
        Assert::true($path->match('*/FILE.txt', false), 'Should match with case-insensitive flag');
        Assert::true($path->match('*/FiLe.TxT', false), 'Should match with case-insensitive flag');
    }

    public function testMatchCaseSensitiveOnAllOS(): void
    {
        $path = Path::create('Documents/report.PDF')->absolute();

        Assert::true($path->match('*/report.PDF', true), 'Should match exact case');
        Assert::false($path->match('*/report.pdf', true), 'Should not match different case');
        Assert::false($path->match('*/REPORT.PDF', true), 'Should not match different case');
    }

    public function testMatchWithWildcardsAndCaseFlag(): void
    {
        $path = Path::create('src/Controller/UserController.php')->absolute();

        Assert::true($path->match('*/controller/*.PHP', false), 'Wildcard should match case-insensitive');
        Assert::false($path->match('*/controller/*.php', true), 'Should not match - Controller != controller');
        Assert::true($path->match('*/Controller/*.php', true), 'Should match with correct case');
    }

    /**
     * @param non-empty-string $cwd
     */
    #[DataProvider('provideAbsoluteWithCwd')]
    public function testAbsoluteWithCwd(string $relativePath, string $cwd, ?string $expected = null, bool $usesCurrentDir = false): void
    {
        $path = Path::create($relativePath);

        $result = $path->absolute($cwd);

        if ($usesCurrentDir) {
            // Special case: relative cwd needs to be resolved against current directory.
            $currentCwd = \getcwd();
            $currentCwd === false and throw new SkipTest('Cannot get current working directory');

            $expected = Path::create($currentCwd)->join($cwd)->join($relativePath);
            Assert::same((string) $result, (string) $expected);
        } else {
            Assert::same((string) $result, $expected);
        }
    }

    /**
     * @param non-empty-string $cwd
     */
    #[DataProvider('provideAbsoluteWithCwdErrors')]
    public function testAbsoluteWithCwdThrowsException(string $absolutePath, string $cwd): never
    {
        $path = Path::create($absolutePath);

        Expect::exception(\LogicException::class)
            ->withMessageContaining('does not start with the given directory');

        $path->absolute($cwd);
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $base
     */
    #[DataProvider('provideTryRelative')]
    public function testTryRelative(string $path, string $base, string $expected): void
    {
        $result = Path::create($path)->tryRelative($base);

        Assert::notNull($result, "Path '$path' should be relative to '$base'");
        Assert::same((string) $result, $expected);
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $base
     */
    #[DataProvider('provideTryRelativeUnrelatedRoots')]
    public function testTryRelativeWithUnrelatedRootsReturnsNull(string $path, string $base): void
    {
        $result = Path::create($path)->tryRelative($base);

        Assert::null($result, "Path '$path' should not be relative to '$base'");
    }

    public function testTryRelativeForRelativePathReturnsSameInstance(): void
    {
        $path = Path::create('some/relative/path');
        $base = DIRECTORY_SEPARATOR === '\\' ? 'C:/var/www' : '/var/www';

        $result = $path->tryRelative($base);

        Assert::same($result, $path, 'Relative path should be returned as is');
    }

    public function testTryRelativeWithRelativeBaseResolvesAgainstCwd(): void
    {
        $cwd = \getcwd();
        $cwd === false and throw new SkipTest('Cannot get current working directory');

        $path = Path::create($cwd)->join('project', 'app', 'src', 'file.php');

        $result = $path->tryRelative('project/app');

        Assert::notNull($result);
        Assert::same((string) $result, 'src/file.php');
    }

    public function testTryRelativeWithCurrentDirectoryAsBase(): void
    {
        $cwd = \getcwd();
        $cwd === false and throw new SkipTest('Cannot get current working directory');

        $path = Path::create($cwd)->join('src', 'Path.php');

        $result = $path->tryRelative('.');

        Assert::notNull($result);
        Assert::same((string) $result, 'src/Path.php');
    }

    public function testTryRelativeWithPathObjectAsBase(): void
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $path = Path::create($isWindows ? 'C:/var/www/app/src/file.php' : '/var/www/app/src/file.php');
        $base = Path::create($isWindows ? 'C:/var/www/app' : '/var/www/app');

        $result = $path->tryRelative($base);

        Assert::notNull($result);
        Assert::same((string) $result, 'src/file.php');
    }

    public function testTryRelativeIsCaseSensitiveDependingOnOs(): void
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $path = Path::create($isWindows ? 'C:/var/WWW/app' : '/var/WWW/app');
        $base = $isWindows ? 'C:/var/www' : '/var/www';

        $result = $path->tryRelative($base);

        Assert::notNull($result);
        if ($isWindows) {
            Assert::same((string) $result, 'app', 'On Windows, segment comparison is case-insensitive');
        } else {
            Assert::same((string) $result, '../WWW/app', 'On Unix, segment comparison is case-sensitive');
        }
    }

    public function testTryRelativeResultJoinedBackToBaseGivesOriginalPath(): void
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $base = Path::create($isWindows ? 'C:/var/www/app/src' : '/var/www/app/src');
        $path = Path::create($isWindows ? 'C:/var/log/nginx/error.log' : '/var/log/nginx/error.log');

        $result = $path->tryRelative($base);

        Assert::notNull($result);
        Assert::same((string) $base->join($result), (string) $path);
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $base
     */
    #[DataProvider('provideIsWithin')]
    public function testIsWithin(string $path, string $base, bool $expected): void
    {
        $result = Path::create($path)->isWithin($base);

        Assert::same($result, $expected, "Path '$path' should " . ($expected ? '' : 'not ') . "be within '$base'");
    }

    public function testIsWithinWithPathObjectAsBase(): void
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $base = Path::create($isWindows ? 'C:/var/www/app' : '/var/www/app');
        $inside = Path::create($isWindows ? 'C:/var/www/app/src/file.php' : '/var/www/app/src/file.php');
        $outside = Path::create($isWindows ? 'C:/var/www/other' : '/var/www/other');

        Assert::true($inside->isWithin($base));
        Assert::false($outside->isWithin($base));
    }

    public function testIsWithinDefaultBaseIsCurrentDirectory(): void
    {
        $relativeInside = Path::create('src/Path.php');
        $currentDir = Path::create('.');
        $traversal = Path::create('../outside/file.php');

        Assert::true($relativeInside->isWithin(), 'Relative path should be within the current directory');
        Assert::true($currentDir->isWithin(), 'Current directory should be within itself');
        Assert::false($traversal->isWithin(), 'Path traversal should not be within the current directory');
    }

    public function testIsWithinDefaultBaseForAbsolutePath(): void
    {
        $cwd = \getcwd();
        $cwd === false and throw new SkipTest('Cannot get current working directory');

        $inside = Path::create($cwd)->join('src', 'Path.php');
        $outside = Path::create($cwd)->parent()->join('definitely-outside');

        Assert::true($inside->isWithin());
        Assert::false($outside->isWithin());
    }

    public function testIsWithinWithRelativeBaseResolvesAgainstCwd(): void
    {
        $cwd = \getcwd();
        $cwd === false and throw new SkipTest('Cannot get current working directory');

        $path = Path::create($cwd)->join('project', 'app', 'src', 'file.php');

        Assert::true($path->isWithin('project/app'));
        Assert::false($path->isWithin('project/other'));
    }

    public function testIsWithinIsCaseSensitiveDependingOnOs(): void
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $path = Path::create($isWindows ? 'C:/var/WWW/app' : '/var/WWW/app');
        $base = $isWindows ? 'C:/var/www' : '/var/www';

        $result = $path->isWithin($base);

        if ($isWindows) {
            Assert::true($result, 'On Windows, segment comparison is case-insensitive');
        } else {
            Assert::false($result, 'On Unix, segment comparison is case-sensitive');
        }
    }
}
