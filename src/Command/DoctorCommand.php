<?php

declare(strict_types=1);

namespace TuicClient\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TuicClient\Config\TuicConfigLoader;

final class DoctorCommand extends Command
{
    public function __construct()
    {
        parent::__construct('doctor');
    }

    protected function configure(): void
    {
        $this->setDescription('Validate the local PHP runtime and a TUIC client config file.');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON config file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['json', 'openssl', 'sockets'] as $extension) {
            if (extension_loaded($extension)) {
                $output->writeln(sprintf('<info>OK ext-%s loaded</info>', $extension));
            } else {
                $output->writeln(sprintf('<error>MISSING ext-%s</error>', $extension));

                return self::FAILURE;
            }
        }

        if (extension_loaded('curl')) {
            $output->writeln('<info>OK ext-curl loaded</info>');
        } else {
            $output->writeln('<comment>WARNING ext-curl is not loaded; application-level HTTP proxy integration will be limited.</comment>');
        }

        $output->writeln(sprintf('<info>OK PHP %s</info>', PHP_VERSION));

        $configPath = $input->getOption('config');
        if (!is_string($configPath) || $configPath === '') {
            $output->writeln('<comment>No config file provided. Runtime checks passed.</comment>');

            return self::SUCCESS;
        }

        try {
            $config = (new TuicConfigLoader())->fromFile($configPath);
        } catch (InvalidArgumentException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::INVALID;
        }

        $output->writeln(sprintf('<info>OK TUIC config parsed for %s:%d</info>', $config->node->server, $config->node->port));
        $output->writeln(sprintf('<info>OK Local bind %s</info>', $config->runtime->local));
        $output->writeln(sprintf('<info>OK ALPN %s</info>', implode(', ', $config->node->alpn)));

        return self::SUCCESS;
    }
}
