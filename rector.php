<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/App',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,  // Enable dead code removal
        codeQuality: true,  // Enable code quality improvements
        typeDeclarations: true // Disable naming convention refactoring
    )
    ->withRules([
        AddMethodCallBasedStrictParamTypeRector::class,
    ])
    ->withImportNames();
