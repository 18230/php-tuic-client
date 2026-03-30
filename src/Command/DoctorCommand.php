<?php declare(strict_types=1);

namespace PhpTuic\Command;

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Support\Platform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'doctor', description: 'Check runtime prerequisites and validate the current TUIC configuration.')]
final class DoctorCommand extends Command
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
            ->addOption('no-socks', null, InputOption::VALUE_NONE, 'Disable the local SOCKS5 proxy listener.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failures = 0;

        $io->title('tuic-client doctor');

        foreach (['curl', 'ffi', 'json', 'openssl', 'sockets'] as $extension) {
            $loaded = extension_loaded($extension);
            $loaded
                ? $io->writeln(sprintf('<info>OK</info> ext-%s loaded', $extension))
                : $io->writeln(sprintf('<error>FAIL</error> ext-%s missing', $extension));

            $failures += $loaded ? 0 : 1;
        }

        if (version_compare(PHP_VERSION, '8.2.4', '>=')) {
            $io->writeln(sprintf('<info>OK</info> PHP %s', PHP_VERSION));
        } else {
            $io->writeln(sprintf('<error>FAIL</error> PHP %s, required >= 8.2.4', PHP_VERSION));
            $failures++;
        }

        if (Platform::isWindows()) {
            $io->warning('Windows packaging, config parsing, and CLI dry-run are supported.');
        } else {
            foreach (['pcntl', 'posix'] as $extension) {
                if (extension_loaded($extension)) {
                    $io->writeln(sprintf('<info>OK</info> ext-%s loaded', $extension));
                    continue;
                }

                $io->warning(sprintf('ext-%s is not loaded. Signal handling and process supervision are better on Unix-like systems when it is available.', $extension));
            }
        }

        [$nativeBindingsOk, $nativeBindingMessage] = $this->checkNativeBindings();
        if ($nativeBindingsOk) {
            $io->writeln(sprintf('<info>OK</info> %s', $nativeBindingMessage));
        } else {
            $io->writeln(sprintf('<error>FAIL</error> %s', $nativeBindingMessage));
            $failures++;
        }

        try {
            $node = (new NodeInputResolver())->resolve(
                inlineNode: self::nullableString($input->getOption('node')),
                configPath: self::nullableString($input->getOption('config')),
                nodeName: self::nullableString($input->getOption('node-name')),
            );

            $io->writeln(sprintf('<info>OK</info> Parsed node "%s" for %s:%d', $node->name, $node->server, $node->port));
            $io->writeln(sprintf('<info>OK</info> ALPN: %s', implode(', ', $node->alpn)));
            $io->writeln(sprintf('<info>OK</info> SNI: %s', $node->sni));
            $io->writeln(sprintf('<info>OK</info> TUIC server cert verify: %s', $node->skipCertVerify ? 'disabled' : 'enabled'));
        } catch (\Throwable $throwable) {
            $io->writeln(sprintf('<error>FAIL</error> %s', $throwable->getMessage()));
            $failures++;
        }

        $httpEnabled = !$input->getOption('no-http');
        $socksEnabled = !$input->getOption('no-socks');

        if (!$httpEnabled && !$socksEnabled) {
            $io->writeln('<error>FAIL</error> Both local proxy listeners are disabled.');
            $failures++;
        } else {
            if ($httpEnabled) {
                $io->writeln(sprintf('<info>OK</info> HTTP proxy listen %s', (string) $input->getOption('http-listen')));
            }

            if ($socksEnabled) {
                $io->writeln(sprintf('<info>OK</info> SOCKS5 proxy listen %s', (string) $input->getOption('socks-listen')));
            }
        }

        $caFile = (string) ini_get('curl.cainfo');
        $opensslCaFile = (string) ini_get('openssl.cafile');
        if ($caFile !== '' || $opensslCaFile !== '') {
            $io->writeln(sprintf('<info>OK</info> PHP CA bundle configured (%s)', $caFile !== '' ? $caFile : $opensslCaFile));
        } else {
            $io->warning('No CA bundle is configured in php.ini. Proxied HTTPS requests from PHP cURL may fail certificate validation.');
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkNativeBindings(): array
    {
        $bindingsDir = dirname((new \ReflectionClass(\Amp\Quic\QuicheDriver::class))->getFileName()) . '/bindings';

        if (!is_dir($bindingsDir)) {
            return [false, 'Could not find the amphp/quic bindings directory.'];
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $dlls = glob($bindingsDir . '/libquiche-*.dll') ?: [];
            if ($dlls === []) {
                return [false, 'The current amphp/quic package does not ship Windows libquiche binaries, so the TUIC runtime is currently unsupported on Windows.'];
            }

            return [true, 'Windows libquiche binaries were found.'];
        }

        $artifacts = array_merge(
            glob($bindingsDir . '/libquiche-*.so') ?: [],
            glob($bindingsDir . '/libquiche-*.dylib') ?: [],
        );

        if ($artifacts === []) {
            return [false, 'No native libquiche artifacts were found for the current platform.'];
        }

        return [true, 'Native libquiche bindings found for the current platform.'];
    }
}
