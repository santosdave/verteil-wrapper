<?php

namespace Santosdave\VerteilWrapper\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * Creates the Guzzle clients the package talks to Verteil with.
 *
 * By default it builds exactly what the package always built. Bind your own instance with a
 * handler stack to observe or record traffic (middleware), or to fake Verteil in tests:
 *
 *     $app->instance(HttpClientFactory::class, new HttpClientFactory(HandlerStack::create($mock)));
 */
class HttpClientFactory
{
    public function __construct(private ?HandlerStack $handler = null) {}

    public function make(array $options = []): Client
    {
        if ($this->handler !== null) {
            $options['handler'] = $this->handler;
        }

        return new Client($options);
    }
}
