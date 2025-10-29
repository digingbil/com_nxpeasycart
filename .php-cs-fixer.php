<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/administrator/components/com_nxpeasycart')
    ->in(__DIR__ . '/components/com_nxpeasycart')
    ->name('*.php')
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_align' => false,
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder);
