<?php

namespace kapitanluffy\ResqueCronBundle;

use Symfony\Component\Console as Console;

/**
 * resque jobs that can be run on console
 */
interface ResqueCronJobCommandInterface {

    /**
     * run the command
     *
     * @method execute
     *
     * @param  Console\Input\InputInterface $input
     * @param  Console\Output\OutputInterface $output
     */
    public function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output);

    /**
     * stop the comand
     *
     * @method stop
     */
    public function stop();
}