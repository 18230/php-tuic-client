<?php declare(strict_types=1);

namespace PhpTuic\Command;

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Native\Quiche\QuicheBindings;
use PhpTuic\Native\Quiche\QuicheLibraryResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'doctor', description: 'Check runtime prerequisites for the quiche-backed TUIC client.')]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Inline TUIC node config in YAML or JSON.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON file that contains the TUIC node config.')
            ->addOption('node-name', null, InputOption::VALUE_REQUIRED, 'Optional node name when the config contains multiple proxies.')
            ->addOption('listen', null, InputOption::VALUE_REQUIRED, 'Local SOCKS5 listen address.', '127.0.0.1:1080')
            ->addOption('quiche-lib', null, InputOption::VALUE_REQUIRED, 'Absolute path or library name of the libquiche shared library.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failures = 0;

        $io->title('tuic-client doctor');

        foreach (['ffi', 'json', 'openssl', 'sockets'] as $extension) {
            $loaded = extension_loaded($extension);
            $io->writeln(sprintf(
                $loaded ? '<info>OK</info> ext-%s loaded' : '<error>FAIL</error> ext-%s missing',
                $extension,
            ));

            $failures += $loaded ? 0 : 1;
        }

        if (version_compare(PHP_VERSION, '8.2.4', '>=')) {
            $io->writeln(sprintf('<info>OK</info> PHP %s', PHP_VERSION));
        } else {
            $io->writeln(sprintf('<error>FAIL</error> PHP %s, required >= 8.2.4', PHP_VERSION));
            $failures++;
        }

        $listen = (string) $input->getOption('listen');
        $io->writeln(sprintf('<info>OK</info> SOCKS5 listener %s', $listen));

        try {
            $node = (new NodeInputResolver())->resolve(
                inlineNode: self::nullableString($input->getOption('node')),
                configPath: self::nullableString($input->getOption('config')),
                nodeName: self::nullableString($input->getOption('node-name')),
            );

            $io->writeln(sprintf('<info>OK</info> Parsed node "%s" for %s:%d', $node->name, $node->server, $node->port));
            $io->writeln(sprintf('<info>OK</info> ALPN: %s', implode(', ', $node->alpn)));
            $io->writeln(sprintf('<info>OK</info> SNI: %s', $node->sni));
        } catch (\Throwable $throwable) {
            $io->writeln(sprintf('<error>FAIL</error> %s', $throwable->getMessage()));
            $failures++;
        }

        $libraryResolver = new QuicheLibraryResolver(self::nullableString($input->getOption('quiche-lib')));
        $triplet = $libraryResolver->platformTriplet();
        $io->writeln(sprintf('<info>OK</info> Detected native triplet: %s', $triplet->asString()));
        try {
            $resolved = $libraryResolver->resolve();
            $io->writeln(sprintf('<info>OK</info> libquiche candidate: %s', $resolved));

            $bindings = new QuicheBindings($resolved);
            $io->writeln(sprintf('<info>OK</info> quiche version: %s', $bindings->version()));
        } catch (\Throwable $throwable) {
            $io->writeln(sprintf('<error>FAIL</error> %s', $throwable->getMessage()));
            $failures++;

            foreach ($libraryResolver->candidates() as $candidate) {
                $io->writeln(sprintf('  - searched: %s', $candidate));
            }

            $this->printBuildHints($io);
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function printBuildHints(SymfonyStyle $io): void
    {
        $io->section('Build Hints');
        $io->writeln('Official release tags vendor prebuilt x64 libraries under resources/native/.');
        $io->writeln('If you are using a dev branch or another architecture, build cloudflare/quiche as a shared library with the ffi feature enabled.');
        $io->writeln('Windows: install Rust, CMake, and NASM, then run scripts/build-quiche.ps1.');
        $io->writeln('Linux/macOS: install Rust and CMake, then run scripts/build-quiche.sh.');
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
