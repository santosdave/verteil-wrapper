<?php

namespace Santosdave\VerteilWrapper\Tests;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A Guzzle handler standing in for Verteil: issues numbered tokens, answers the NDC calls
 * (with statuses you queue up), and remembers every request.
 */
class FakeVerteil
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<int> statuses for the next NDC calls; 200 once empty */
    public array $statuses = [];

    private int $tokens = 0;

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $this->requests[] = $request;

        if ($request->getUri()->getPath() === '/oauth2/token') {
            $this->tokens++;

            return Create::promiseFor(self::json(200, ['access_token' => 'token-'.$this->tokens, 'token_type' => 'bearer', 'expires_in' => 3600]));
        }

        $status = array_shift($this->statuses) ?? 200;

        return Create::promiseFor($status === 200
            ? self::json(200, ['Response' => ['OrderCancel' => 'OK'], 'Success' => true])
            : self::json($status, ['Errors' => ['Error' => [['value' => "Error {$status} from Verteil"]]]]));
    }

    /**
     * @return list<RequestInterface>
     */
    public function tokenRequests(): array
    {
        return array_values(array_filter($this->requests, fn (RequestInterface $r) => $r->getUri()->getPath() === '/oauth2/token'));
    }

    /**
     * @return list<RequestInterface>
     */
    public function apiRequests(): array
    {
        return array_values(array_filter($this->requests, fn (RequestInterface $r) => $r->getUri()->getPath() !== '/oauth2/token'));
    }

    private static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
