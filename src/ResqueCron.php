<?php

namespace kapitanluffy\ResqueCronBundle;

use BCC\ResqueBundle as BCCResque;

/**
 * cron support for resque
 */
class Resquecron {

    // ^([0-9]{4})-((?:0[1-9]|1[0-2]))-((?:0[1-9]|[1-2][0-9]|3[0-1])) ([0-9]{2}):([0-9]{2}):([0-9]{2}) ((?:\+|-)[0-9]{4})$

    /** @var string Default cron schedule (now) */
    protected $schedule = "Y-m-d H:i:s";

    /**
     * units regex pattern for determining schedule
     * http://php.net/manual/en/datetime.formats.relative.php
     *
     * @var string
     */
    private $unitsPattern = "^(\+|-)[0-9]+ (hour|minute|second|day|year)s?$";

    /** @var int schedule interval */
    protected $interval = null;

    /**
     * set defaults
     *
     * @method __construct
     *
     * @param  BCCResque\Resque $resque
     * @param  Logger $logger
     */
    public function __construct(BCCResque\Resque $resque)
    {
        $this->resque =& $resque;
    }

    /**
     * get resque instance
     * @method get_resque
     * @return \BCCResque\Resque
     */
    public function get_resque()
    {
        return $this->resque;
    }

    /**
     * create a job object
     * @method create_job
     * @param  array     $data
     * @param  bool       $removeArgs
     * @return \BCCResque\Job
     */
    public function create_job($data, $removeArgs = false)
    {
        if(!class_exists($data['class'])) throw new \Exception("{$data['class']} Class not found", 1);

        $job = new $data['class'];
        $job->args = $data['args'][0];
        $job->queue = $data['queue'];

        if($removeArgs) {
            $job->args = array_intersect_key($job->args, array_flip(preg_grep('#kernel\.|bcc_resque\.#', array_keys($job->args), PREG_GREP_INVERT)));
        }

        return $job;
    }

    /**
     * get the job hash
     * @method get_job_hash
     * @param  \BCCResque\Job       $job
     * @return string
     */
    public function get_job_hash($job)
    {
        $array = array(
            'class' => \get_class($job),
            'args' => array($job->args),
            'queue' => $job->queue
            );
        return json_encode($array);
    }

    /**
     * stop a job
     * @method stop
     * @param  \BCCResque $job
     * @param  bool   $timestamp
     * @return int            count of stopped jobs
     */
    public function stop($job, $timestamp = false)
    {
        $job_class = \get_class($job);
        $jobs = $this->get_jobs(false);
        $count = 0;
        foreach($jobs as $timestamp_ => $jobs_) {
            if($timestamp === true && $timestamp != $timestamp_) continue;

            foreach($jobs_ as $job_) {
                $job_class_ = \get_class($job_);
                if($job_class == $job_class_) {
                    $count += $this->resque->removeFromTimestamp($timestamp_, $job_);
                    $this->logger->info("[RESQUECRON::STOP] Stopped $job_class_ cron job");
                }
            }
        }

        if($count > 0) return $count;

        $this->logger->info("[RESQUECRON::STOP] Cannot find $job_class cron job..");
        return 0;
    }

    /**
     * get jobs
     * @method get_jobs
     * @param  bool     $removeArgs
     * @return array               list of jobs
     */
    public function get_jobs($removeArgs = true)
    {
        $timestamps = $this->resque->getDelayedJobTimestamps();
        $jobs = array();
        foreach($timestamps as $timestamp)
        {
            $jobs_ = $this->resque->getJobsForTimestamp($timestamp[0]);
            $jobs[$timestamp[0]] = array();
            foreach($jobs_ as $job)
            {
                try {
                    $job = $this->create_job($job, $removeArgs);
                    $jobs[$timestamp[0]][] = $job;
                }
                catch(\Exception $e) {
                    $this->logger->info("{$job['class']} job cannot find class");
                }
            }
        }

        return $jobs;
    }

