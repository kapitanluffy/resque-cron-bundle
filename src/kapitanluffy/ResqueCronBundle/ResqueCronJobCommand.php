<?php

namespace kapitanluffy\ResqueCronBundle;

/**
 * abstract class for commandable resquecron jobs
 */
abstract class ResqueCronJobCommand extends ResqueCronJob implements ResqueCronJobCommandInterface
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
