<?php

namespace Santosdave\VerteilWrapper\Tests;

use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;

/**
 * NDC calls and the token behind them.
 */
class RequestTest extends TestCase
{
    public function test_the_token_request_uses_client_credentials(): void
    {
        self::cancel();

        $token = $this->verteil->tokenRequests()[0];
        $this->assertSame('POST', $token->getMethod());
        $this->assertSame('Basic '.base64_encode('test-user:test-password'), $token->getHeaderLine('Authorization'));
        parse_str($token->getUri()->getQuery(), $query);
        $this->assertSame(['grant_type' => 'client_credentials', 'scope' => 'api'], $query);
    }

    public function test_a_call_sends_the_token_service_header_and_json_body(): void
    {
        $result = self::cancel();

        $this->assertSame(['OrderCancel' => 'OK'], $result['Response']);
        $call = $this->verteil->apiRequests()[0];
        $this->assertSame('/entrygate/rest/request:orderCancel', $call->getUri()->getPath());
        $this->assertSame('Bearer token-1', $call->getHeaderLine('Authorization'));
        $this->assertSame('OrderCancel', $call->getHeaderLine('service'));
        $this->assertIsArray(json_decode((string) $call->getBody(), true));
    }

    public function test_the_token_is_reused_by_later_service_instances(): void
    {
        self::cancel();
        self::cancel();
        self::cancel();

        $this->assertCount(1, $this->verteil->tokenRequests());
    }

    public function test_an_api_error_carries_verteils_message(): void
    {
        $this->verteil->statuses = [400, 400, 400];

        try {
            self::cancel();
            $this->fail('Expected an exception');
        } catch (VerteilApiException $e) {
            $this->assertStringContainsString('Error 400 from Verteil', $e->getMessage());
        }
    }
}
