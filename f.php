<?php

use Symfony\Component\Process\Process;

require 'vendor/autoload.php';

$a = [
    0 => '/bin/bash',
    1 => '-c',
    2 => 'source /home/bosun/.bashrc && command -v __shadowenv_hook',
];

$process = new Process($a);
$process->setTty(true);
$process->mustRun(function ($t, $o): void {
    echo $o, $t;
});

echo $process->getOutput();
echo "hjbdshfsdfsd\n";
