<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Runtime;

use PhpTuic\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class RunOptionsTest extends TestCase
{
    public function testRunOptionsNormalizeProductionFlags(): void
    {
        $input = new ArrayInput([
            '--listen' => '127.0.0.1:11080',
            '--allow-ip' => ['127.0.0.1', '10.0.0.0/24,192.168.0.0/16'],
            '--max-connections' => '2048',
            '--connect-timeout' => '12.5',
            '--idle-timeout' => '600',
            '--handshake-timeout' => '20',
            '--status-file' => 'runtime/status.json',
            '--status-interval' => '15',
            '--log-file' => 'runtime/proxy.log',
            '--pid-file' => 'runtime/proxy.pid',
        ], self::definition());

        $options = RunOptions::fromInput($input, true);

        self::assertSame('127.0.0.1:11080', $options->listenAddress);
        self::assertSame(['127.0.0.1', '10.0.0.0/24', '192.168.0.0/16'], $options->allowIps);
        self::assertSame(2048, $options->maxConnections);
        self::assertSame(12.5, $options->connectTimeout);
        self::assertSame(600, $options->idleTimeoutSeconds);
        self::assertSame(20.0, $options->handshakeTimeout);
        self::assertSame('runtime/status.json', $options->statusFile);
        self::assertSame(15, $options->statusInterval);
        self::assertSame('runtime/proxy.log', $options->logFile);
        self::assertSame('runtime/proxy.pid', $options->pidFile);
        self::assertTrue($options->verbose);
    }

    private static function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('listen', null, InputOption::VALUE_REQUIRED),
            new InputOption('allow-ip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
            new InputOption('max-connections', null, InputOption::VALUE_REQUIRED),
            new InputOption('connect-timeout', null, InputOption::VALUE_REQUIRED),
            new InputOption('idle-timeout', null, InputOption::VALUE_REQUIRED),
            new InputOption('handshake-timeout', null, InputOption::VALUE_REQUIRED),
            new InputOption('status-file', null, InputOption::VALUE_REQUIRED),
            new InputOption('status-interval', null, InputOption::VALUE_REQUIRED),
            new InputOption('log-file', null, InputOption::VALUE_REQUIRED),
            new InputOption('pid-file', null, InputOption::VALUE_REQUIRED),
        ]);
    }
}
