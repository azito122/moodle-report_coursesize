<?php

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

$which = $argv[1];
$out   = $argv[2];

if (!isset($which)) {
    echo "Please provide a key ('course_sizes', 'category_sizes', 'user_sizes').";
    die();
}

if (!isset($out)) {
    echo "Please provide an output path (e.g. '~/course_sizes.json').";
    die();
}

$cache = \cache::make('report_coursesize', 'results');
$data = $cache->get($which);

if (!file_put_contents($out, json_encode($data))) {
    echo "Something went wrong! Could not write to given path.";
    die();
}
