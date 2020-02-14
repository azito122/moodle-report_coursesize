<?php

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

$which = $argv[1] ?? null;

$caches = $which ?? array('results', 'in_progress', 'file_mappings');

foreach ($caches as $cachearea) {
    $cache = \cache::make('report_coursesize', $cachearea);
    $cache->purge();
}
