<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setRules([
        '@PSR2' => true,
        'blank_line_after_opening_tag' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
    ]);
