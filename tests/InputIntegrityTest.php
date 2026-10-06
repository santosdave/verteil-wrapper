<?php

namespace Santosdave\VerteilWrapper\Tests;

use Santosdave\VerteilWrapper\DataTypes\OrderCancel;

/**
 * What a caller sends is what Verteil receives: names and free text are not HTML-escaped,
 * and credentials are used exactly as configured.
 */
class InputIntegrityTest extends TestCase
{
    public function test_an_apostrophe_and_angle_brackets_reach_verteil_unchanged(): void
    {
        self::service()->send('orderCancel', [
            ...OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1']]]),
            'Metadata' => ['Other' => ['OtherMetadata' => [['Remarks' => ["O'Brien <VIP> & family"]]]]],
        ]);

        $body = (string) $this->verteil->apiRequests()[0]->getBody();
        $this->assertStringContainsString("O'Brien <VIP> & family", json_decode($body, true)['Metadata']['Other']['OtherMetadata'][0]['Remarks'][0]);
        $this->assertStringNotContainsString('&apos;', $body);
        $this->assertStringNotContainsString('&amp;', $body);
    }

    public function test_control_characters_are_removed_and_whitespace_normalised(): void
    {
        self::service()->send('orderCancel', [
            ...OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => "ORDER1\0"]]]),
        ]);

        $body = json_decode((string) $this->verteil->apiRequests()[0]->getBody(), true);
        $this->assertSame('ORDER1', $body['Query']['OrderID'][0]['value']);
    }

    public function test_a_password_with_special_characters_is_sent_as_configured(): void
    {
        config(['verteil.password' => "p&ss<w'rd>"]);

        self::service(['password' => "p&ss<w'rd>"])->send('orderCancel', OrderCancel::create(['orders' => [['owner' => 'KQ', 'orderId' => 'ORDER1']]]));

        $token = $this->verteil->tokenRequests()[0];
        $this->assertSame('Basic '.base64_encode("test-user:p&ss<w'rd>"), $token->getHeaderLine('Authorization'));
    }
}
