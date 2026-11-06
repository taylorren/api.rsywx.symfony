<?php

namespace Tests\Unit;

use App\Support\ClientIp;
use PHPUnit\Framework\TestCase;

/**
 * Forwarding headers must only be trusted when the request actually came from a
 * configured proxy (REMOTE_ADDR in TRUSTED_PROXIES).
 */
final class ClientIpTest extends TestCase
{
    public function testUntrustedPeerIgnoresForwardedHeaders(): void
    {
        $server = [
            'REMOTE_ADDR' => '8.8.4.4',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'HTTP_X_REAL_IP' => '203.0.113.10',
        ];

        $this->assertSame('8.8.4.4', ClientIp::resolve($server, []));
    }

    public function testTrustedPeerHonoursXForwardedForLeftmostEntry(): void
    {
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 10.0.0.1',
        ];

        $this->assertSame('8.8.8.8', ClientIp::resolve($server, ['127.0.0.1']));
    }

    public function testTrustedPeerKeepsLeftmostEvenIfPrivate(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.5, 10.0.0.9',
        ];

        $this->assertSame('192.168.1.5', ClientIp::resolve($server, ['10.0.0.0/8']));
    }

    public function testTrustedPeerFallsBackToXRealIpWhenNoXff(): void
    {
        $server = [
            'REMOTE_ADDR' => '172.16.0.1',
            'HTTP_X_REAL_IP' => '1.1.1.1',
        ];

        $this->assertSame('1.1.1.1', ClientIp::resolve($server, ['172.16.0.0/12']));
    }

    public function testTrustedPeerUsesCloudflareHeader(): void
    {
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '9.9.9.9',
        ];

        $this->assertSame('9.9.9.9', ClientIp::resolve($server, ['127.0.0.1']));
    }

    public function testForwardedValueWithPortIsStripped(): void
    {
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7:443, 10.0.0.1',
        ];

        $this->assertSame('203.0.113.7', ClientIp::resolve($server, ['127.0.0.1']));
    }

    public function testTrustedPeerWithNoUsableHeaderFallsBackToRemoteAddr(): void
    {
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, garbage',
        ];

        $this->assertSame('127.0.0.1', ClientIp::resolve($server, ['127.0.0.1']));
    }

    public function testMissingRemoteAddrReturnsNull(): void
    {
        $this->assertNull(ClientIp::resolve([], []));
    }

    public function testUntrustedPeerWithoutRemoteAddrIsNull(): void
    {
        // Forwarded header present, but no peer to validate against -> no trust.
        $this->assertNull(ClientIp::resolve(['HTTP_X_FORWARDED_FOR' => '8.8.8.8'], ['127.0.0.1']));
    }

    /**
     * @dataProvider trustedProvider
     *
     * @param string[] $list
     */
    public function testIsTrusted(string $ip, array $list, bool $expected): void
    {
        $this->assertSame($expected, ClientIp::isTrusted($ip, $list));
    }

    /**
     * @return array<string, array{0: string, 1: string[], 2: bool}>
     */
    public function trustedProvider(): array
    {
        return [
            'exact ipv4'         => ['127.0.0.1', ['127.0.0.1'], true],
            'cidr ipv4 match'    => ['10.1.2.3', ['10.0.0.0/8'], true],
            'cidr ipv4 no match' => ['11.1.2.3', ['10.0.0.0/8'], false],
            'cidr /32'           => ['10.1.2.3', ['10.1.2.3/32'], true],
            'ipv6 exact'         => ['::1', ['::1'], true],
            'ipv6 cidr match'    => ['2001:db8::1', ['2001:db8::/32'], true],
            'ipv6 cidr no match' => ['2001:dead::1', ['2001:db8::/32'], false],
            'empty list'         => ['127.0.0.1', [], false],
            'invalid ip'         => ['not-an-ip', ['0.0.0.0/0'], false],
            'wrong family'       => ['10.0.0.1', ['::/0'], false],
            'multiple entries'   => ['192.168.5.5', ['10.0.0.0/8', '192.168.0.0/16'], true],
        ];
    }
}
