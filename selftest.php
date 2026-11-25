<?php
declare(strict_types=1);

/**
 * freedns.my - deployment self test for registry operators.
 *
 * Open /selftest.php while signed in to the back office. It checks the uploaded files, the
 * language catalogues and the database structure, and prints the SQL needed to fix what is
 * missing. It reads nothing but the structure, so no applicant data is exposed.
 */

require __DIR__ . '/inc/bootstrap.php';

fd_start_session();

if (!is_admin()) {
    // No redirect: the back office is reached through its secret entry, which is not shown here.
    http_response_code(403);
    page_header('Self test');
    echo '<p>This page is for the registry operators. Sign in to the back office first, then open '
        . 'this address again.</p>';
    page_footer();
    exit;
}

// The self test prints configuration and SQL, so it is for owner accounts only.
if (!is_owner()) {
    http_response_code(403);
    page_header('Self test');
    echo '<div class="msg error"><strong>Not allowed</strong></div>';
    echo '<p>The self test is reserved for the registry owner: it prints configuration details. '
        . 'Your reviewer account can decide requests under <a href="' . h(url('admin/index.php')) . '">'
        . 'Administration</a>.</p>';
    page_footer();
    exit;
}

$results = [];
$note    = static function (string $label, bool $pass, string $hint = '') use (&$results): void {
    $results[] = ['label' => $label, 'pass' => $pass, 'hint' => $hint];
};

/* ---------------- environment ---------------- */

$note('PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>='), 'PHP 7.4 or newer is required.');
$note('pdo_mysql extension', extension_loaded('pdo_mysql'), 'Enable pdo_mysql in the PHP settings.');

/* ---------------- uploaded files ---------------- */

$requiredFiles = [
    'index.php', 'request.php', 'status.php', 'manage.php', 'domains.php', 'doc.php',
    'inc/bootstrap.php', 'inc/functions.php', 'inc/views.php', 'inc/auth.php', 'inc/db.php', 'inc/mail.php',
    'inc/users.php', 'inc/admin_gate.php', 'inc/github.php', 'inc/flood.php', 'inc/whois.php',
    'inc/cloudflare.php', 'inc/dnshe.php', 'inc/publish.php',
    'inc/lang/en.php', 'inc/lang/zh.php',
    'zh/index.php', 'zh/request.php', 'zh/status.php', 'zh/manage.php', 'zh/domains.php', 'zh/doc.php',
    'admin/index.php', 'admin/zones.php', 'admin/reserved.php', 'admin/changes.php', 'admin/change.php',
    'admin/edit.php', 'admin/mail.php', 'admin/dns.php', 'admin/dns-cron.php', 'admin/users.php',
    'account/index.php', 'account/login.php', 'account/register.php', 'account/verify.php',
    'account/forgot.php', 'account/reset.php', 'account/manage.php', 'account/profile.php',
    'zh/account/index.php', 'zh/account/login.php', 'zh/account/register.php', 'zh/account/manage.php',
    'zh/account/verify.php', 'zh/account/forgot.php', 'zh/account/reset.php', 'zh/account/profile.php',
    'zh/account/export.php', 'zh/account/logout.php', 'zh/account/github.php', 'zh/account/bonus.php',
    'zh/account/transfers.php', 'account/transfers.php', 'admin/tickets.php',
    'market.php', 'zh/market.php', 'account/market.php', 'zh/account/market.php', 'admin/market.php',
    'account/nameservers.php', 'zh/account/nameservers.php', 'admin/nameservers.php',
    'chat.php', 'zh/chat.php', 'admin/chat.php',
    'changelog.php', 'zh/changelog.php',
    'ddns.php', 'account/ddns.php', 'zh/account/ddns.php',
    'api.php', 'account/api.php', 'zh/account/api.php',
    'health.php', 'admin/health.php',
    'abuse.php', 'zh/abuse.php', 'admin/abuse.php', 'legal.php', 'zh/legal.php',
    'totp.php', 'zh/totp.php', 'account/security.php', 'zh/account/security.php',
    'UPGRADING.md', 'upgrade-recordtypes.sql',
    'account/close.php', 'zh/account/close.php',
    'admin/bulk.php',
    'admin/verify.php',
    'alert-cron.php', 'upgrade-alerts.sql',
    'account/shared.php', 'zh/account/shared.php', 'upgrade-sharing.sql',
    'whois.php', 'zh/whois.php',
];
$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!is_file(FD_ROOT . '/' . $file)) {
        $missingFiles[] = $file;
    }
}
$note(
    'uploaded files (' . (count($requiredFiles) - count($missingFiles)) . '/' . count($requiredFiles) . ')',
    $missingFiles === [],
    'Missing: ' . implode(', ', $missingFiles)
);

