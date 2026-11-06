<?php

namespace App\Support;

/**
 * Resolves the real client IP address, honouring forwarding headers only when
 * the immediate peer (REMOTE_ADDR) is a configured trusted proxy.
 *
 * Trusting X-Forwarded-For and friends unconditionally lets any client spoof
 * its address; behind a proxy we must only believe those headers for requests
 * that actually came from the proxy. Configure the allow-list via the
 * TRUSTED_PROXIES env var (comma-separated IPs and/or CIDRs, e.g.
 * "127.0.0.1,10.0.0.0/8,2001:db8::/32"). An empty list means "trust nobody",
 * so only REMOTE_ADDR is used.
 */
final class ClientIp
{
    /**
     * Headers that may carry the original client address, most-specific first.
     */
    private const FORWARDED_HEADERS = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
    ];

    /**
     * Resolve the client IP from server variables, honouring forwarded headers
     * only when REMOTE_ADDR is a trusted proxy.
     *
     * @param array<string,mixed> $server         Typically $_SERVER.
     * @param string[]            $trustedProxies List of IPs / CIDRs.
     */
    public static function resolve(array $server, array $trustedProxies = []): ?string
    {
        $remoteAddr = isset($server['REMOTE_ADDR']) ? trim((string) $server['REMOTE_ADDR']) : '';

        // Only trust forwarding headers when the immediate peer is a known proxy.
        if ($remoteAddr === '' || !self::isTrusted($remoteAddr, $trustedProxies)) {
            return $remoteAddr !== '' ? $remoteAddr : null;
        }

        foreach (self::FORWARDED_HEADERS as $header) {
            if (empty($server[$header])) {
                continue;
            }

            foreach (explode(',', (string) $server[$header]) as $candidate) {
                $candidate = self::stripPort(trim($candidate));
                // The peer is a trusted proxy, so the left-most entry is the
                // original client. Return the first syntactically valid IP.
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        // Headers present but nothing usable -> fall back to the peer.
        return $remoteAddr;
    }

    /**
     * Whether $ip matches any entry in the trusted proxy list (exact IP or CIDR).
     *
     * @param string[] $trustedProxies
     */
    public static function isTrusted(string $ip, array $trustedProxies): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach ($trustedProxies as $proxy) {
            $proxy = trim((string) $proxy);
            if ($proxy === '') {
                continue;
            }

            if (strpos($proxy, '/') === false) {
                if ($ip === $proxy) {
                    return true;
                }
            } elseif (self::inCidr($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $ip falls inside the CIDR block (IPv4 or IPv6), or equals the
     * bare address when no "/bits" suffix is present.
     */
    public static function inCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $bits = isset($parts[1]) ? (int) $parts[1] : null;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        if ($bits === null) {
            return $ipBin === $subnetBin;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder > 0) {
            $mask = (0xFF << (8 - $remainder)) & 0xFF;
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip an optional :port (and IPv6 brackets) from a forwarded value.
     */
    private static function stripPort(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // [2001:db8::1]:443  ->  2001:db8::1
        if ($value[0] === '[') {
            $end = strpos($value, ']');
            if ($end !== false) {
                return substr($value, 1, $end - 1);
            }
        }

        // 203.0.113.7:443  ->  203.0.113.7  (leave bare IPv6 untouched)
        if (substr_count($value, ':') === 1 && strpos($value, '.') !== false) {
            return explode(':', $value, 2)[0];
        }

        return $value;
    }
}
