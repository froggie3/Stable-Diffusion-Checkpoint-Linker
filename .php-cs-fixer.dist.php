<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/scripts')
    ->append([__DIR__ . '/linker', __FILE__])
    ->exclude(['vendor', 'data', '.cache']);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules(['@PSR12' => true])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.cache/php-cs-fixer');
