<?php declare(strict_types=1);

namespace PhpTuic\Native\Quiche;

final class SocketAddress
{
    /**
     * @param \FFI\CData $storage
     */
    private function __construct(
        private readonly \FFI $ffi,
        public readonly \FFI\CData $storage,
        public readonly int $length,
        public readonly string $host,
        public readonly int $port,
        public readonly int $family,
    ) {
    }

    public static function fromEndpoint(QuicheBindings $bindings, string $host, int $port): self
    {
        $packed = @inet_pton($host);
        if ($packed === false) {
            throw new \RuntimeException("Invalid IP address: {$host}");
        }

        $ffi = $bindings->ffi();
        if (strlen($packed) === 4) {
            $address = $ffi->new('struct sockaddr_in');
            if (QuicheBindings::usesBsdSockaddrLayout()) {
                $address->sin_len = \FFI::sizeof($address);
            }
            $address->sin_family = QuicheBindings::afInet();
            $address->sin_port = self::hostToNetworkShort($port);
            $address->sin_addr->s_addr = unpack('Nip', $packed)['ip'];

            return new self($ffi, $address, \FFI::sizeof($address), $host, $port, QuicheBindings::afInet());
        }

        if (strlen($packed) === 16) {
            $address = $ffi->new('struct sockaddr_in6');
            if (QuicheBindings::usesBsdSockaddrLayout()) {
                $address->sin6_len = \FFI::sizeof($address);
            }
            $address->sin6_family = QuicheBindings::afInet6();
            $address->sin6_port = self::hostToNetworkShort($port);

            for ($i = 0; $i < 16; $i++) {
                $address->sin6_addr->s6_addr[$i] = ord($packed[$i]);
            }

            return new self($ffi, $address, \FFI::sizeof($address), $host, $port, QuicheBindings::afInet6());
        }

        throw new \RuntimeException("Unsupported IP address family for {$host}");
    }

    public static function fromStreamName(QuicheBindings $bindings, string $name): self
    {
        [$host, $port] = self::parseStreamName($name);

        return self::fromEndpoint($bindings, $host, $port);
    }

    /**
     * @return array{0: string, 1: int}
     */
    public static function parseStreamName(string $value): array
    {
        $value = trim($value);

        if (preg_match('/^\[(.+)]:(\d+)$/', $value, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        $lastColon = strrpos($value, ':');
        if ($lastColon === false) {
            throw new \RuntimeException("Invalid socket endpoint: {$value}");
        }

        $host = substr($value, 0, $lastColon);
        $port = (int) substr($value, $lastColon + 1);

        return [$host, $port];
    }

    /**
     * @return \FFI\CData
     */
    public function asConstSockaddrPointer(): \FFI\CData
    {
        return $this->ffi->cast('const struct sockaddr *', \FFI::addr($this->storage));
    }

    public function asMutableSockaddrPointer(): \FFI\CData
    {
        return $this->ffi->cast('struct sockaddr *', \FFI::addr($this->storage));
    }

    private static function hostToNetworkShort(int $port): int
    {
        return unpack('vport', pack('n', $port))['port'];
    }
}
