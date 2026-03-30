<?php declare(strict_types=1);

namespace PhpTuic\Native\Quiche;

final class QuicheLibraryResolver
{
    public function __construct(
        private readonly ?string $explicitPath = null,
        private readonly ?string $projectRoot = null,
    ) {
    }

    public function resolve(): string
    {
        foreach ($this->candidates() as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if ($this->isLoadableAlias($candidate) || is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Unable to locate a loadable libquiche shared library. Set QUICHE_LIB or pass --quiche-lib.',
        );
    }

    /**
     * @return list<string>
     */
    public function candidates(): array
    {
        $projectRoot = $this->projectRoot ?? dirname(__DIR__, 3);
        $triplet = $this->platformTriplet();
        $platform = $triplet->platform;

        $candidates = array_filter([
            $this->explicitPath,
            getenv('QUICHE_LIB') ?: null,
            $projectRoot . "/resources/native/{$triplet->asString()}/" . $this->defaultLibraryFileName(),
            $projectRoot . "/resources/native/{$platform}/" . $this->defaultLibraryFileName(),
            $projectRoot . "/native/{$triplet->asString()}/" . $this->defaultLibraryFileName(),
            $projectRoot . "/native/{$platform}/" . $this->defaultLibraryFileName(),
            $projectRoot . "/runtime/quiche/{$triplet->asString()}/" . $this->defaultLibraryFileName(),
            $projectRoot . "/runtime/quiche/{$platform}/" . $this->defaultLibraryFileName(),
            $this->defaultLibraryFileName(),
            $this->defaultLibraryAlias(),
        ]);

        return array_values(array_unique($candidates));
    }

    public function platformTriplet(): PlatformTriplet
    {
        return PlatformTriplet::detect();
    }

    public function defaultLibraryFileName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'quiche.dll',
            'Darwin' => 'libquiche.dylib',
            default => 'libquiche.so',
        };
    }

    public function defaultLibraryAlias(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'quiche',
            default => 'quiche',
        };
    }

    private function isLoadableAlias(string $candidate): bool
    {
        return !str_contains($candidate, DIRECTORY_SEPARATOR)
            && !str_contains($candidate, '/')
            && preg_match('/^[A-Za-z0-9_.-]+$/', $candidate) === 1;
    }
}
