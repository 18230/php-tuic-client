<?php

declare(strict_types=1);

namespace TuicClient\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TuicClient\Config\TuicClientConfig;
use TuicClient\Config\TuicConfigLoader;

final class RunCommand extends Command
{
    public function __construct()
    {
        parent::__construct('run');
    }

    protected function configure(): void
    {
        $this->setDescription('Resolve TUIC client configuration and run the scaffold runtime.');
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON config file.')
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Remote TUIC server hostname.')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Remote TUIC server port.')
            ->addOption('uuid', null, InputOption::VALUE_REQUIRED, 'TUIC UUID.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'TUIC password.')
            ->addOption('sni', null, InputOption::VALUE_REQUIRED, 'TLS SNI override.')
            ->addOption('alpn', null, InputOption::VALUE_REQUIRED, 'Comma-separated ALPN list.')
            ->addOption('udp-relay-mode', null, InputOption::VALUE_REQUIRED, 'UDP relay mode.')
            ->addOption('congestion-controller', null, InputOption::VALUE_REQUIRED, 'Congestion controller.')
            ->addOption('allow-insecure', null, InputOption::VALUE_REQUIRED, 'Whether to allow insecure TLS (0 or 1).')
            ->addOption('local', null, InputOption::VALUE_REQUIRED, 'Local bind endpoint, for example 127.0.0.1:1080.')
            ->addOption('log-level', null, InputOption::VALUE_REQUIRED, 'Log level for the future runtime.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve and print configuration without starting the runtime.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->resolveConfig($input);
        } catch (InvalidArgumentException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::INVALID;
        }

        $output->writeln('<info>Resolved TUIC client configuration</info>');
        foreach ($this->summaryLines($config) as $line) {
            $output->writeln(sprintf(' - %s', $line));
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<comment>Dry run successful. The TUIC transport layer is intentionally left for the next implementation phase.</comment>');

            return self::SUCCESS;
        }

        $output->writeln('<comment>The php-tuic-client project has been initialized, but the TUIC transport runtime is not implemented yet. Re-run with --dry-run for validation only.</comment>');

        return self::FAILURE;
    }

    private function resolveConfig(InputInterface $input): TuicClientConfig
    {
        $loader = new TuicConfigLoader();
        $payload = [];

        $configPath = $input->getOption('config');
        if (is_string($configPath) && $configPath !== '') {
            $payload = $loader->loadRawArray($configPath);
        }

        $node = isset($payload['node']) && is_array($payload['node']) ? $payload['node'] : $payload;
        $runtime = isset($payload['runtime']) && is_array($payload['runtime']) ? $payload['runtime'] : [];

        foreach ([
            'server' => 'server',
            'port' => 'port',
            'uuid' => 'uuid',
            'password' => 'password',
            'sni' => 'sni',
            'alpn' => 'alpn',
            'udp-relay-mode' => 'udp_relay_mode',
            'congestion-controller' => 'congestion_controller',
            'allow-insecure' => 'allow_insecure',
        ] as $option => $key) {
            $value = $input->getOption($option);
            if ($value !== null) {
                $node[$key] = $value;
            }
        }

        foreach ([
            'local' => 'local',
            'log-level' => 'log_level',
        ] as $option => $key) {
            $value = $input->getOption($option);
            if ($value !== null) {
                $runtime[$key] = $value;
            }
        }

        return $loader->fromArray([
            'node' => $node,
            'runtime' => $runtime,
        ]);
    }

    /**
     * @return list<string>
     */
    private function summaryLines(TuicClientConfig $config): array
    {
        return [
            sprintf('server: %s:%d', $config->node->server, $config->node->port),
            sprintf('uuid: %s', $config->node->uuid),
            sprintf('local bind: %s', $config->runtime->local),
            sprintf('sni: %s', $config->node->sni ?? '(auto)'),
            sprintf('alpn: %s', implode(', ', $config->node->alpn)),
            sprintf('udp relay mode: %s', $config->node->udpRelayMode),
            sprintf('congestion controller: %s', $config->node->congestionController),
            sprintf('allow insecure: %s', $config->node->allowInsecure ? 'yes' : 'no'),
            sprintf('log level: %s', $config->runtime->logLevel),
        ];
    }
}
