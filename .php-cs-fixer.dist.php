<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        '.github',
        'archive',
        'assets',
        'node_modules',
        'vendor',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof' => true,
        'blank_line_after_namespace' => true,
        'line_ending' => true,
        'encoding' => true,
        'full_opening_tag' => true,
        'no_closing_tag' => false, // Keep closing tags where needed in mixed template files
    ])
    ->setFinder($finder);
