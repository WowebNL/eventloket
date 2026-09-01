<?php

declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\ArrayDimFetch\EnvVariableToEnvHelperRector;
use RectorLaravel\Rector\Class_\AnonymousMigrationsRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Rector\FuncCall\NowFuncWithStartOfDayMethodCallToTodayFuncRector;
use RectorLaravel\Rector\MethodCall\AvoidNegatedCollectionFilterOrRejectRector;
use RectorLaravel\Rector\MethodCall\ConvertEnumerableToArrayToAllRector;
use RectorLaravel\Rector\MethodCall\ValidationRuleArrayStringValueToArrayRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withSkip([
        // TODO: rules added in rector-laravel dependency update — fix in dedicated branch
        AnonymousMigrationsRector::class,
        AppToResolveRector::class,
        ArgumentAdderRector::class,
        AvoidNegatedCollectionFilterOrRejectRector::class,
        CarbonToDateFacadeRector::class,
        DispatchToHelperFunctionsRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        ValidationRuleArrayStringValueToArrayRector::class,
        ConvertEnumerableToArrayToAllRector::class,

        // Rewrites every $_ENV[...] read into Illuminate\Support\Env::get(), including the
        // ones inside unset(). A static call is not a valid unset() target, so the rewritten
        // file no longer parses. The rule only skips direct assignment, so there is no
        // configuration in which it leaves write contexts alone.
        EnvVariableToEnvHelperRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_84);
