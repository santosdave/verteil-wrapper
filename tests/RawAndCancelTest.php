<?php

namespace Santosdave\VerteilWrapper\Tests;

use Santosdave\VerteilWrapper\DataTypes\OrderCancel;

/**
 * send() answers Verteil's body untouched; OrderCancel::create() reaches Verteil as built.
 */
class RawAndCancelTest extends TestCase
{
    public function test_send_answers_the_body_as_verteil_sent_it(): void
    {
        $body = self::service()->send('orderCancel', OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1']]]));

        $this->assertSame(['Response' => ['OrderCancel' => 'OK'], 'Success' => true], $body);
    }

    public function test_a_cancel_built_by_order_cancel_create_is_sent_once_as_built_with_the_owner_as_third_party(): void
    {
        self::service()->cancelOrder(OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1', 'channel' => 'NDC']]]));

        $calls = $this->verteil->apiRequests();
        $this->assertCount(1, $calls);
        $this->assertSame(['Query' => ['OrderID' => [['Owner' => 'KQ', 'value' => 'ORDER1', 'Channel' => 'NDC']]]], json_decode((string) $calls[0]->getBody(), true));
        $this->assertSame('KQ', $calls[0]->getHeaderLine('ThirdpartyId'));
        $this->assertSame('OrderCancel', $calls[0]->getHeaderLine('service'));
    }

    public function test_a_given_third_party_id_wins_over_the_owner(): void
    {
        self::service()->cancelOrder([...OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1']]]), 'third_party_id' => 'QR']);

        $this->assertSame('QR', $this->verteil->apiRequests()[0]->getHeaderLine('ThirdpartyId'));
    }

    public function test_a_cancel_whose_answer_is_lost_is_not_sent_again(): void
    {
        $this->verteil->statuses = [0];

        try {
            self::service()->cancelOrder(OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1']]]));
            $this->fail('Expected the lost connection to surface');
        } catch (\Throwable) {
        }

        $this->assertCount(1, $this->verteil->apiRequests());
    }
}
