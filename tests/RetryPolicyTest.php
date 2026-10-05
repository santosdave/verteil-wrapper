<?php

namespace Santosdave\VerteilWrapper\Tests;

/**
 * A failed call is only sent again when that cannot do anything twice. Order create, cancel
 * and change may already have been applied when the connection dropped, and sending them
 * again could create or change an order twice: they are reported instead. Reads (shopping,
 * pricing, retrieve, reshop, seat and service lists) are retried.
 */
class RetryPolicyTest extends TestCase
{
    public function test_a_cancellation_whose_connection_dropped_is_never_sent_again(): void
    {
        $this->verteil->statuses = [0, 200];

        $failure = null;
        try {
            self::cancel();
        } catch (\GuzzleHttp\Exception\ConnectException|\Santosdave\VerteilWrapper\Exceptions\VerteilApiException $e) {
            $failure = $e;
        }

        $this->assertNotNull($failure, 'The dropped connection must surface, not be retried away');
        $this->assertStringContainsString('timed out', $failure->getMessage());
        $this->assertCount(1, $this->verteil->apiRequests());
    }

    public function test_an_order_create_whose_connection_dropped_is_never_sent_again(): void
    {
        $this->verteil->statuses = [0, 200];
        $service = self::service();
        $call = (fn (string $endpoint) => $this->retryHandler->execute(fn () => $this->client->post('/entrygate/rest/request:'.$endpoint), $endpoint))->bindTo($service, $service);
        $service->authenticate()->setAuthorizationHeader();

        $failed = false;
        try {
            $call('orderCreate');
        } catch (\GuzzleHttp\Exception\ConnectException|\Santosdave\VerteilWrapper\Exceptions\VerteilApiException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'The dropped connection must surface, not be retried away');
        $this->assertCount(1, $this->verteil->apiRequests());
    }

    public function test_a_retrieve_whose_connection_dropped_is_tried_again(): void
    {
        $this->verteil->statuses = [0, 200];

        try {
            self::service()->retrieveOrder(['Query' => ['Filters' => ['OrderID' => ['Owner' => 'KQ', 'value' => 'ORDER-1']]]]);
        } catch (\Throwable) {
            // the fake's answer is not an order view; only the attempts matter here
        }

        $this->assertCount(2, $this->verteil->apiRequests());
    }
}
