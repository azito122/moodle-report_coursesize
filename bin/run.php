<?php

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

$delay = $argv[1] ?? 0;

$times = array();
$avgtimes = [
    5 => 0,
    15 => 0,
    50 => 0,
];

$count = 1;
$lastoutput = '';
while(true) {
    $progress = \report_coursesize\task\build_data_task::get_progress(true);
    $timestart = time();

    $task = \report_coursesize\task\build_data_task::make();
    if ($task->execute(false)) {
        break;
    }

    $timeend = time();
    $runtime = $timeend - $timestart;
    $times[] = $runtime;

    foreach ($avgtimes as $range => $avg) {
        if (count($times) >= $range) {
            $a = round(array_sum(array_slice($times, -1 * $range, $range)) / $range);
        } else {
            $a = '.';
        }
        $avgtimes[$range] = $a;
    }

    $pad = strlen($lastoutput);
    $erase = "\033[{$pad}D";

    $output = "Run #$count - {$progress->step}/{$progress->steptotal} ({$progress->percent}%) - stage {$progress->stage} - $runtime seconds ({$avgtimes[5]}/{$avgtimes[15]}/{$avgtimes[50]})";
    $lastoutput = $output;

    echo $erase;
    echo $output;

    $count++;
    sleep($delay);
}
