<?php

namespace MassEdge\MyWaterToronto;

use Symfony\Component\Console\Application;

class Cli {
    /**
     * @var Application
     */
    private $application;

    function __construct() {
        $this->application = new Application();
        $this->application->add(new ConsumptionCommand());
    }

    function run() {
        return $this->application->run();
    }
}
