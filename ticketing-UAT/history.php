<?php
function getClientIpAddress() {
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    $ipv6Fallback = null;

    $loopbackIp = null;

    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $ips = explode(',', $_SERVER[$key]);
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (!$ip) {
                continue;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                if ($ip === '127.0.0.1') {
                    $loopbackIp = $ip;
                    continue;
                }
                return $ip;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if ($ip === '::1') {
                    $loopbackIp = $ip;
                    continue;
                }
                if ($ipv6Fallback === null) {
                    $ipv6Fallback = $ip;
                }
            }
        }
    }

    if ($ipv6Fallback !== null) {
        return $ipv6Fallback;
    }

    if ($loopbackIp !== null) {
        return $loopbackIp;
    }

    return 'unknown';
}

function getClientMacAddress($ip) {
    if ($ip === 'unknown' || $ip === '::1' || $ip === '127.0.0.1') {
        return 'unknown';
    }

    $mac = 'unknown';

    if (stripos(PHP_OS, 'WIN') === 0) {
        $output = [];
        @exec('arp -a ' . escapeshellarg($ip), $output);
        foreach ($output as $line) {
            if (strpos($line, $ip) !== false) {
                if (preg_match('/([0-9a-fA-F]{2}(?:[-:][0-9a-fA-F]{2}){5})/', $line, $matches)) {
                    $mac = strtoupper(str_replace(':', '-', $matches[1]));
                    break;
                }
            }
        }
    } else {
        $output = [];
        @exec('arp -n ' . escapeshellarg($ip), $output);
        foreach ($output as $line) {
            if (strpos($line, $ip) !== false) {
                if (preg_match('/([0-9a-fA-F]{2}(?::[0-9a-fA-F]{2}){5})/', $line, $matches)) {
                    $mac = strtoupper($matches[1]);
                    break;
                }
            }
        }
    }

    return $mac;
}

function recordLogHistory($conn, $username) {
    if (!$conn || !$username) {
        return false;
    }

    $ip = getClientIpAddress();
    $mac = getClientMacAddress($ip);
    $date = date('Y-m-d');
    $time = date('H:i:s');

    $stmt = $conn->prepare("INSERT INTO loghis (`username`, `date`, `time`, `ipaddress`, `macadd`) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssss', $username, $date, $time, $ip, $mac);
    $stmt->execute();
    $stmt->close();

    return true;
}
