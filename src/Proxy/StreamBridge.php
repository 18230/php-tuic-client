<?php declare(strict_types=1);

namespace PhpTuic\Proxy;

use Amp\Quic\QuicSocket;
use Amp\Socket\Socket;
use function Amp\async;
use function Amp\Future\awaitAll;
use function Amp\Future\awaitFirst;

final class StreamBridge
{
    public static function tunnel(Socket $local, QuicSocket $remote, string $initialRemoteData = ''): void
    {
        if ($initialRemoteData !== '') {
            $remote->write($initialRemoteData);
        }

        $uplink = async(static function () use ($local, $remote): void {
            try {
                while (($chunk = $local->read()) !== null) {
                    $remote->write($chunk);
                }
                $remote->end();
            } catch (\Throwable) {
                try {
                    $remote->close();
                } catch (\Throwable) {
                }
            }
        });

        $downlink = async(static function () use ($local, $remote): void {
            try {
                while (($chunk = $remote->read()) !== null) {
                    $local->write($chunk);
                }
                $local->end();
            } catch (\Throwable) {
                try {
                    $local->close();
                } catch (\Throwable) {
                }
            }
        });

        try {
            awaitFirst([$uplink, $downlink]);
        } finally {
            try {
                $remote->close();
            } catch (\Throwable) {
            }

            try {
                $local->close();
            } catch (\Throwable) {
            }

            awaitAll([$uplink, $downlink]);
        }
    }

    public static function relayResponse(Socket $local, QuicSocket $remote, string $initialRemoteData = ''): void
    {
        if ($initialRemoteData !== '') {
            $remote->write($initialRemoteData);
        }

        $uplink = async(static function () use ($local, $remote): void {
            try {
                while (($chunk = $local->read()) !== null) {
                    $remote->write($chunk);
                }
                $remote->end();
            } catch (\Throwable) {
                try {
                    $remote->close();
                } catch (\Throwable) {
                }
            }
        });

        try {
            while (($chunk = $remote->read()) !== null) {
                $local->write($chunk);
            }
            $local->end();
        } finally {
            try {
                $remote->close();
            } catch (\Throwable) {
            }

            awaitAll([$uplink]);
        }
    }

    public static function pipeResponse(Socket $local, QuicSocket $remote): void
    {
        try {
            while (($chunk = $remote->read()) !== null) {
                $local->write($chunk);
            }
            $local->end();
        } finally {
            try {
                $remote->close();
            } catch (\Throwable) {
            }
        }
    }
}
