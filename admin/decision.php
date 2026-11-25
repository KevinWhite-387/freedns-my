<?php
declare(strict_types=1);

/**
 * freedns.my - applies approve / reject / suspend / delete decisions.
 * Reached only by POST from the administration pages.
 *
 * With automatic Cloudflare publishing switched on, the records are pushed (or removed) before the
 * local change is committed: in the default strict mode a failed API call leaves the request
 * untouched, so the registry never shows a name as ACTIVE while its records are missing.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/index.php'));
}
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$note   = trim((string)($_POST['public_note'] ?? ''));

$ids = [];
if (isset($_POST['id'])) {
    $ids[] = (int)$_POST['id'];
}
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $value) {
        $ids[] = (int)$value;
    }
}
$ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));

$targets = [
    'approve'    => 'active',
    'reject'     => 'rejected',
    'suspend'    => 'suspended',
    'reactivate' => 'active',
    'delete'     => 'deleted',
    'pending'    => 'pending',
];

$back = safe_relative_url((string)($_POST['back'] ?? ''), url('admin/domains.php'));

if (!isset($targets[$action])) {
    flash('Unknown action requested.', 'error');
    redirect($back);
}
if ($ids === []) {
    flash('No domain was selected.', 'error');
    redirect($back);
}

$newStatus    = $targets[$action];
$notifyStates = ['active', 'rejected', 'suspended'];
$changed      = 0;
$skipped      = [];
$failed       = [];

// A name published through DNSHE cannot be deleted: DNSHE does not allow a registered free subdomain to
// be removed. Suspend it instead - that takes its records away and keeps the name reserved.
if ($newStatus === 'deleted') {
    $blocked = [];
    $kept    = [];
    foreach ($ids as $id) {
        $row = db_one('SELECT id, name, zone FROM domains WHERE id = ?', [$id]);
        if ($row !== null && publish_provider((string)$row['zone']) === 'dnshe') {
            $blocked[] = (string)$row['name'];
            continue;
        }
        $kept[] = $id;
    }
    if ($blocked !== []) {
        flash(
            'DNSHE does not allow a registered subdomain to be deleted, so these names were not deleted: '
            . implode(', ', $blocked) . '. Suspend them instead - the records are removed and the name stays '
            . 'reserved.',
            'error'
        );
    }
    $ids = $kept;
    if ($ids === []) {
        redirect($back);
    }
}

// A reviewer may only decide names inside the suffixes assigned to it; the rest is reported back.
$outOfScope = [];
if (!is_owner()) {
    $kept = [];
    foreach ($ids as $id) {
        $row = db_one('SELECT id, name, zone FROM domains WHERE id = ?', [$id]);
        if ($row === null) {
            continue;
        }
        if (!admin_may_zone((string)$row['zone'])) {
            $outOfScope[] = (string)$row['name'];
            continue;
        }
        $kept[] = $id;
    }
    if ($outOfScope !== []) {
        flash(
            'Not your suffixes, so nothing was done for: ' . implode(', ', $outOfScope)
            . '. Your account may only decide ' . implode(', ', admin_scope_zones()) . '.',
            'error'
        );
    }
    $ids = $kept;
    if ($ids === []) {
        redirect($back);
    }
}

foreach ($ids as $id) {
    $domain = domain_by_id($id);
    if ($domain === null) {
        continue;
    }

    $records = domain_records($id);

    // Never publish a name that has nothing to resolve to.
    if ($newStatus === 'active' && count($records) < 1) {
        $skipped[] = (string)$domain['name'];
        continue;
    }

    $wasStatus  = (string)$domain['status'];
    $isDecision = in_array($newStatus, $notifyStates, true);

    /* ---------------- Cloudflare first ---------------- */

    $mappingAction = '';
    $mappingRows   = [];
    $zoneName      = (string)$domain['zone'];

    if (publish_zone_ready($zoneName)) {
        if ($newStatus === 'active') {
            $sync = publish_sync_domain($id, (string)$domain['name'], $zoneName, publish_records_of($records), !empty($domain['proxied']));
            if ($sync['ok']) {
                $mappingAction = 'store';
                $mappingRows   = $sync['rows'];
            }
        } else {
            $sync = publish_remove_domain($id, $zoneName, $newStatus === 'deleted');
            if ($sync['ok']) {
                $mappingAction = 'clear';
            }
        }

        if (!$sync['ok']) {
            log_action('cf_sync_failed', 'domain', $id, (string)$domain['name'] . ': ' . $sync['error']);
            if (cf_strict()) {
                $failed[] = (string)$domain['name'] . ' - ' . $sync['error'];
                continue;
            }
        } elseif ($mappingAction === 'store') {
            log_action(
                'cf_synced',
                'domain',
                $id,
                (string)$domain['name'] . ': ' . $sync['created'] . ' created, ' . $sync['updated']
                    . ' updated, ' . $sync['deleted'] . ' removed'
            );
        }
    }

    /* ---------------- local change ---------------- */

    $sql    = 'UPDATE domains SET status = ?,
                   public_note = COALESCE(NULLIF(?, \'\'), public_note),
                   updated_at = NOW(),
                   decided_at = ' . ($isDecision ? 'NOW()' : 'NULL') . ',
                   decided_by = ' . ($isDecision ? '?' : 'NULL') . '
               WHERE id = ?';
    $params = [$newStatus, $note];
    if ($isDecision) {
        $params[] = (string)$admin['username'];
    }
    $params[] = $id;

    try {
        $pdo = db();
        $pdo->beginTransaction();

        if ($mappingAction === 'store') {
            publish_store_mapping($id, (array)($mappingRows ?? []));
        } elseif ($mappingAction === 'clear') {
            publish_forget_mapping($id);
        }

        db_query($sql, $params);

        // A name that is no longer active cannot carry a queued record change.
        if ($newStatus !== 'active') {
            drop_pending_changes($id);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        log_action('decision_failed', 'domain', $id, (string)$domain['name'] . ': ' . $e->getMessage());
        /* An exception, not a refused answer: print what it said - the same text that went to the log. */
        $failed[] = (string)$domain['name'] . ': ' . $e->getMessage();
        continue;
    }

    // Only tell the applicant when something really changed.
    if ($wasStatus !== $newStatus && $isDecision) {
        $updated = domain_by_id($id);
        if ($updated !== null) {
            notify_applicant_decision($updated, domain_records($id));
        }
    }

    log_action(
        'decision_' . $action,
        'domain',
        $id,
        (string)$domain['name'] . ': ' . $wasStatus . ' -> ' . $newStatus
            . ($note === '' ? '' : ' | note: ' . $note)
    );
    $changed++;
}

if ($changed === 0) {
    $reason = 'No matching domain record was found; nothing was changed.';
    if ($failed !== []) {
        $reason = 'Nothing was changed - ' . (function_exists('publish_provider_label') ? publish_provider_label((string)publish_provider((string)($domain['zone'] ?? ''))) : 'The provider') . ' refused: ' . implode('; ', $failed)
            . '. Fix the record or the DNS settings and try again.';
    } elseif ($skipped !== []) {
        $reason = 'Nothing was changed: ' . implode(', ', $skipped)
            . ' has no DNS record on file, so it cannot be set to ACTIVE.';
    }
    flash($reason, 'error');
    redirect($back);
}

flash(
    ($changed === 1
        ? 'Request updated: status is now '
        : $changed . ' requests updated: status is now ') . status_text($newStatus) . '.'
        . ($skipped === [] ? '' : ' Skipped (no record on file): ' . implode(', ', $skipped) . '.')
        . ($failed === [] ? '' : ' Failed at Cloudflare (left unchanged): ' . implode('; ', $failed) . '.'),
    $failed === [] && $skipped === [] ? 'ok' : 'info'
);
redirect($back);
