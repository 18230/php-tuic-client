<?php

declare(strict_types=1);

namespace TuicClient\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TuicClient\Command\DoctorCommand;

final class DoctorCommandTest extends TestCase
{
    public function testDoctorAcceptsTheExampleConfig(): void
    {
        $tester = new CommandTester(new DoctorCommand());
        $status = $tester->execute([
            '--config' => dirname(__DIR__, 3) . '/examples/node.example.yaml',
        ]);

        self::assertSame(0, $status);
        self::assertStringContainsString('OK TUIC config parsed', $tester->getDisplay());
    }
}
