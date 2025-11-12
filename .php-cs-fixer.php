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
        'phpdoc_align' => false,
        'phpdoc_to_comment' => false,
        'indentation_type' => true,
    ])
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setFinder($finder);
