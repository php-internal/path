<div align="center">

# Path helper for PHP

</div>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://boosty.to/roxblnfk)

</div>

<br />

A type-safe library for working with file system paths.
Handles normalization, cross-platform compatibility, and all the edge cases with slashes and separators.

Path is an immutable value object – all methods return new instances, so it's safe to use as a DTO,
pass between layers, or store in your domain models without worrying about side effects.

## Installation

```bash
composer require internal/path
```

[![PHP](https://img.shields.io/packagist/php-v/internal/path.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/path)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/path.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/path)
[![License](https://img.shields.io/packagist/l/internal/path.svg?style=flat-square)](LICENSE.md)
[![Total Destroys](https://img.shields.io/packagist/dt/internal/path.svg?style=flat-square)](https://packagist.org/packages/internal/path/stats)

## Usage

### Creating paths

```php
use Internal\Path;

// Create from string
$path = Path::create('/var/www/app');
$path = Path::create('src/helpers/utils.php');
```

### Joining paths

```php
$base = Path::create('/var/www');
$full = $base->join('app', 'src', 'Controller.php');
// Result: /var/www/app/src/Controller.php

// Works with Path objects too
$subdir = Path::create('logs');
$logFile = $base->join($subdir, 'app.log');
```

### Working with path components

```php
$file = Path::create('/home/user/documents/report.pdf');

$file->name();      // 'report.pdf'
$file->stem();      // 'report'
$file->extension(); // 'pdf'
$file->parent();    // Path('/home/user/documents')
```

### Path checks

```php
$path = Path::create('config/app.php');

$path->isAbsolute();  // false
$path->isRelative();  // true
$path->exists();      // checks if file/directory exists
$path->isFile();      // checks if it's a file
$path->isDir();       // checks if it's a directory
$path->isWriteable(); // checks if writable
```

### Converting paths

```php
$relative = Path::create('src/Path.php');
$absolute = $relative->absolute();
// Result: /current/working/directory/src/Path.php

// Resolve against custom directory
$absolute = $relative->absolute('/var/www/app');
// Result: /var/www/app/src/Path.php

// Use as string
echo $path; // Path implements Stringable
```

### Pattern matching

```php
$path = Path::create('/var/www/app/Controller.php');
$path->match('*.php');           // true
$path->match('/var/www/*/Con*'); // true

// Supports wildcards
$path->match('file?.txt');       // matches file1.txt, fileA.txt, etc.
$path->match('file[123].txt');   // matches file1.txt, file2.txt, file3.txt
$path->match('test/*/*.php');    // matches test/any/file.php

// Control case sensitivity with optional parameter
$file = Path::create('File.TXT');
$file->match('*.txt');              // OS default (case-insensitive on Windows, case-sensitive on Unix)
$file->match('*.txt', false);       // case-insensitive (matches on any OS)
$file->match('*.txt', true);        // case-sensitive (won't match - different case)
$file->match('*.TXT', true);        // case-sensitive (matches - exact case)
```

## Edge cases and special handling

The library handles common edge cases automatically:

### Hidden files and multiple extensions

```php
// Hidden files (Unix-style)
$hidden = Path::create('.gitignore');
$hidden->stem();      // '.gitignore'
$hidden->extension(); // 'gitignore'

// Files with multiple dots
$config = Path::create('app.config.json');
$config->stem();      // 'app.config'
$config->extension(); // 'json' (only the last extension)
```

### Windows paths

```php
// Automatically normalizes Windows backslashes
$winPath = Path::create('C:\Users\Admin\Documents');
echo $winPath; // 'C:/Users/Admin/Documents'

// Windows drive letters are recognized as absolute
Path::create('C:/Program Files')->isAbsolute(); // true
```

### Path normalization

```php
// Removes redundant separators
Path::create('path//to///file.txt'); // 'path/to/file.txt'

// Resolves . and .. segments
Path::create('path/./to/../file.txt'); // 'path/file.txt'

// Empty path becomes current directory
Path::create(''); // '.'
```

### Safety checks

```php
// Cannot join absolute paths (prevents common mistakes)
$base = Path::create('/var/www');
$base->join('/etc/config'); // throws LogicException

// Cannot navigate above root in absolute paths
Path::create('/var/../../root'); // throws LogicException
```

### Converting to absolute with custom base directory

```php
// Relative base directory is converted to absolute first
$path = Path::create('lib/helper.php');
$path->absolute('project/app'); // resolves 'project/app/lib/helper.php' against current directory first

// Absolute path with matching base - validation passes
$absolutePath = Path::create('/var/www/app/src/file.php');
$absolutePath->absolute('/var/www/app'); // returns same path (validation OK)
$absolutePath->absolute('/var/www');     // returns same path (validation OK - parent directory)

// Absolute path with non-matching base - throws exception
$absolutePath = Path::create('/var/www/app/file.php');
$absolutePath->absolute('/home/user'); // throws LogicException - path doesn't start with base
```
