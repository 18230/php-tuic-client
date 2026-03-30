<?php

declare(strict_types=1);

namespace TuicClient\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TuicClient\Command\RunCommand;

final class RunCommandTest extends TestCase
{
    public function testDryRunPrintsTheResolvedConfig(): void
    {
        $tester = new CommandTester(new RunCommand());
        $status = $tester->execute([
            '--config' => dirname(__DIR__, 3) . '/examples/node.example.yaml',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Resolved TUIC client configuration', $tester->getDisplay());
        self::assertStringContainsString('Dry run successful', $tester->getDisplay());
    }
}