/* ---------------- language catalogues ---------------- */

$en = lang_catalogue_for('en');
$zh = lang_catalogue_for('zh');
$note('inc/lang/en.php loads', $en !== [], 'Upload inc/lang/en.php from the package.');
$note('inc/lang/zh.php loads', $zh !== [], 'Upload inc/lang/zh.php from the package.');

$missingZh = array_values(array_diff(array_keys($en), array_keys($zh)));
$note(
    'Chinese catalogue complete (' . count($zh) . ' keys)',
    $en !== [] && $missingZh === [],
    'Missing keys: ' . implode(', ', array_slice($missingZh, 0, 12))
);
$note("t('nav.home') resolves", t('nav.home') !== 'nav.home', 'The language catalogue did not load.');

$previous = lang_switch('zh');
$zhHome   = t('nav.home');
lang_switch($previous);
$note('Chinese lookup works', $zhHome !== 'nav.home', "t('nav.home') in Chinese returned the key itself.");

/* ---------------- database structure ---------------- */

$tables  = [];
$columns = [];
try {
    foreach (db_all('SHOW TABLES') as $row) {
        $tables[] = (string)array_values($row)[0];
    }
} catch (Throwable $e) {
    $note('database reachable', false, 'Check inc/config.php and that MySQL is running.');
}

if ($tables !== []) {
    $note('database reachable', true);
}

foreach (['zones', 'domains', 'records', 'change_requests', 'reserved_names', 'users', 'dns_records',
    'rate_limits', 'whois_cache', 'whois_queries', 'admins', 'settings', 'audit_log',
    'login_attempts'] as $table) {
    $exists = in_array($table, $tables, true);
    $note('table ' . $table, $exists, 'Run install.php (fresh install) or the matching upgrade file.');
    if ($exists) {
        try {
            foreach (db_all('SHOW COLUMNS FROM ' . $table) as $row) {
                $columns[$table][] = (string)$row['Field'];
            }
        } catch (Throwable $e) {
            $columns[$table] = [];
        }
    }
}

$requiredColumns = [
    'admins'          => ['username', 'password_hash', 'email', 'is_owner', 'zones', 'gate_slug', 'gate_pass',
                          'created_at'],
    'zones'           => ['name', 'title', 'title_zh', 'description', 'description_zh', 'cf_zone_id',
                          'cf_token', 'provider', 'dnshe_key', 'dnshe_secret', 'is_open', 'allow_subdomains',
                          'allow_ns'],
    'domains'         => ['name', 'label', 'zone', 'language', 'reserved', 'proxied', 'delegated',
                          'nameservers', 'glue', 'status', 'request_code', 'provider_ref', 'provider_owned',
                          'registrant_email', 'ddns_token', 'ddns_updated_at'],
    'records'         => ['domain_id', 'sub', 'type', 'value', 'ttl', 'position', 'proxied'],
    'change_requests' => ['domain_id', 'status', 'snapshot', 'pending_domain', 'submitted_at'],
    'reserved_names'  => ['zone', 'label', 'note', 'created_by', 'created_at'],
    'users'           => ['username', 'username_lower', 'email', 'email_lower', 'password_hash',
                          'has_password', 'github_id', 'github_login', 'github_linked_at',
                          'quota_unlimited', 'quota_limit', 'quota_bonus', 'star_bonus_at',
                          'star_bonus_repo', 'status',
                          'verify_token', 'reset_token', 'created_at'],
    'dns_records'     => ['domain_id', 'provider', 'remote_id', 'fqdn', 'sub', 'type', 'value', 'proxied'],
    'rate_limits'     => ['ip', 'hits', 'window_started', 'blocked_until', 'strikes', 'note', 'last_seen'],
    'whois_cache'     => ['domain', 'source', 'payload', 'fetched_at'],
    'whois_queries'   => ['ip', 'domain', 'created_at'],
    'transfer_requests' => ['domain_id', 'requester_email', 'recipient_email', 'status', 'transfer_code',
                            'expires_at', 'created_at'],
    'transfer_messages' => ['request_id', 'author', 'body', 'created_at'],
    'market_listings' => ['user_id', 'seller_email', 'contact_email', 'domain', 'registered_on', 'expires_on',
                          'registrar', 'transfer_price', 'price', 'currency', 'negotiable', 'status', 'pinned'],
    'market_enquiries' => ['listing_id', 'buyer_email', 'message', 'created_at'],
    'shop_promos'     => ['user_id', 'user_email', 'url', 'status', 'bonus', 'decided_by', 'created_at'],
    'chat_threads'    => ['user_id', 'email', 'display_name', 'status', 'unread_staff', 'unread_user',
                          'last_message_at', 'created_at'],
    'chat_messages'   => ['thread_id', 'author', 'body', 'created_at'],
    'api_tokens'      => ['user_id', 'token_hash', 'token_hint', 'calls', 'last_used_at', 'created_at',
                          'revoked_at'],
    'mail_routes'     => ['domain_id', 'user_id', 'local_part', 'forward_to', 'cf_rule_id', 'status',
                          'created_at'],
    'abuse_reports'   => ['domain', 'reporter', 'kind', 'message', 'status', 'note', 'created_at'],
    'domain_checks'   => ['domain_id', 'ok', 'stored_count', 'live_count', 'missing', 'extra', 'error',
                          'checked_at', 'alert_hash'],
    'domain_access'   => ['domain_id', 'user_id', 'invited_by', 'created_at'],
];

