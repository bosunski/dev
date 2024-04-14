<?php

use function Swoole\Coroutine\run;

require __DIR__ . '/vendor/autoload.php';

run(function (): void {
    $process = proc_open('echo 1 >/dev/null', [['pty'], ['pty'], ['pty']], $pipes);
    dump($process, $pipes);
});

// proc_close($process);

// use Symfony\Component\Console\Application;
// use Symfony\Component\Console\Attribute\AsCommand;
// use Symfony\Component\Console\Command\Command;
// use Symfony\Component\Console\Input\InputInterface;
// use Symfony\Component\Console\Output\OutputInterface;

// // the name of the command is what users type after "php bin/console"
// #[AsCommand(name: 'foo')]
// class GenerateAdminCommand extends Command
// {
//     protected function configure(): void
//     {
//         $this
//             // the command description shown when running "php bin/console list"
//             ->setDescription('Creates a new user.')
//             // the command help shown when running the command with the "--help" option
//             ->setHelp('This command allows you to create a user...');
//     }

//     protected function execute(InputInterface $input, OutputInterface $output)
//     {
//         // $process = proc_open('echo 1', [['pty'], ['pty'], ['pty']], $pipes);
//         // $out = stream_get_contents($pipes[1]);
//         // dump($process, $pipes, $out);

//         // dump(is_resource($process));

//         // proc_close($process);

//         $descriptorspec = [
//             ['pty'],
//             ['pty'],
//             ['pty'],
//         ];

//         $process = proc_open('echo 1', $descriptorspec, $pipes);

//         $out = stream_get_contents($pipes[1]);
//         dump($process, $pipes, $out);

//         dump(is_resource($process));

//         proc_close($process);

//         // ... put here the code to create the user

//         // this method must return an integer number with the "exit status code"
//         // of the command. You can also use these constants to make code more readable

//         // return this if there was no problem running the command
//         // (it's equivalent to returning int(0))
//         return Command::SUCCESS;

//         // or return this if some error happened during the execution
//         // (it's equivalent to returning int(1))
//         // return Command::FAILURE;

//         // or return this to indicate incorrect command usage; e.g. invalid options
//         // or missing arguments (it's equivalent to returning int(2))
//         // return Command::INVALID
//     }
// }

// $application = new Application();

// $application->add(new GenerateAdminCommand());

// // dump(@proc_open('echo 2 >/dev/null', [['pty'], ['pty'], ['pty']], $pd));
// // ... register commands

// $application->run();
