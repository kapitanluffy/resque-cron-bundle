<?php

namespace kapitanluffy\ResqueCronBundle;

use Pickld\ApiBundle\Interfaces as Interfaces;
use BCC\ResqueBundle\ContainerAwareJob;
use Symfony\Component\DependencyInjection as DI;

/**
 * abstact class for resque cron clas
 */
abstract class ResqueCronJob extends ContainerAwareJob implements ResqueCronJobInterface, DI\ContainerAwareInterface
{

    /** @var DI\ContainerAwareInterface the job container */
    protected $container;

    /** @var Logger symfony logger */
    public $logger;

    /** @var ResqueCron resquecron instance */
    public $resque_cron;

    const FAILED = 0;
    const REPEAT = 1;
    const STOP = 2;

    /**
     * run the job
     *
     * @method run
     *
     * @param  array $args job arguments
     *
     * @return int job status
     */
    final public function run($args) {
        // get container from parent
        $this->container = parent::getContainer();

        // get resque cron service
        $this->resque_cron = $this->container->get('resque_cron');

        // logger service anyone?
        $this->logger = $this->container->get('logger');

        $class = \get_class($this);

        // do pre hook
        $this->logger->info("[" . $class . "]: executing pre_hook method");
        $this->pre_hook($args);

        // execute main method
        $this->start($this->args);

        // do post hook
        $this->logger->info("[" . $class . "]: executing post_hook method");
        $postHookResult = $this->post_hook($this->args);

        switch($postHookResult) {
            case self::REPEAT:
                $this->resque_cron->run($this);
                break;
            case self::FAILED:
                throw new \Exception($class . ' job failed', 1);
                break;
            case self::STOP:
                $this->logger->info("$class stopped");
                return true;
        }
    }

    /**
     * method executed before running the job
     * @method pre_hook
     * @param  array   $args
     * @return bool
     */
    public function pre_hook($args) {
        return true;
    }

    /**
     * method executed after running the job
     * @method post_hook
     * @param  array    $args
     * @return int          cronjob status
     */
    public function post_hook($args) {
        return self::STOP;
    }

    /**
     * set the container for the job
     * @method setContainer
     * @param  DI\ContainerInterface|null $container
     */
    public function setContainer(DI\ContainerInterface $container = null) {
        $this->container = $container;
    }

    /**
     * get the container of the job
     * @method getContainer
     * @return DI\ContainerInterface
     */
    public function getContainer() {
        if(!is_object($this->container)) {

            $this->container = parent::getContainer();
            if(!is_object($this->container)) {
                $class = \get_class($this);
                throw new \Exception("$class: service sontainer not set", 1);
            }
        }

        return $this->container;
    }
}