/** Columns the newer releases added to existing tables (used by the repair button as well). */
$extraColumns = [
    'users'  => ['totp_secret', 'totp_at'],
    'admins' => ['totp_secret', 'totp_at'],
];

$columnFixes = [
    'zones.title_zh'                  => 'ALTER TABLE zones ADD COLUMN title_zh VARCHAR(190) DEFAULT NULL AFTER title;',
    'zones.description_zh'            => 'ALTER TABLE zones ADD COLUMN description_zh TEXT AFTER description;',
    'domains.language'                => "ALTER TABLE domains ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'en' AFTER zone;",
    'domains.reserved'                => 'ALTER TABLE domains ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER language;',
    'domains.proxied'                 => 'ALTER TABLE domains ADD COLUMN proxied TINYINT(1) NOT NULL DEFAULT 0 AFTER reserved;',
    'zones.cf_zone_id'                => 'ALTER TABLE zones ADD COLUMN cf_zone_id VARCHAR(64) DEFAULT NULL AFTER description_zh;',
    'zones.cf_token'                  => 'ALTER TABLE zones ADD COLUMN cf_token VARCHAR(190) DEFAULT NULL AFTER cf_zone_id;',
    'admins.zones'                    => 'ALTER TABLE admins ADD COLUMN zones VARCHAR(255) DEFAULT NULL AFTER is_owner;',
    'admins.gate_slug'                => 'ALTER TABLE admins ADD COLUMN gate_slug VARCHAR(64) DEFAULT NULL AFTER zones;',
    'admins.gate_pass'                => 'ALTER TABLE admins ADD COLUMN gate_pass VARCHAR(255) DEFAULT NULL AFTER gate_slug;',
    'users.quota_unlimited'           => 'ALTER TABLE users ADD COLUMN quota_unlimited TINYINT(1) NOT NULL DEFAULT 0 AFTER github_linked_at;',
    'users.quota_limit'               => 'ALTER TABLE users ADD COLUMN quota_limit SMALLINT UNSIGNED DEFAULT NULL AFTER quota_unlimited;',
    'users.quota_bonus'               => 'ALTER TABLE users ADD COLUMN quota_bonus SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER quota_limit;',
    'users.star_bonus_at'             => 'ALTER TABLE users ADD COLUMN star_bonus_at DATETIME DEFAULT NULL AFTER quota_bonus;',
    'users.star_bonus_repo'           => 'ALTER TABLE users ADD COLUMN star_bonus_repo VARCHAR(190) DEFAULT NULL AFTER star_bonus_at;',
    'zones.provider'                  => 'ALTER TABLE zones ADD COLUMN provider VARCHAR(16) DEFAULT NULL AFTER cf_token;',
    'zones.dnshe_key'                 => 'ALTER TABLE zones ADD COLUMN dnshe_key VARCHAR(190) DEFAULT NULL AFTER provider;',
    'zones.dnshe_secret'              => 'ALTER TABLE zones ADD COLUMN dnshe_secret VARCHAR(190) DEFAULT NULL AFTER dnshe_key;',
    'domains.provider_ref'            => 'ALTER TABLE domains ADD COLUMN provider_ref VARCHAR(64) DEFAULT NULL AFTER request_code;',
    'domains.provider_owned'          => 'ALTER TABLE domains ADD COLUMN provider_owned TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_ref;',
    'zones.allow_ns'                  => 'ALTER TABLE zones ADD COLUMN allow_ns TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_subdomains;',
    'domains.delegated'               => 'ALTER TABLE domains ADD COLUMN delegated TINYINT(1) NOT NULL DEFAULT 0 AFTER proxied;',
    'domains.nameservers'             => 'ALTER TABLE domains ADD COLUMN nameservers VARCHAR(512) DEFAULT NULL AFTER delegated;',
    'domains.glue'                    => 'ALTER TABLE domains ADD COLUMN glue VARCHAR(512) DEFAULT NULL AFTER nameservers;',
    'users.invite_code'               => 'ALTER TABLE users ADD COLUMN invite_code VARCHAR(32) DEFAULT NULL AFTER quota_bonus;',
    'users.referred_by'               => 'ALTER TABLE users ADD COLUMN referred_by VARCHAR(32) DEFAULT NULL AFTER invite_code;',
    'domains.ref_code'                => 'ALTER TABLE domains ADD COLUMN ref_code VARCHAR(32) DEFAULT NULL AFTER provider_owned;',
    'domains.ddns_token'              => 'ALTER TABLE domains ADD COLUMN ddns_token VARCHAR(64) DEFAULT NULL AFTER ref_code;',
    'domains.ddns_updated_at'         => 'ALTER TABLE domains ADD COLUMN ddns_updated_at DATETIME DEFAULT NULL AFTER ddns_token;',
    'zones.cf_account_id'             => 'ALTER TABLE zones ADD COLUMN cf_account_id VARCHAR(64) DEFAULT NULL AFTER cf_zone_id;',
    'users.totp_secret'               => 'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER github_linked_at;',
    'users.totp_at'                   => 'ALTER TABLE users ADD COLUMN totp_at DATETIME DEFAULT NULL AFTER totp_secret;',
    'admins.totp_secret'              => 'ALTER TABLE admins ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER gate_pass;',
    'admins.totp_at'                  => 'ALTER TABLE admins ADD COLUMN totp_at DATETIME DEFAULT NULL AFTER totp_secret;',
    'records.type'                    => "ALTER TABLE records MODIFY COLUMN type ENUM('A','AAAA','CNAME','TXT','MX','SRV','CAA','PTR','NS') NOT NULL;",
    'records.proxied'                 => 'ALTER TABLE records ADD COLUMN proxied TINYINT(1) NOT NULL DEFAULT 0 AFTER ttl;',
    'domains.zone'                    => "ALTER TABLE domains ADD COLUMN zone VARCHAR(190) NOT NULL DEFAULT '' AFTER label;",
    'change_requests.pending_domain'  => 'ALTER TABLE change_requests ADD COLUMN pending_domain INT UNSIGNED '
        . "GENERATED ALWAYS AS (IF(status = 'pending', domain_id, NULL)) STORED, "
        . 'ADD UNIQUE KEY uniq_change_pending (pending_domain);',
];

