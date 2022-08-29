<?php


namespace AbmmHasan\WebFace\Request\Asset;


use AbmmHasan\Bucket\Functional\Arrject;
use AbmmHasan\WebFace\Common\StaticSingleInstance;
use AbmmHasan\WebFace\Common\Value;
use RuntimeException;

final class EndUser
{
    private array $checkedIps = [];
    private string $clientIp;
    private Arrject $info;

    use Value, StaticSingleInstance;

    /**
     * Get user info
     *
     * @param string|null $key
     * @return mixed
     */
    public function info(string $key = null): mixed
    {
        if (!isset($this->info)) {
            $server = CommonAsset::instance()->server();
            $this->info = new Arrject([
                'ip' => $server->server('REMOTE_ADDR'),
                'proxy_ip' => $this->ip(),
                'referer' => $server->server('HTTP_REFERER'),
                'ua' => [
                    'agent' => $server->server('HTTP_USER_AGENT'),
                    'system' => $this->userAgentInfo(),
                ]
            ]);
        }

        return $this->find($this->info, $key);
    }

    /**
     * Get Client IP
     *
     * @return string|null
     */
    public function ip(): ?string
    {
        if (isset($this->clientIp)) {
            return $this->clientIp;
        }

        if (php_sapi_name() == 'cli') {
            $ip = gethostbyname(gethostname());
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = '127.0.0.1';
            }
            return $this->clientIp = $ip;
        }
        $server = CommonAsset::instance()->server();
        foreach (
            [
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR'
            ] as $key
        ) {
            $ipAsset = $server[$key] ?? null;
            if ($ipAsset !== null) {
                foreach (explode(',', $ipAsset) as $ip) {
                    $ip = trim($ip);
                    if (filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        ) !== false) {
                        return $this->clientIp = $ip;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Checks if Client IP is contained in the list of given IPs or subnets.
     *
     * @param array|string $ips List of IPs or subnets (can be a string if only a single one)
     * @return bool Whether the ClientIP is valid
     */
    public function checkIP(array|string $ips, $checkIP = null): bool
    {
        $ips = (array)$ips;
        $this->ip();

        $check = $checkIP ?? $this->clientIp;

        $method = substr_count($check, ':') > 1 ? 'checkIp6' : 'checkIp4';

        foreach ($ips as $ip) {
            if ($this->$method($check, $ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anonymizes an ClientIP/IPv6.
     *
     * Removes the last byte for v4 and the last 8 bytes for v6 IPs
     * @param string $ip
     * @return string
     */
    public function anonymize(string $ip): string
    {
        $wrappedIPv6 = false;
        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $wrappedIPv6 = true;
            $ip = substr($ip, 1, -1);
        }

        $packedAddress = inet_pton($ip);
        if (4 === strlen($packedAddress)) {
            $mask = '255.255.255.0';
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff:ffff'))) {
            $mask = '::ffff:ffff:ff00';
        } elseif ($ip === inet_ntop($packedAddress & inet_pton('::ffff:ffff'))) {
            $mask = '::ffff:ff00';
        } else {
            $mask = 'ffff:ffff:ffff:ffff:0000:0000:0000:0000';
        }
        $ip = inet_ntop($packedAddress & inet_pton($mask));

        if ($wrappedIPv6) {
            $ip = '[' . $ip . ']';
        }

        return $ip;
    }

    /**
     * Compares two IPv4 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @param string $ip IPv4 address or subnet in CIDR notation
     *
     * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
     */
    public function checkIp4(string $check, string $ip): bool
    {
        $cacheKey = $check . '-' . $ip;
        if (isset($this->checkedIps[$cacheKey])) {
            return $this->checkedIps[$cacheKey];
        }

        if (!filter_var($check, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->checkedIps[$cacheKey] = false;
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if ('0' === $netmask) {
                return $this->checkedIps[$cacheKey] = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            }

            if ($netmask < 0 || $netmask > 32) {
                return $this->checkedIps[$cacheKey] = false;
            }
        } else {
            $address = $ip;
            $netmask = 32;
        }

        if (false === ip2long($address)) {
            return $this->checkedIps[$cacheKey] = false;
        }

        return $this->checkedIps[$cacheKey] = 0 === substr_compare(
                sprintf('%032b', ip2long($check)),
                sprintf('%032b', ip2long($address)),
                0, $netmask
            );
    }

    /**
     * Compares two IPv6 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @param string $ip IPv6 address or subnet in CIDR notation
     *
     * @throws RuntimeException When IPV6 support is not enabled
     * @author David Soria Parra <dsp at php dot net>
     *
     * @see https://github.com/dsp/v6tools
     *
     */
    public function checkIp6(string $check, string $ip): bool
    {
        $cacheKey = $check . '-' . $ip;
        if (isset($this->checkedIps[$cacheKey])) {
            return $this->checkedIps[$cacheKey];
        }

        if (!((extension_loaded('sockets') && defined('AF_INET6')) || @inet_pton('::1'))) {
            throw new RuntimeException('Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".');
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if ('0' === $netmask) {
                return (bool)unpack('n*', @inet_pton($address));
            }

            if ($netmask < 1 || $netmask > 128) {
                return $this->checkedIps[$cacheKey] = false;
            }
        } else {
            $address = $ip;
            $netmask = 128;
        }

        $bytesAddr = unpack('n*', @inet_pton($address));
        $bytesTest = unpack('n*', @inet_pton($check));

        if (!$bytesAddr || !$bytesTest) {
            return $this->checkedIps[$cacheKey] = false;
        }

        for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; ++$i) {
            $left = $netmask - 16 * ($i - 1);
            $left = ($left <= 16) ? $left : 16;
            $mask = ~(0xFFFF >> $left) & 0xFFFF;
            if (($bytesAddr[$i] & $mask) != ($bytesTest[$i] & $mask)) {
                return $this->checkedIps[$cacheKey] = false;
            }
        }

        return $this->checkedIps[$cacheKey] = true;
    }

    private function userAgentInfo(): object|bool|array
    {
        if (ini_get('browscap')) {
            return @get_browser(null, true) ?? [];
        }
        return [];
    }
}
