<?php
declare(strict_types=1);

/**
 * freedns.my - administration: the zones (suffixes) names are handed out under.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('The suffix list');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id            = (int)($_POST['id'] ?? 0);
        $name          = strtolower(trim((string)($_POST['name'] ?? '')));
        $title         = trim((string)($_POST['title'] ?? ''));
        $titleZh       = trim((string)($_POST['title_zh'] ?? ''));
        $description   = trim((string)($_POST['description'] ?? ''));
        $descriptionZh = trim((string)($_POST['description_zh'] ?? ''));
        $cfZoneId      = trim((string)($_POST['cf_zone_id'] ?? ''));
        $maxPerEmail   = (int)($_POST['max_domains_per_email'] ?? 0);
        $sortOrder     = (int)($_POST['sort_order'] ?? 0);

        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $name)
            || strlen($name) > 190) {
            $errors[] = 'The zone name must be a domain name such as freedns.my or asia.ci.';
        } else {
            $clash = db_one('SELECT id FROM zones WHERE name = ? AND id <> ?', [$name, $id]);
            if ($clash !== null) {
                $errors[] = 'The zone ' . $name . ' already exists.';
            }
        }
        if (strlen($title) > 190 || strlen($titleZh) > 190) {
            $errors[] = 'The title is too long.';
        }
        if ($cfZoneId !== '' && !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $cfZoneId)) {
            $errors[] = 'The Cloudflare zone id does not look like a zone id (32 hexadecimal characters). '
                . 'Copy it from the DNS page or from the Cloudflare dashboard.';
        }
        if ($maxPerEmail < 0 || $maxPerEmail > 1000) {
            $errors[] = 'The per-mailbox limit must be between 0 and 1000 (0 = use the global limit).';
        }

        if ($errors === []) {
            $params = [
                $name,
                $title === '' ? null : $title,
                $titleZh === '' ? null : $titleZh,
                $description === '' ? null : $description,
                $descriptionZh === '' ? null : $descriptionZh,
                $cfZoneId === '' ? null : $cfZoneId,
                isset($_POST['is_open']) ? 1 : 0,
                isset($_POST['allow_subdomains']) ? 1 : 0,
                $maxPerEmail,
                $sortOrder,
            ];

            $existing = $id > 0 ? db_one('SELECT * FROM zones WHERE id = ?', [$id]) : null;
            $oldName  = $existing === null ? '' : (string)$existing['name'];
            $renamed  = $id > 0 && $oldName !== '' && strcasecmp($oldName, $name) !== 0;

            // A rename must not collide with names that already exist in the target suffix.
            if ($renamed) {
                $clash = (int)db_value(
                    "SELECT COUNT(*) FROM domains d WHERE d.zone = ? AND EXISTS (
                        SELECT 1 FROM domains x WHERE x.name = CONCAT(d.label, '.', ?))",
                    [$oldName, $name],
                    0
                );
                if ($clash > 0) {
                    $errors[] = $clash . ' name(s) of ' . $oldName . ' would collide with existing names in '
                        . $name . '; the zone was not renamed.';
                }
            }

            if ($errors === []) {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    if ($id > 0) {
                        db_query(
                            'UPDATE zones SET name = ?, title = ?, title_zh = ?, description = ?,
                                description_zh = ?, cf_zone_id = ?, is_open = ?, allow_subdomains = ?,
                                max_domains_per_email = ?, sort_order = ?, updated_at = NOW() WHERE id = ?',
                            array_merge($params, [$id])
                        );
                        if ($renamed) {
                            // Move the names of the zone with it, name column included.
                            db_query(
                                "UPDATE domains SET zone = ?, name = CONCAT(label, '.', ?) WHERE zone = ?",
                                [$name, $name, $oldName]
                            );
                        }
                    } else {
                        db_query(
                            'INSERT INTO zones (name, title, title_zh, description, description_zh, cf_zone_id,
                                is_open, allow_subdomains, max_domains_per_email, sort_order, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                            $params
                        );
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'The zone could not be saved because of a server error.';
                }
            }

            if ($errors === []) {
                if ($id > 0) {
                    log_action(
                        'zone_updated',
                        'zone',
                        $id,
                        'Zone ' . $oldName . ' -> ' . $name . ($renamed ? ' (names moved)' : '')
                    );
                    flash('Zone ' . $name . ' saved.' . ($renamed ? ' The names of the zone were renamed too.' : ''), 'ok');
                } else {
                    log_action('zone_added', 'zone', (int)db()->lastInsertId(), 'Zone ' . $name);
                    flash('Zone ' . $name . ' created.', 'ok');
                }
                zone_refresh();
                redirect('zones.php');
            }
        }
    }

    if ($action === 'delete') {
        $id   = (int)($_POST['id'] ?? 0);
        $zone = $id > 0 ? db_one('SELECT * FROM zones WHERE id = ?', [$id]) : null;

        if ($zone === null) {
            $errors[] = 'That zone does not exist.';
        } else {
            $used = (int)db_value('SELECT COUNT(*) FROM domains WHERE zone = ?', [(string)$zone['name']], 0);
            if ($used > 0) {
                $errors[] = 'The zone ' . (string)$zone['name'] . ' still has ' . $used
                    . ' domain record(s); it cannot be removed while names are registered in it.';
            } else {
                db_query('DELETE FROM zones WHERE id = ?', [$id]);
                log_action('zone_deleted', 'zone', $id, 'Zone ' . (string)$zone['name']);
                zone_refresh();
                flash('Zone ' . (string)$zone['name'] . ' removed.', 'ok');
                redirect('zones.php');
            }
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? db_one('SELECT * FROM zones WHERE id = ?', [$editId]) : null;

// A rejected form: keep the typed values so the operator does not have to enter them again.
if ($errors !== [] && (string)($_POST['action'] ?? '') === 'save') {
    $edit = [
        'id'                    => (int)($_POST['id'] ?? 0),
        'name'                  => strtolower(trim((string)($_POST['name'] ?? ''))),
        'title'                 => trim((string)($_POST['title'] ?? '')),
        'title_zh'              => trim((string)($_POST['title_zh'] ?? '')),
        'description'           => trim((string)($_POST['description'] ?? '')),
        'description_zh'        => trim((string)($_POST['description_zh'] ?? '')),
        'cf_zone_id'            => trim((string)($_POST['cf_zone_id'] ?? '')),
        'is_open'               => isset($_POST['is_open']) ? 1 : 0,
        'allow_subdomains'      => isset($_POST['allow_subdomains']) ? 1 : 0,
        'max_domains_per_email' => (int)($_POST['max_domains_per_email'] ?? 0),
        'sort_order'            => (int)($_POST['sort_order'] ?? 0),
    ];
}
$isNewZone = $edit === null || (int)($edit['id'] ?? 0) === 0;

$counts = [];
foreach (db_all("SELECT zone, status, COUNT(*) AS n FROM domains GROUP BY zone, status") as $row) {
    $counts[(string)$row['zone']][(string)$row['status']] = (int)$row['n'];
}

$zones = zone_list();

page_header('Zones');
?>
<p>
A zone is a suffix that names are handed out under, for example <code>freedns.my</code> or
<code>asia.ci</code>. Requests choose one of the open zones on the request form, and the records of the
approved names are exported per zone (see <a href="export.php">Export</a>).
</p>
<p class="hint">
Adding a zone here does not create the DNS zone itself: host the zone (aaPanel DNS, Cloudflare, BIND, ...)
and publish the exported records there. The zone must also be in the Public Suffix List before name-server
delegation can be offered; until then the registry answers for the names itself.
</p>

<?= error_list($errors) ?>

<table>
  <tr><th>ID</th><th>Zone</th><th>Requests</th><th>Sub-names</th><th>Mailbox limit</th><th>Order</th>
      <th>Domains</th><th></th></tr>
  <?php foreach ($zones as $zone): ?>
  <?php
  $zoneName = (string)$zone['name'];
  $byStatus = $counts[$zoneName] ?? [];
  $total    = array_sum($byStatus);
  ?>
  <tr>
    <td><?= (int)$zone['id'] ?></td>
    <td><code><?= h($zoneName) ?></code><?= trim((string)($zone['title'] ?? '')) !== '' ? '<br><span class="small">' . h((string)$zone['title']) . '</span>' : '' ?></td>
    <td><?= (int)$zone['is_open'] === 1 ? '<span class="ok">open</span>' : '<span class="err">closed</span>' ?></td>
    <td><?= (int)$zone['allow_subdomains'] === 1 ? 'yes' : 'no' ?></td>
    <td><?= (int)$zone['max_domains_per_email'] === 0
        ? 'global (' . (int)setting('max_domains_per_email', '5') . ')'
        : (int)$zone['max_domains_per_email'] ?></td>
    <td><?= (int)$zone['sort_order'] ?></td>
    <td><?= number_format($total) ?>
        <span class="small">(<?= (int)($byStatus['pending'] ?? 0) ?> pending,
        <?= (int)($byStatus['active'] ?? 0) ?> active)</span></td>
    <td class="actions">
      <a href="zones.php?edit=<?= (int)$zone['id'] ?>">edit</a>
      <form class="inline" method="post" action="zones.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$zone['id'] ?>">
        <input type="submit" value="remove"<?= $total > 0 ? ' disabled' : '' ?>>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2><?= $isNewZone ? 'Add a zone' : 'Edit zone ' . h((string)$edit['name']) ?></h2>
<form method="post" action="zones.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $isNewZone ? 0 : (int)$edit['id'] ?>">

  <label for="name">Zone name *</label>
  <input type="text" id="name" name="name" maxlength="190"
         value="<?= h((string)($edit['name'] ?? '')) ?>" placeholder="freedns.my">
  <p class="hint">Lower case, no wildcard, no leading dot.</p>

  <label for="title">Title (optional)</label>
  <input type="text" id="title" name="title" maxlength="190" value="<?= h((string)($edit['title'] ?? '')) ?>">
  <p class="hint">Shown next to the zone on the request form, for example "Asia community names".</p>

  <label for="title_zh">中文标题（可选）</label>
  <input type="text" id="title_zh" name="title_zh" maxlength="190" value="<?= h((string)($edit['title_zh'] ?? '')) ?>">
  <p class="hint">中文页面 (/zh/) 上显示的后缀标题，留空则使用上面的英文标题。</p>

  <label for="description">Description (optional, plain text)</label>
  <textarea id="description" name="description" rows="4"><?= h((string)($edit['description'] ?? '')) ?></textarea>

  <label for="description_zh">中文说明（可选，纯文本）</label>
  <textarea id="description_zh" name="description_zh" rows="4"><?= h((string)($edit['description_zh'] ?? '')) ?></textarea>

  <label for="cf_zone_id">Cloudflare zone id (optional)</label>
  <input type="text" id="cf_zone_id" name="cf_zone_id" maxlength="64"
         value="<?= h((string)($edit['cf_zone_id'] ?? '')) ?>" placeholder="for example 1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d">
  <p class="hint">
    Fill this in to let the registry publish approved names of this suffix in Cloudflare automatically
    (see the <a href="dns.php">DNS</a> page). Leave it empty to keep publishing by hand through the
    export.
  </p>

  <p>
    <input type="checkbox" id="is_open" name="is_open" value="1"
        <?= $isNewZone || (int)$edit['is_open'] === 1 ? 'checked' : '' ?>>
    <label class="inline" for="is_open">Accept new requests in this zone</label>
  </p>
  <p>
    <input type="checkbox" id="allow_subdomains" name="allow_subdomains" value="1"
        <?= $isNewZone || (int)$edit['allow_subdomains'] === 1 ? 'checked' : '' ?>>
    <label class="inline" for="allow_subdomains">Let registrants add sub-names (www.name.zone)</label>
  </p>

  <label for="max_domains_per_email">Per-mailbox limit (0 = use the global limit)</label>
  <input type="text" id="max_domains_per_email" name="max_domains_per_email" maxlength="4"
         value="<?= (int)($edit['max_domains_per_email'] ?? 0) ?>">

  <label for="sort_order">Sort order (lower comes first on the request form)</label>
  <input type="text" id="sort_order" name="sort_order" maxlength="6" value="<?= (int)($edit['sort_order'] ?? 0) ?>">

  <p><input type="submit" value="<?= $isNewZone ? 'Create zone' : 'Save zone' ?>">
  <?php if (!$isNewZone): ?> <a href="zones.php">cancel</a><?php endif; ?></p>
</form>
<?php
page_footer();
