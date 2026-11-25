<?php
declare(strict_types=1);

/**
 * freedns.my - scanning the sites that live under registered names for abuse keywords.
 *
 * Safety rules baked in, because the input is whatever a stranger published:
 *   - only http/https, only the root path, at most 5 seconds, at most 200 KB;
 *   - private, loopback, link-local and reserved addresses are refused before connecting, so the
 *     scanner can never be pointed at the registry's own network;
 *   - a match is recorded with its evidence and nothing else happens. Banning is a separate,
 *     deliberate click on the admin page.
 */

/** Keyword lists, editable from the back office (stored as JSON under abuse_scan_words). */
function abuse_scan_categories(): array
{
    $default = [
        'adult' => ['porn', 'xxx', 'hentai', 'nsfw', '成人视频', '成人影片', '色情', '裸聊', '约炮'],
        'gamble' => ['casino', 'betting', 'slot online', 'togel', 'sportsbook', '博彩', '赌场', '下注', '六合彩'],
        'drugs' => ['buy cocaine', 'buy heroin', 'methamphetamine for sale', 'k2 spice', '卖冰毒', '出售毒品', '摇头丸'],
        'hate'  => ['white power', 'ethnic cleansing', 'kill all ', '种族清洗', '劣等民族'],
        'abuse' => ['child porn', 'cp video', '幼女', '儿童色情'],
    ];
    $raw = (string)setting('abuse_scan_words', '');
    if (trim($raw) !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && $parsed !== []) {
            $out = [];
            foreach ($parsed as $cat => $words) {
                if (is_array($words)) {
                    $out[(string)$cat] = array_values(array_filter(array_map('strval', $words)));
                }
            }
            if ($out !== []) {
                return $out;
            }
        }
    }

    return $default;
}

function abuse_scan_threshold(): int
{
    return max(1, min(50, (int)setting('abuse_scan_threshold', '2')));
}

/** Is this host safe to fetch? Refuses anything that is not a public address. */
function abuse_scan_host_ok(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
        return false;
    }
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $row) {
                if (!empty($row['ipv6'])) {
                    $ips[] = (string)$row['ipv6'];
                }
            }
        }
    }
    if ($ips === []) {
        return false;
    }
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }

    return true;
}

/** Fetch a small piece of a site. Returns [ok, status, body]. */
function abuse_scan_fetch(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return [false, 0, ''];
    }
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [false, 0, ''];
    }
    if (!abuse_scan_host_ok((string)$parts['host'])) {
        return [false, 0, ''];
    }

    $target = $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '') . '/';
    $ch = curl_init($target);
    if ($ch === false) {
        return [false, 0, ''];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_MAXFILESIZE    => 204800,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'freedns.my abuse scan (+https://freedns.my)',
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($body)) {
        return [false, $status, ''];
    }
    if (strlen($body) > 204800) {
        $body = substr($body, 0, 204800);
    }

    return [true, $status, $body];
}

/** Score one body against the keyword lists. Returns [score, reasons[], excerpt]. */
function abuse_scan_score(string $body): array
{
    $text = strtolower(preg_replace('/\s+/', ' ', strip_tags($body)) ?? $body);
    $score = 0;
    $reasons = [];
    $excerpt = '';

    foreach (abuse_scan_categories() as $cat => $words) {
        $hits = 0;
        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if ($word === '') {
                continue;
            }
            $pos = mb_strpos($text, $word);
            if ($pos !== false) {
                $hits++;
                if ($excerpt === '') {
                    $start = max(0, $pos - 60);
                    $excerpt = mb_substr($text, $start, 180);
                }
            }
        }
        if ($hits > 0) {
            $reasons[] = $cat . ':' . $hits;
            $score += $hits;
        }
    }

    return [$score, $reasons, $excerpt];
}

/**
 * Check one domain and store the result.
 *
 * @return array the row that was written (or a synthetic one when the domain is unknown)
 */
function abuse_scan_domain(int $domainId): array
{
    $d = db_one('SELECT id, name, status FROM domains WHERE id = ? LIMIT 1', [$domainId]);
    if ($d === null) {
        return ['verdict' => 'error', 'score' => 0, 'reasons' => '', 'status' => 0];
    }
    $name = (string)$d['name'];
    $url  = 'https://' . $name . '/';

    [$fetched, $status, $body] = abuse_scan_fetch($url);
    if (!$fetched) {
        $url = 'http://' . $name . '/';
        [$fetched, $status, $body] = abuse_scan_fetch($url);
    }

    $verdict = 'error';
    $score = 0;
    $reasons = '';
    $excerpt = '';
    if ($fetched) {
        [$score, $why, $excerpt] = abuse_scan_score($body);
        $reasons = implode(',', $why);
        $verdict = $score >= abuse_scan_threshold() ? 'suspect' : 'clean';
    }

    db_query(
        'INSERT INTO abuse_scan (domain_id, domain_name, url, status, verdict, score, reasons, sample, handled, checked_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'no\', NOW())
         ON DUPLICATE KEY UPDATE
            domain_name = VALUES(domain_name), url = VALUES(url), status = VALUES(status),
            verdict = VALUES(verdict), score = VALUES(score), reasons = VALUES(reasons),
            sample = VALUES(sample), checked_at = NOW()',
        [$domainId, $name, $url, $status, $verdict, $score, mb_substr($reasons, 0, 500), mb_substr($excerpt, 0, 500)]
    );

    return ['verdict' => $verdict, 'score' => $score, 'reasons' => $reasons, 'status' => $status];
}

/** Scan a batch of active domains that were never checked (or checked before $staleHours). */
function abuse_scan_batch(int $limit = 20, int $staleHours = 24): array
{
    $limit = max(1, min(100, $limit));
    $rows = db_all(
        "SELECT d.id FROM domains d
           LEFT JOIN abuse_scan s ON s.domain_id = d.id
          WHERE d.status = 'active'
            AND (s.id IS NULL OR s.checked_at < DATE_SUB(NOW(), INTERVAL ? HOUR))
          ORDER BY (s.id IS NOT NULL) ASC, d.id ASC
          LIMIT " . $limit,
        [$staleHours]
    );
    $out = ['checked' => 0, 'suspect' => 0, 'clean' => 0, 'error' => 0];
    foreach ($rows as $row) {
        $r = abuse_scan_domain((int)$row['id']);
        $out['checked']++;
        $key = (string)$r['verdict'];
        if (isset($out[$key])) {
            $out[$key]++;
        }
    }

    return $out;
}