<?php

namespace kapitanluffy\ResqueCronBundle;

/**
 * resque cron jobs
 */
interface ResqueCronJobInterface {

    /**
     * start job
     *
     * @method start
     *
     * @param  array $args job arguments
     */
    public function start($args);

    /**
     * run before starting the job
     *
     * @method pre_hook
     *
     * @param  array $args job arguments
     */
    public function pre_hook($args);

    /**
     * run after starting the job
     *
     * @method post_hook
     *
     * @param  array $args job arguments
     *
     * @return int job status
     */
    public function post_hook($args);

}