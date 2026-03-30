<?php declare(strict_types=1);

namespace PhpTuic\Config;

final readonly class NodeConfig
{
    /**
     * @param list<string> $alpn
     */
    public function __construct(
        public string $name,
        public string $server,
        public int $port,
        public string $uuid,
        public string $password,
        public array $alpn,
        public string $sni,
        public bool $skipCertVerify,
        public bool $disableSni,
        public bool $reduceRtt,
        public string $udpRelayMode,
        public string $congestionControl,
    ) {
    }
}
