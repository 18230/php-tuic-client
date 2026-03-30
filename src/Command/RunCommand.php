<?php declare(strict_types=1);

namespace PhpTuic\Command;

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Runtime\ProxyRunner;
use PhpTuic\Runtime\RunOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'run', description: 'Start the local SOCKS5 proxy and relay through a TUIC node using quiche FFI.')]
final class RunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Inline TUIC node config in YAML or JSON.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON file that contains the TUIC node config.')
            ->addOption('node-name', null, InputOption::VALUE_REQUIRED, 'Optional node name when the config contains multiple proxies.')
            ->addOption('listen', null, InputOption::VALUE_REQUIRED, 'Local SOCKS5 listen address.', '127.0.0.1:1080')
            ->addOption('allow-ip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Allow a local client IP or CIDR. Repeat the option to add multiple rules.')
            ->addOption('max-connections', null, InputOption::VALUE_REQUIRED, 'Maximum number of concurrent local client connections.', '1024')
            ->addOption('connect-timeout', null, InputOption::VALUE_REQUIRED, 'Timeout in seconds for opening the TUIC UDP socket.', '10')
            ->addOption('idle-timeout', null, InputOption::VALUE_REQUIRED, 'QUIC idle timeout in seconds.', '300')
            ->addOption('handshake-timeout', null, InputOption::VALUE_REQUIRED, 'Maximum time in seconds to finish QUIC + TUIC authentication.', '15')
            ->addOption('status-file', null, InputOption::VALUE_REQUIRED, 'Optional JSON status file that is refreshed while the proxy is running.')
            ->addOption('status-interval', null, InputOption::VALUE_REQUIRED, 'Status file refresh interval in seconds.', '10')
            ->addOption('log-file', null, InputOption::VALUE_REQUIRED, 'Optional runtime log file. Defaults to STDERR when omitted.')
            ->addOption('pid-file', null, InputOption::VALUE_REQUIRED, 'Optional PID file for supervisor/systemd integration.')
            ->addOption('quiche-lib', null, InputOption::VALUE_REQUIRED, 'Absolute path or library name of the libquiche shared library.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse the configuration and print the resolved runtime without starting the SOCKS5 listener.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $node = (new NodeInputResolver())->resolve(
            inlineNode: self::nullableString($input->getOption('node')),
            configPath: self::nullableString($input->getOption('config')),
            nodeName: self::nullableString($input->getOption('node-name')),
        );

        $options = RunOptions::fromInput($input, $output->isVerbose());
        $quicheLib = self::nullableString($input->getOption('quiche-lib'));

        if ($input->getOption('dry-run')) {
            $io->title('tuic-client dry run');
            $io->writeln(sprintf('Node: %s (%s:%d)', $node->name, $node->server, $node->port));
            $io->writeln(sprintf('ALPN: %s', implode(', ', $node->alpn)));
            $io->writeln(sprintf('SOCKS5 listener: %s', $options->listenAddress));
            $io->writeln(sprintf('libquiche: %s', $quicheLib ?? (getenv('QUICHE_LIB') ?: 'auto')));
            $io->writeln(sprintf('TUIC cert verification: %s', $node->skipCertVerify ? 'disabled' : 'enabled'));
            $io->writeln(sprintf('Max connections: %d', $options->maxConnections));
            $io->writeln(sprintf('Connect timeout: %.1fs', $options->connectTimeout));
            $io->writeln(sprintf('Idle timeout: %ds', $options->idleTimeoutSeconds));
            $io->writeln(sprintf('Handshake timeout: %.1fs', $options->handshakeTimeout));
            if ($options->allowIps !== []) {
                $io->writeln(sprintf('Allow IPs: %s', implode(', ', $options->allowIps)));
            }
            if ($options->statusFile !== null) {
                $io->writeln(sprintf('Status file: %s', $options->statusFile));
            }
            if ($options->logFile !== null) {
                $io->writeln(sprintf('Log file: %s', $options->logFile));
            }
            if ($options->pidFile !== null) {
                $io->writeln(sprintf('PID file: %s', $options->pidFile));
            }

            return Command::SUCCESS;
        }

        (new ProxyRunner(
            node: $node,
            options: $options,
            quicheLibrary: $quicheLib,
        ))->run();

        return Command::SUCCESS;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
