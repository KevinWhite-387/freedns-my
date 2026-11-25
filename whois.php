<?php
declare(strict_types=1);

/**
 * freedns.my - WHOIS query page.
 *
 * Names of this registry are answered from its own database (without personal data); everything else
 * is looked up in RDAP and, if that has nothing, with the classic port 43 service. The page needs no
 * session and sets no cookie.
 */

require __DIR__ . '/inc/bootstrap.php';

$input   = trim((string)($_GET['domain'] ?? ''));
$domain  = whois_normalize($input);
$errors  = [];
$notices = [];
$result  = null;
$source  = '';

if ($input !== '') {
    if (!setting_bool('whois_enabled', true)) {
        $errors[] = t('whois.disabled');
    } elseif (!whois_valid_domain($domain)) {
        $errors[] = t('whois.invalid', $input);
    } else {
        $ownZone = whois_own_zone($domain);

        if ($ownZone !== '') {
            $text = whois_internal_text($domain);
            if ($text === null) {
                $notices[] = t('whois.not_registered', $domain, $ownZone);
            } else {
                $result = $text;
                $source = 'internal';
            }
        } elseif (!setting_bool('whois_external', true)) {
            $errors[] = t('whois.external_off');
        } else {
            $limit  = max(1, (int)setting('whois_external_per_hour', '20'));
            $recent = whois_queries_recent(client_ip());

            if ($recent >= $limit) {
                $errors[] = t('whois.limit', (string)$limit);
            } else {
                $cacheMinutes = max(0, (int)setting('whois_cache_minutes', '15'));
                $cached       = whois_cache_get($domain, $cacheMinutes);

                if ($cached !== null) {
                    $result   = (string)$cached['payload'];
                    $source   = (string)$cached['source'];
                    $notices[] = t('whois.cached', (string)$cached['fetched_at']);
                } else {
                    whois_query_record(client_ip(), $domain);
                    $external = whois_external_text($domain);

                    if ($external['ok']) {
                        $result = $external['text'];
                        $source = $external['source'];
                        whois_cache_put($domain, $external['source'], $external['text']);
                    } else {
                        $errors[] = t('whois.lookup_failed', $domain, $external['error']);
                    }
                }
            }
        }
    }
}

page_header(t('whois.heading'));
?>
<p><?= h(t('whois.intro')) ?></p>

<?= error_list($errors) ?>

<?php foreach ($notices as $notice): ?>
<div class="msg info"><?= h($notice) ?></div>
<?php endforeach; ?>

<form method="get" action="<?= h(url('whois.php')) ?>">
<label for="domain"><?= h(t('whois.label')) ?></label>
<input type="text" id="domain" name="domain" maxlength="190" value="<?= h($input) ?>"
       placeholder="myname.freedns.my" autocomplete="off" required>
<p><input type="submit" value="<?= h(t('whois.submit')) ?>"></p>
</form>

<?php if ($result !== null): ?>
<h3><?= h($domain) ?></h3>
<pre class="wrap code-block"><?= h($result) ?></pre>
<?php if ($source === 'internal'): ?>
<p class="hint"><?= h(t('whois.internal_note')) ?></p>
<?php else: ?>
<p class="hint"><?= h(t('whois.external_note')) ?></p>
<?php endif; ?>
<?php endif; ?>

<h3><?= h(t('whois.help_heading')) ?></h3>
<ul>
  <li><?= t('whois.help_1', '<code>myname.' . h(zone_list() === []
      ? 'freedns.my'
      : (string)(zone_list()[0]['name'])) . '</code>') ?></li>
  <li><?= t('whois.help_2') ?></li>
  <li><?= t('whois.help_3', (string)max(1, (int)setting('whois_external_per_hour', '20'))) ?></li>
</ul>
<?php
page_footer();
