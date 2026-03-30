<?php declare(strict_types=1);

namespace PhpTuic\Transport;

use Amp\Quic\Internal\Quiche\QuicheConnection as InternalQuicheConnection;
use Amp\Quic\Internal\Quiche\QuicheSocket as InternalQuicheSocket;

final class UnidirectionalStreamFactory
{
    public static function create(InternalQuicheConnection $connection): InternalQuicheSocket
    {
        $stream = new InternalQuicheSocket($connection);
        $stream->closed = InternalQuicheSocket::UNREADABLE;

        return $stream;
    }
}
