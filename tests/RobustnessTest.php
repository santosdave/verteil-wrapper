<?php

namespace Santosdave\VerteilWrapper\Tests;

use Illuminate\Support\Facades\Cache;
use Santosdave\VerteilWrapper\Exceptions\VerteilApiException;

/**
 * Behaviour under pressure and with several accounts: rate limits, a token Verteil keeps
 * rejecting, and two agencies on one application.
 */
class RobustnessTest extends TestCase
{
    public function test_a_rate_limited_call_says_when_to_retry(): void
    {
        $service = self::service();
        for ($i = 0; $i < 60; $i++) { // the default limit: 60 calls a minute
            self::cancel($service);
        }

        try {
            self::cancel($service);
            $this->fail('Expected the rate limit');
        } catch (VerteilApiException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertMatchesRegularExpression('/Try again in \d+ seconds/', $e->getMessage());
        }
    }

    public function test_a_token_verteil_keeps_rejecting_is_renewed_once_not_forever(): void
    {
        $this->verteil->statuses = [401, 401, 401, 401, 401, 401];

        try {
            self::cancel();
            $this->fail('Expected the rejection to surface');
        } catch (VerteilApiException $e) {
            $this->assertSame(401, $e->getCode());
        }
        $this->assertLessThanOrEqual(2, count($this->verteil->tokenRequests()));
    }

    public function test_a_rejected_token_is_renewed_and_the_call_succeeds(): void
    {
        self::cancel();
        $this->verteil->statuses = [401];

        $result = self::cancel();

        $this->assertSame(['OrderCancel' => 'OK'], $result['Response']);
        $this->assertCount(2, $this->verteil->tokenRequests());
    }

    public function test_two_accounts_keep_their_own_tokens(): void
    {
        $agencyA = self::service(['username' => 'agency-a', 'password' => 'pw-a']);
        $agencyB = self::service(['username' => 'agency-b', 'password' => 'pw-b']);

        foreach ([$agencyA, $agencyB, $agencyA, $agencyB] as $agency) {
            self::cancel($agency);
        }

        $logins = array_map(fn ($r) => $r->getHeaderLine('Authorization'), $this->verteil->tokenRequests());
        $this->assertSame(['Basic '.base64_encode('agency-a:pw-a'), 'Basic '.base64_encode('agency-b:pw-b')], $logins);
        $sent = array_map(fn ($r) => $r->getHeaderLine('Authorization'), $this->verteil->apiRequests());
        $this->assertSame(['Bearer token-1', 'Bearer token-2', 'Bearer token-1', 'Bearer token-2'], $sent);
    }

    public function test_each_account_sends_its_own_office_id(): void
    {
        $agencyB = self::service(['username' => 'agency-b', 'office_id' => 'OFFICE-B']);

        try {
            $agencyB->retrieveOrder(['Query' => ['Filters' => ['OrderID' => ['Owner' => 'KQ', 'value' => 'ORDER-1']]]]);
        } catch (\Throwable) {
            // the fake's answer is not an order view; only the request matters here
        }

        $this->assertSame('OFFICE-B', $this->verteil->apiRequests()[0]->getHeaderLine('OfficeId'));
    }

    public function test_one_accounts_rate_limit_does_not_block_another(): void
    {
        $agencyA = self::service(['username' => 'agency-a']);
        for ($i = 0; $i < 60; $i++) {
            self::cancel($agencyA);
        }

        $result = self::cancel(self::service(['username' => 'agency-b']));

        $this->assertSame(['OrderCancel' => 'OK'], $result['Response']);
    }
}
