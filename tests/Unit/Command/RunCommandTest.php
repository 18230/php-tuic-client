<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Command;

use PhpTuic\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class RunCommandTest extends TestCase
{
    public function testDryRunWithInlineNodeSucceeds(): void
    {
        $application = new Application();
        $command = $application->find('run');
        $tester = new CommandTester($command);

        $tester->execute([
            '--node' => "{ name: demo, type: tuic, server: example.com, port: 443, uuid: 44444444-4444-4444-4444-444444444444, password: secret, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: example.com }",
            '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('tuic-client dry run', $tester->getDisplay());
        self::assertStringContainsString('SOCKS5 listener: 127.0.0.1:1080', $tester->getDisplay());
    }
}
