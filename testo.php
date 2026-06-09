<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    // For Codecov.
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/Unit'],
        ),

        // For inline tests and benchmarks right in the project source code, in the src folder.
        new SuiteConfig(
            name: 'Sources',
            location: ['src'],
        ),
    ],
);
