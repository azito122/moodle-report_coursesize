<?php

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

$delay = $argv[1] ?? 0;

$count = 1;
while(true) {
    $pad = 5+ strlen((string) $count);
    echo "\033[{$pad}D";
    echo "Run #" . $count;
    $task = \report_coursesize\task\build_data_task::make();
    if ($task->execute(false)) {
        break;
    }
    $count++;
    sleep($delay);
}
