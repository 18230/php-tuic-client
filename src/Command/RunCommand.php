<?php declare(strict_types=1);

namespace PhpTuic\Command;

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Runtime\ProxyRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'run', description: 'Start the local HTTP and SOCKS5 proxy listeners and relay through a TUIC node.')]
final class RunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Inline TUIC node config in YAML or JSON.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON file that contains the TUIC node config.')
            ->addOption('node-name', null, InputOption::VALUE_REQUIRED, 'Optional node name when the config contains multiple proxies.')
            ->addOption('http-listen', null, InputOption::VALUE_REQUIRED, 'Local HTTP proxy listen address.', '127.0.0.1:8080')
            ->addOption('socks-listen', null, InputOption::VALUE_REQUIRED, 'Local SOCKS5 proxy listen address.', '127.0.0.1:1080')
            ->addOption('no-http', null, InputOption::VALUE_NONE, 'Disable the local HTTP proxy listener.')
            ->addOption('no-socks', null, InputOption::VALUE_NONE, 'Disable the local SOCKS5 proxy listener.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse the configuration and print the resolved runtime without starting listeners.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $node = (new NodeInputResolver())->resolve(
            inlineNode: self::nullableString($input->getOption('node')),
            configPath: self::nullableString($input->getOption('config')),
            nodeName: self::nullableString($input->getOption('node-name')),
        );

        $httpListen = (string) $input->getOption('http-listen');
        $socksListen = (string) $input->getOption('socks-listen');
        $httpEnabled = !$input->getOption('no-http');
        $socksEnabled = !$input->getOption('no-socks');

        if (!$httpEnabled && !$socksEnabled) {
            throw new \RuntimeException('Both proxy listeners are disabled.');
        }

        if ($input->getOption('dry-run')) {
            $io->title('tuic-client dry run');
            $io->writeln(sprintf('Node: %s (%s:%d)', $node->name, $node->server, $node->port));
            $io->writeln(sprintf('ALPN: %s', implode(', ', $node->alpn)));
            $io->writeln(sprintf('HTTP listener: %s', $httpEnabled ? $httpListen : 'disabled'));
            $io->writeln(sprintf('SOCKS5 listener: %s', $socksEnabled ? $socksListen : 'disabled'));
            $io->writeln(sprintf('TUIC cert verification: %s', $node->skipCertVerify ? 'disabled' : 'enabled'));

            return Command::SUCCESS;
        }

        $runner = new ProxyRunner(
            node: $node,
            httpListen: $httpListen,
            socksListen: $socksListen,
            enableHttp: $httpEnabled,
            enableSocks: $socksEnabled,
        );

        $runner->run();

        return Command::SUCCESS;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
