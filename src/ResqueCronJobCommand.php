<?php

namespace kapitanluffy\ResqueCronBundle;

use Pickld\ApiBundle\Interfaces as Interfaces;

/**
 * abstract class for commandable resquecron jobs
 */
abstract class ResqueCronJobCommand extends ResqueCronJob implements Interfaces\ResqueCronJobCommandInterface
{

    /**
     * stop the cronjob command
     * @method stop
     */
    public function stop()
    {
        $resque_cron = $this->getContainer()->get('resque_cron');
        $resque_cron->stop($this);
    }
}
