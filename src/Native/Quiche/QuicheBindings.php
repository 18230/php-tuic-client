<?php declare(strict_types=1);

namespace PhpTuic\Native\Quiche;

final class QuicheBindings
{
    public const PROTOCOL_VERSION = 0x00000001;
    public const ERR_DONE = -1;
    public const ERR_BUFFER_TOO_SHORT = -2;
    public const ERR_INVALID_STATE = -6;
    public const ERR_STREAM_STOPPED = -15;
    public const ERR_STREAM_RESET = -16;

    private \FFI $ffi;

    public function __construct(?string $libraryPath = null)
    {
        $resolver = new QuicheLibraryResolver($libraryPath);
        $this->ffi = \FFI::cdef(QuicheCdef::definitions(), $resolver->resolve());
    }

    public function ffi(): \FFI
    {
        return $this->ffi;
    }

    public function version(): string
    {
        $version = $this->ffi->quiche_version();

        return is_string($version) ? $version : \FFI::string($version);
    }

    public static function afInet(): int
    {
        return \defined('AF_INET') ? AF_INET : 2;
    }

    public static function afInet6(): int
    {
        return \defined('AF_INET6') ? AF_INET6 : 10;
    }

    public static function usesBsdSockaddrLayout(): bool
    {
        return \PHP_OS_FAMILY === 'Darwin';
    }
}
