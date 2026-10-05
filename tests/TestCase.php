<?php

namespace Santosdave\VerteilWrapper\Tests;

use GuzzleHttp\HandlerStack;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Santosdave\VerteilWrapper\Http\HttpClientFactory;
use Santosdave\VerteilWrapper\Services\VerteilService;
use Santosdave\VerteilWrapper\VerteilServiceProvider;

/**
 * A Laravel app with the package, fake credentials and no network: Verteil is FakeVerteil.
 */
abstract class TestCase extends BaseTestCase
{
    protected FakeVerteil $verteil;

    protected function getPackageProviders($app): array
    {
        return [VerteilServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32))); // tokens are stored encrypted
        $app['config']->set('verteil.base_url', 'https://verteil.test');
        $app['config']->set('verteil.username', 'test-user');
        $app['config']->set('verteil.password', 'test-password');
        $app['config']->set('verteil.third_party_id', 'KQ');
        $app['config']->set('verteil.office_id', 'TEST-OFFICE');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->verteil = new FakeVerteil;
        $this->app->instance(HttpClientFactory::class, new HttpClientFactory(HandlerStack::create($this->verteil)));
    }

    protected static function service(array $overrides = []): VerteilService
    {
        return new VerteilService(array_replace(config('verteil'), $overrides));
    }

    protected static function cancel(?VerteilService $service = null): array
    {
        return ($service ?? self::service())->cancelOrder(['Owner' => 'KQ', 'OrderID' => 'ORDER-1']);
    }
}
