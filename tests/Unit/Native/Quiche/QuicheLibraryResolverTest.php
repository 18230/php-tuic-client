<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Native\Quiche;

use PhpTuic\Native\Quiche\QuicheLibraryResolver;
use PhpTuic\Native\Quiche\PlatformTriplet;
use PHPUnit\Framework\TestCase;

final class QuicheLibraryResolverTest extends TestCase
{
    public function testExplicitPathIsPreferred(): void
    {
        $resolver = new QuicheLibraryResolver('C:\\quiche\\quiche.dll', 'E:\\ss\\php-tuic-client');

        self::assertSame('C:\\quiche\\quiche.dll', $resolver->candidates()[0]);
    }

    public function testDefaultFileNameMatchesPlatform(): void
    {
        $resolver = new QuicheLibraryResolver();

        $expected = match (PHP_OS_FAMILY) {
            'Windows' => 'quiche.dll',
            'Darwin' => 'libquiche.dylib',
            default => 'libquiche.so',
        };

        self::assertSame($expected, $resolver->defaultLibraryFileName());
    }

    public function testTripletSpecificCandidateIsIncludedBeforePlatformFallback(): void
    {
        $resolver = new QuicheLibraryResolver(null, 'E:\\ss\\php-tuic-client');
        $triplet = $resolver->platformTriplet();
        $platform = $triplet->platform;
        $fileName = $resolver->defaultLibraryFileName();
        $candidates = $resolver->candidates();

        self::assertContains("E:\\ss\\php-tuic-client/resources/native/{$triplet->asString()}/{$fileName}", $candidates);
        self::assertContains("E:\\ss\\php-tuic-client/resources/native/{$platform}/{$fileName}", $candidates);
        self::assertLessThan(
            array_search("E:\\ss\\php-tuic-client/resources/native/{$platform}/{$fileName}", $candidates, true),
            array_search("E:\\ss\\php-tuic-client/resources/native/{$triplet->asString()}/{$fileName}", $candidates, true),
        );
    }

    public function testPlatformTripletUsesKnownPlatformNames(): void
    {
        $triplet = PlatformTriplet::detect();

        self::assertContains($triplet->platform, ['windows', 'linux', 'macos']);
        self::assertNotSame('', $triplet->architecture);
    }
}
