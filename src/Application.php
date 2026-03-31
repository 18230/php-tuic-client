<?php declare(strict_types=1);

namespace PhpTuic;

use PhpTuic\Command\DoctorCommand;
use PhpTuic\Command\RunCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('tuic-client', '0.2.3');

        $this->add(new RunCommand());
        $this->add(new DoctorCommand());
        $this->setDefaultCommand('run');
    }
}
