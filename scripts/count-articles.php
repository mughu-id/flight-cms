<?php

require __DIR__ . '/articles.php';

foreach (dummy_articles() as $slug => $html) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    echo $slug, ' ', str_word_count($text), PHP_EOL;
}