$missingColumns = [];
foreach ($requiredColumns as $table => $fields) {
    if (!isset($columns[$table])) {
        continue;
    }
    foreach ($fields as $field) {
        if (!in_array($field, $columns[$table], true)) {
            $missingColumns[] = $table . '.' . $field;
        }
    }
}

// The "type" column of the records table is an ENUM: when it does not know the newer record types yet,
// storing an MX, SRV, CAA or PTR record fails, so it is offered for repair like a missing column.
$recordTypeCell = (string)db_value(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'type'",
    [],
    ''
);
if ($recordTypeCell !== '' && stripos($recordTypeCell, "'MX'") === false) {
    $missingColumns[] = 'records.type';
}

$note(
    'database columns',
    $missingColumns === [],
    'Missing: ' . implode(', ', $missingColumns) . ' — run the SQL below.'
);

/* Tables that only arrive with an upgrade file, with the SQL that creates them. */
$tableFixes = [
    'reserved_names' => "CREATE TABLE IF NOT EXISTS reserved_names (\n"
        . "  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  zone       VARCHAR(190) NOT NULL,\n"
        . "  label      VARCHAR(63)  NOT NULL,\n"
        . "  note       VARCHAR(190) DEFAULT NULL,\n"
        . "  created_by VARCHAR(32)  DEFAULT NULL,\n"
        . "  created_at DATETIME     NOT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY uniq_reserved_name (zone, label),\n"
        . "  KEY idx_reserved_zone (zone)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'users' => "CREATE TABLE IF NOT EXISTS users (\n"
        . "  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  username       VARCHAR(32)  NOT NULL,\n"
        . "  username_lower VARCHAR(32)  NOT NULL,\n"
        . "  email          VARCHAR(190) NOT NULL,\n"
        . "  email_lower    VARCHAR(190) NOT NULL,\n"
        . "  password_hash  VARCHAR(255) NOT NULL,\n"
        . "  status         ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',\n"
        . "  language       VARCHAR(5)   NOT NULL DEFAULT 'en',\n"
        . "  verify_token   VARCHAR(64)  DEFAULT NULL,\n"
        . "  verify_expires DATETIME     DEFAULT NULL,\n"
        . "  verify_sent_at DATETIME     DEFAULT NULL,\n"
        . "  reset_token    VARCHAR(64)  DEFAULT NULL,\n"
        . "  reset_expires  DATETIME     DEFAULT NULL,\n"
        . "  created_at     DATETIME     NOT NULL,\n"
        . "  created_ip     VARCHAR(45)  DEFAULT NULL,\n"
        . "  last_login_at  DATETIME     DEFAULT NULL,\n"
        . "  last_login_ip  VARCHAR(45)  DEFAULT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY uniq_user_username (username_lower),\n"
        . "  UNIQUE KEY uniq_user_email (email_lower),\n"
        . "  KEY idx_user_status (status),\n"
        . "  KEY idx_user_verify (verify_token),\n"
        . "  KEY idx_user_reset (reset_token)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'dns_records' => "CREATE TABLE IF NOT EXISTS dns_records (\n"
        . "  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  domain_id  INT UNSIGNED NOT NULL,\n"
        . "  provider   VARCHAR(16)  NOT NULL DEFAULT 'cloudflare',\n"
        . "  remote_id  VARCHAR(64)  NOT NULL,\n"
        . "  fqdn       VARCHAR(255) NOT NULL,\n"
        . "  sub        VARCHAR(63)  NOT NULL DEFAULT '',\n"
        . "  type       VARCHAR(8)   NOT NULL,\n"
        . "  value      VARCHAR(255) NOT NULL,\n"
        . "  proxied    TINYINT(1)   NOT NULL DEFAULT 0,\n"
        . "  created_at DATETIME     NOT NULL,\n"
        . "  updated_at DATETIME     NOT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY uniq_dns_remote (provider, remote_id),\n"
        . "  KEY idx_dns_domain (domain_id),\n"
        . "  CONSTRAINT fk_dns_domain FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'rate_limits' => "CREATE TABLE IF NOT EXISTS rate_limits (\n"
        . "  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  ip             VARCHAR(45) NOT NULL,\n"
        . "  hits           INT UNSIGNED NOT NULL DEFAULT 0,\n"
        . "  window_started DATETIME    NOT NULL,\n"
        . "  blocked_until  DATETIME    DEFAULT NULL,\n"
        . "  strikes        SMALLINT UNSIGNED NOT NULL DEFAULT 0,\n"
        . "  note           VARCHAR(190) DEFAULT NULL,\n"
        . "  last_seen      DATETIME    NOT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY uniq_rate_ip (ip),\n"
        . "  KEY idx_rate_blocked (blocked_until),\n"
        . "  KEY idx_rate_seen (last_seen)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'whois_cache' => "CREATE TABLE IF NOT EXISTS whois_cache (\n"
        . "  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  domain     VARCHAR(190) NOT NULL,\n"
        . "  source     VARCHAR(16)  NOT NULL,\n"
        . "  payload    MEDIUMTEXT   NOT NULL,\n"
        . "  fetched_at DATETIME     NOT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY uniq_whois_domain (domain),\n"
        . "  KEY idx_whois_fetched (fetched_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'whois_queries' => "CREATE TABLE IF NOT EXISTS whois_queries (\n"
        . "  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  ip         VARCHAR(45)  NOT NULL,\n"
        . "  domain     VARCHAR(190) NOT NULL,\n"
        . "  created_at DATETIME     NOT NULL,\n"
        . "  PRIMARY KEY (id),\n"
        . "  KEY idx_whois_ip_time (ip, created_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$missingTables = [];
foreach ($tableFixes as $table => $sql) {
    if ($tables !== [] && !in_array($table, $tables, true)) {
        $missingTables[] = $table;
    }
}

/* ---------------- mail ---------------- */

$transport = mail_transport();
$smtp      = mail_settings();

if ($transport === 'smtp') {
    $note('mail transport: SMTP ' . $smtp['host'] . ':' . $smtp['port'] . ' (' . $smtp['secure'] . ')', true);
    $note(
        'SMTP mailbox configured',
        $smtp['user'] !== '',
        'Enter the mailbox under Administration -> Mail (some servers accept anonymous delivery).'
    );
    $note(
        'SMTP password stored',
        $smtp['pass'] !== '',
        'Enter the password / authorisation code under Administration -> Mail.'
    );

    $errno  = 0;
    $errstr = '';
    $probe  = @fsockopen(
        ($smtp['secure'] === 'ssl' ? 'ssl://' : '') . $smtp['host'],
        $smtp['port'],
        $errno,
        $errstr,
        5
    );
    if (is_resource($probe)) {
        @fclose($probe);
        $note('SMTP port reachable', true);
    } else {
        $note(
            'SMTP port reachable',
            false,
            'Could not open ' . $smtp['host'] . ':' . $smtp['port'] . ' — ' . $errstr
            . '. Check the host, the port and the outgoing firewall.'
        );
    }
} else {
    $note(
        'mail transport: PHP mail()',
        true,
        'No SMTP host is configured; enter one under Administration -> Mail if mail() does not deliver.'
    );
}

$note(
    'sender address usable',
    valid_email_addr(mail_from_address()),
    'Set a sender address under Administration -> Mail.'
);

/* ---------------- hidden back-office entry ---------------- */

if (admin_gate_enabled()) {
    $note(
        'back-office entry: hidden',
        admin_gate_slug() !== '' && admin_gate_has_pass(),
        'Set the secret address and the pass phrase under Administration -> Settings (Admin entry).'
    );
    $backOfficeEntry = admin_gate_url() === '' ? '(not set)' : admin_gate_url();
} else {
    $note(
        'back-office entry: hidden',
        false,
        'The hidden entry is switched off, so /admin/ answers normally. Switch it on under '
        . 'Administration -> Settings (Admin entry).'
    );
    $backOfficeEntry = 'hidden entry is switched off';
}

/* ---------------- automatic Cloudflare publishing ---------------- */

if (cf_enabled()) {
    $bound = 0;
    foreach (zone_list() as $zone) {
        if (trim((string)($zone['cf_zone_id'] ?? '')) !== '') {
            $bound++;
        }
    }
    $note(
        'Cloudflare publishing: zones bound',
        $bound > 0,
        'Set the Cloudflare zone id per suffix under Administration -> DNS.'
    );
    $note(
        'Cloudflare publishing: dns_records',
        publish_mapping_ready(),
        'Import upgrade-cloudflare.sql so pushed records can be removed again later.'
    );
} else {
    $note(
        'Cloudflare publishing',
        true,
        'Switched off, so approved names are published by hand through the export. Switch it on under '
        . 'Administration -> DNS.'
    );
}

/* ---------------- anti-flood protection ---------------- */

if (setting_bool('flood_enabled', false)) {
    $note(
        'rate limit: visitor address trusted',
        flood_trust_ok(),
        'The protection is on but "Trust reverse-proxy headers" is off - switch it on under Rate limits, '
        . 'or every visitor shares one address.'
    );
    $note(
        'rate limit: counters table',
        flood_table_ready(),
        'Import upgrade-flood.sql so the counters can be stored.'
    );
} else {
    $note(
        'rate limit',
        true,
        'Switched off; the site answers every request. Switch it on under Rate limits.'
    );
}

/* ---------------- GitHub sign-in ---------------- */

if (setting_bool('github_enabled', false)) {
    $note(
        'GitHub sign-in: columns',
        github_columns_ready(),
        'Import upgrade-github.sql so the users table can store the link.'
    );
    $note(
        'GitHub sign-in: client id and secret',
        github_client_id() !== '' && github_client_secret() !== '',
        'Store both values under Settings, otherwise the button leads nowhere.'
    );
    $note(
        'GitHub sign-in: writable session directory',
        session_status() !== PHP_SESSION_DISABLED,
        'The OAuth flow keeps its security token in the session.'
    );
} else {
    $note('GitHub sign-in', true, 'Switched off; the console only uses e-mail and password.');
}

/* ---------------- Manage Records page ---------------- */

$note(
    'Manage Records',
    true,
    setting_bool('manage_closed', false)
        ? 'Closed for visitors who are not signed in; a notice of ' . (trim(setting_lang('manage_notice')) === ''
            ? 'no text yet (fill in "Important notice" under Settings)' : 'your own text') . ' is shown there.'
        : 'Open to everyone with request code and e-mail address.'
);

/* ---------------- reviewer accounts ---------------- */

$reviewers = [];
try {
    foreach (db_all('SELECT username, zones FROM admins WHERE is_owner = 0 ORDER BY username ASC') as $row) {
        $reviewers[] = (string)$row['username'] . ' ('
            . (trim((string)($row['zones'] ?? '')) === '' ? 'no suffix assigned' : (string)$row['zones']) . ')';
    }
} catch (Throwable $e) {
    $reviewers = [];
}
$note(
    'reviewer accounts',
    true,
    $reviewers === []
        ? 'None yet: every administrator is an owner. Add a reviewer under Administrators.'
        : implode(', ', $reviewers)
);

/* ---------------- registration quota ---------------- */

if (!quota_enabled()) {
    $note('registration quota', true, 'Switched off: an e-mail address may hold as many names as it likes.');
} else {
    $note(
        'registration quota: columns',
        quota_columns_ready(),
        'Import upgrade-quota.sql so an account can carry its own allowance.'
    );
    $note(
        'registration quota: accounts freed',
        trim((string)setting('quota_grandfather_at', '')) !== '',
        'Give every account that exists now an unlimited quota (Settings -> Registration quota) before you '
        . 'rely on the limit, otherwise your present users lose their allowance.'
    );
    $note(
        'registration quota',
        true,
        'The default is ' . quota_default_limit() . ' name(s) per address across all suffixes; '
        . (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 1', [], 0) . ' account(s) are '
        . 'unlimited and ' . (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 0 AND quota_limit IS NOT NULL', [], 0)
        . ' have their own number.'
    );
}

/* ---------------- GitHub star bonus ---------------- */

if (!setting_bool('star_bonus_enabled', false)) {
    $note('GitHub star bonus', true, 'Switched off.');
} else {
    $note(
        'GitHub star bonus: columns',
        star_bonus_columns_ready(),
        'Import upgrade-star.sql so the bonus can be stored.'
    );
    $note(
        'GitHub star bonus: repository',
        star_bonus_repo() !== '',
        'Enter the repository as owner/name under Settings -> GitHub star bonus.'
    );
    $note(
        'GitHub star bonus: token',
        trim((string)setting('star_token', '')) !== '',
        'Without a token GitHub allows only 60 anonymous requests an hour for the whole server.'
    );
    $note(
        'GitHub star bonus',
        true,
        star_bonus_repo() === '' ? 'No repository configured yet.' : 'Repository ' . star_bonus_repo()
            . '; ' . (int)star_bonus_amount() . ' extra name(s) per account; '
            . (int)db_value('SELECT COUNT(*) FROM users WHERE star_bonus_at IS NOT NULL', [], 0)
            . ' account(s) collected it so far.'
    );
}

/* ---------------- publishing services ---------------- */

if (!dnshe_enabled()) {
    $note('DNSHE publishing', true, 'Switched off; only Cloudflare is used.');
} else {
    $note(
        'DNSHE publishing: columns',
        dnshe_columns_ready(),
        'Import upgrade-dnshe.sql so a suffix can name its service and keep its own credentials.'
    );
    $dnsheZones = [];
    foreach (zone_list() as $zoneRow) {
        $zoneName = (string)$zoneRow['name'];
        if (publish_provider($zoneName) === 'dnshe') {
            $dnsheZones[] = $zoneName . (dnshe_zone_ready($zoneName) ? '' : ' (not ready)');
        }
    }
    $note(
        'DNSHE publishing',
        $dnsheZones !== [],
        $dnsheZones === []
            ? 'No suffix is set to DNSHE yet - choose the service in the zone table on the DNS page.'
            : 'Suffixes through DNSHE: ' . implode(', ', $dnsheZones) . '. Credentials: '
              . (dnshe_global_key() === '' ? 'general pair missing' : 'general pair stored') . '.'
    );
}

/* ---------------- content (informational) ---------------- */

$info = [];
try {
    $info[] = 'zones: ' . (int)db_value('SELECT COUNT(*) FROM zones', [], 0);
    $info[] = 'domains: ' . (int)db_value('SELECT COUNT(*) FROM domains', [], 0)
        . ' (' . (int)db_value("SELECT COUNT(*) FROM domains WHERE status = 'pending'", [], 0) . ' pending, '
        . (int)db_value("SELECT COUNT(*) FROM domains WHERE status = 'active'", [], 0) . ' active)';
    $info[] = 'records: ' . (int)db_value('SELECT COUNT(*) FROM records', [], 0);
    $info[] = 'record changes: ' . (int)db_value('SELECT COUNT(*) FROM change_requests', [], 0)
        . ' (' . count_pending_changes() . ' pending)';
    $info[] = 'administrators: ' . (int)db_value('SELECT COUNT(*) FROM admins', [], 0);
    $info[] = 'reserved names: ' . reserved_name_count()
        . (reserved_table_ready() ? '' : ' (the reserved_names table does not exist yet)');
    $info[] = 'user accounts: ' . (int)db_value('SELECT COUNT(*) FROM users', [], 0)
        . ' (' . (int)db_value("SELECT COUNT(*) FROM users WHERE status = 'pending'", [], 0) . ' unconfirmed)';
    $info[] = 'back-office address: ' . (string)($backOfficeEntry ?? '(not checked)');
    $iconFiles = [];
    foreach (['favicon.ico', 'favicon.svg', 'favicon-192.png', 'apple-touch-icon.png'] as $icon) {
        if (is_file(FD_ROOT . '/' . $icon)) {
            $iconFiles[] = $icon;
        }
    }
    $info[] = 'tab icon: ' . ($iconFiles === [] ? 'built-in default (no file uploaded)' : implode(', ', $iconFiles));
    $info[] = 'Cloudflare publishing: ' . (cf_enabled() ? 'on' : 'off')
        . (cf_token() === '' ? ' (no token)' : ' (token stored)')
        . ', pushed records: ' . (publish_mapping_ready()
            ? (int)db_value('SELECT COUNT(*) FROM dns_records', [], 0)
            : 'table missing');
} catch (Throwable $e) {
    $info[] = 'counts unavailable: ' . $e->getMessage();
}

// ---------------------------------------------------------------- one-click repair
// The operator can apply the very statements this page prints, without opening phpMyAdmin. Each one is
// run on its own, so a single failure never stops the rest, and everything is written to the audit log.
$fixLog = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'fix') {
    csrf_verify();
    foreach ((array)($_POST['fix'] ?? []) as $wanted) {
        $wanted = (string)$wanted;
        $sql    = '';
        if (str_starts_with($wanted, 'table:')) {
            $table = substr($wanted, 6);
            if (isset($tableFixes[$table])) {
                $sql = (string)$tableFixes[$table];
            }
        } elseif (isset($columnFixes[$wanted])) {
            $sql = (string)$columnFixes[$wanted];
        }
        if ($sql === '') {
            continue;
        }

        $error = '';
        try {
            db_query($sql);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $fixLog[] = ['sql' => $sql, 'error' => $error];
        log_action('selftest_fix', 'settings', null,
            ($error === '' ? 'applied: ' : 'FAILED: ') . $sql);
    }
}

$failed = 0;
foreach ($results as $result) {
    if (!$result['pass']) {
        $failed++;
    }
}

page_header('Self test');
?>
<p>
This page checks the deployment: uploaded files, language catalogues and the database structure.
It reads structure only, never applicant data. You can delete this file once everything passes.
</p>

<?php if ($fixLog !== []): ?>
<h2>What the repair did</h2>
<table>
  <tr><th>Statement</th><th>Result</th></tr>
  <?php foreach ($fixLog as $entry): ?>
  <tr>
    <td class="small"><code><?= h(shorten(preg_replace('/\s+/', ' ', (string)$entry['sql']) ?? '', 160)) ?></code></td>
    <td><?= $entry['error'] === ''
        ? '<span class="ok">applied</span>'
        : '<span class="err">' . h($entry['error']) . '</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">Re-run the check by reloading this page; a statement that reports "duplicate column" or
"already exists" means it was applied before, which is harmless.</p>
<?php endif; ?>

<table>
  <tr><th>Check</th><th>Result</th><th>Note</th></tr>
  <?php foreach ($results as $result): ?>
  <tr>
    <td><?= h($result['label']) ?></td>
    <td><?= $result['pass'] ? '<span class="ok">OK</span>' : '<span class="err">FAILED</span>' ?></td>
    <td class="small"><?= $result['pass'] ? '' : h($result['hint']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if ($missingColumns !== [] || $missingTables !== []): ?>
<h2>Fix the database structure</h2>
<p>
Tick what should be applied and press the button - the statements below are then run at once, each on its
own, and the result of every one is shown above. Nothing is applied unless you ask for it, and the whole
repair is written to the audit log. (The same statements can still be copied into phpMyAdmin by hand.)
</p>
<form method="post" action="selftest.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="fix">
<?php foreach ($missingTables as $table): ?>
<p><label class="inline"><input type="checkbox" name="fix[]" value="table:<?= h($table) ?>" checked>
  create the table <code><?= h($table) ?></code></label></p>
<?php endforeach; ?>
<?php foreach ($missingColumns as $key): ?>
<?php if (!isset($columnFixes[$key])) { continue; } ?>
<p><label class="inline"><input type="checkbox" name="fix[]" value="<?= h($key) ?>" checked>
  <code><?= h($key) ?></code></label></p>
<?php endforeach; ?>
<p><input type="submit" value="Apply the selected statements"></p>
</form>

<h2>SQL to fix the database structure</h2>
<p>Run these statements once in phpMyAdmin (an "already exists" error means it is there already):</p>
<pre class="wrap code-block"><?php
foreach ($missingTables as $table) {
    echo h($tableFixes[$table]) . "\n\n";
}
foreach ($missingColumns as $key) {
    if (isset($columnFixes[$key])) {
        echo h($columnFixes[$key]) . "\n";
    }
}
?></pre>
<?php endif; ?>

<h2>Content</h2>
<ul>
  <?php foreach ($info as $line): ?>
  <li><?= h($line) ?></li>
  <?php endforeach; ?>
</ul>

<h2>Result</h2>
<p>
<?= $failed === 0
    ? '<span class="ok">All checks passed.</span>'
    : '<span class="err">' . (int)$failed . ' check(s) failed.</span>' ?>
</p>
<p class="small">
<a href="<?= h(url('index.php')) ?>">Open the public site</a> &middot;
<a href="<?= h(url('zh/index.php')) ?>">Chinese home page</a> &middot;
<a href="index.php">Back to the dashboard</a>
</p>
<?php
page_footer();
