<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\DeadCode\Rector\StmtsAwareInterface\RemoveDeadInstanceOfAssertRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // The PHP version from composer.json ("php": ">=8.4"), so no syntax newer than the bundle supports.
    ->withPhpSets()
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withCache(cacheDirectory: __DIR__ . '/var/cache/rector')
    ->withSkip([
        RemoveDeadInstanceOfAssertRector::class,
        RemoveDefaultValueFromAssignedPropertyRector::class,
        // Names a class of an optional package (Symfony Security) the Doctrine integration may not depend on (deptrac).
        StringClassNameToClassConstantRector::class => [__DIR__ . '/src/Driver/Doctrine/DoctrineDriver.php'],
        // UserInterface::eraseCredentials() is gone in Symfony 8, but security-core 7.4 (the lowest dependencies) still
        // asks for it.
        RemoveEmptyClassMethodRector::class => [__DIR__ . '/tests/Unit/Security/DefaultUserIdMapperTest.php'],
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
    );
