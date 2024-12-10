<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/App',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,  // Enable dead code removal
        codeQuality: true,  // Enable code quality improvements
        naming: false  // Disable naming convention refactoring
    )
    ->withTypeCoverageLevel(0);