    /**
     * get job
     * @method get_job
     * @param  string  $class      job class
     * @param  string  $timestamp  job schedule
     * @param  bool    $removeArgs
     * @return \BCCResque\Job
     */
    public function get_job($class, $timestamp = null, $removeArgs = true)
    {

        if(!$timestamp) {
            $timestamps = $this->resque->getDelayedJobTimestamps();
        }
        else {
            $timestamps = array(array($timestamp,1));
        }

        foreach($timestamps as $timestamp)
        {
            $jobs_ = $this->resque->getJobsForTimestamp($timestamp[0]);
            foreach($jobs_ as $job)
            {
                if($class == $job['class']) return $this->create_job($job, $removeArgs);
            }
        }
    }

    /**
     * check if valid schedule interval
     * @method isValidInterval
     * @param  string          $interval schedule interval
     * @return bool
     */
    public function isValidInterval($interval) {
        return preg_match('#' . $this->unitsPattern . '#', $interval);
    }

    /**
     * parse schedule
     * @method parse_schedule
     * @param  array          $datetime
     * @return array                   parsed schedule
     */
    public function parse_schedule(array $datetime)
    {
        $patterns = array(
            'year' => '#Y#',
            'month' => '#m#',
            'day' => '#d#',
            'hour' => '#H#',
            'minute' => '#i#',
            'second' => '#s#'
        );

        $default = array(
            'year' => 'Y',
            'month' => 'm',
            'day' => 'd',
            'hour' => 'H',
            'minute' => 'i',
            'second' => 's'
        );

        // merge $datetime to default pattern
        $datetime = array_replace($default, $datetime);

        // replace the schedule with provided
        return preg_replace($patterns, $datetime, $this->schedule);
    }

    /**
     * create scheduled job
     * @method create
     * @param  Interfaces\ResqueCronJobInterface $job
     * @param  string                            $interval
     * @param  array                             $schedule
     * @return \BCCResque\Job
     */
    public function create(ResqueCronJobInterface $job, $interval, $schedule = array()) {

        if(!$this->isValidInterval($interval)) throw new \Exception('invalid Interval', 1);

        $schedule = $this->parse_schedule($schedule);

        $_args = array(
            'resque_cron.interval' => $interval,
            'resque_cron.schedule' => $schedule
            );

        $job->args = array_merge($job->args, $_args);

        return $job;
    }

    /**
     * schedule the job
     * @method run
     * @param  Interfaces\ResqueCronJobInterface $job
     * @return \DateTime                                 job schedule
     */
    public function run(ResqueCronJobInterface $job)
    {
        $class = \get_class($job);

        // get current jobs
        $jobs = $this->get_jobs();

        // throw exception if job is already running
        foreach($jobs as $timestamp => $jobs_) {
            foreach($jobs_ as $job_) {
                $job_class_ = \get_class($job_);
                if($class == $job_class_) {
                    $job_args = json_encode($job->args);
                    $job_args_ = json_encode($job_->args);

                    if($job_args == $job_args_) throw new \Exception("$class Job already in cron..", 1);
                }
            }
        }

        // reset retry attempt
        $job->args['bcc_resque.retry_attempt'] = 0;

        // create the datetime object based on schedule
        $dateString = date($job->args['resque_cron.schedule']);
        $dateTime = new \DateTime($dateString);

        // modify datetime with interval
        $dateTime->modify($job->args['resque_cron.interval']);
        $dateString = $dateTime->format('Y-m-d H:i:s');

        // throw exception if schedule is behind current time
        $now = new \DateTime;
        if($now->getTimestamp() >= $dateTime->getTimestamp()) throw new \Exception("{$class}: {$dateString} is already past the current time", 1);

        // enqueue the job
        $this->resque->enqueueAt($dateTime, $job);
        $this->logger->info("{$class}: scheduled on {$dateString}");

        return $dateTime;
    }


}