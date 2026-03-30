<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Http;

use PhpTuic\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class HttpResponseTest extends TestCase
{
    public function testHeaderReturnsFirstValue(): void
    {
        $response = new HttpResponse(
            protocolVersion: '1.1',
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: ['content-type' => ['application/json', 'text/plain']],
            body: '{"ok":true}',
            raw: "HTTP/1.1 200 OK\r\n\r\n{\"ok\":true}",
        );

        self::assertSame('application/json', $response->header('content-type'));
        self::assertSame('fallback', $response->header('x-missing', 'fallback'));
    }

    public function testJsonDecodesBody(): void
    {
        $response = new HttpResponse(
            protocolVersion: '1.1',
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: ['content-type' => ['application/json']],
            body: '{"ok":true}',
            raw: "HTTP/1.1 200 OK\r\n\r\n{\"ok\":true}",
        );

        self::assertSame(['ok' => true], $response->json());
    }
}
