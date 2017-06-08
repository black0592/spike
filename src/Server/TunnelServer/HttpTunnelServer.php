<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Server\TunnelServer;

use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\str;
use React\Socket\ConnectionInterface;
use Spike\Buffer\HeaderBuffer;
use Spike\Exception\UnsupportedProtocolException;
use Spike\Protocol\HttpRequest;

class HttpTunnelServer extends TunnelServer
{
    public function handleConnection(ConnectionInterface $connection)
    {
        try {
            $buffer = new HeaderBuffer($connection);
            $buffer->gather(function ($buffer) use ($connection) {
                $psrRequest = HttpRequest::fromString($buffer)->getRequest();
                $host = $psrRequest->getUri()->getHost();
                if ($psrRequest->getUri()->getPort()) {
                    $host .= "{$psrRequest->getUri()->getPort()}";
                }
                if ($this->tunnel->supportHost($host)) {
                    $this->tunnel->getConnection()->write($buffer);
                    $this->tunnel->pipe($connection);
                } else {
                    $body = sprintf('The host "%s" was not binding.', $host);
                    $response = $this->makeErrorResponse(404, $body);
                    $connection->write(str($response));
                    $connection->end();
                }
            });
        } catch (UnsupportedProtocolException $exception) {
            $response = $this->makeErrorResponse(404, $exception->getMessage());
            $connection->write(str($response));
            $connection->end();
        }
    }

    protected function makeErrorResponse($code, $message)
    {
        $message = $message ?: 'Proxy error';
        return new Response($code, [
            'Content-Length' => strlen($message)
        ], $message);
    }
}