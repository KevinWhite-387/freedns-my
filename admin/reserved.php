<?php
declare(strict_types=1);

/**
 * freedns.my - reserved names.
 *
 * Names on this list cannot be requested through the ordinary form. The applicant has to tick
 * "This is a reserved name" on the request form, describe the intended use, and only then the
 * name can be registered - a single-character name included.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('The reserved-name list');

$zones = zone_list();
$errors = [];
$added  = 0;
$skipped = [];

$zoneNames = array_map(static fn($zone) => (string)$zone['name'], $zones);
$selected  = strtolower(trim((string)($_REQUEST['zone'] ?? '')));
if ($selected === '' || !in_array($selected, $zoneNames, true)) {
    $selected = $zoneNames === [] ? '' : $zoneNames[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $zone = strtolower(trim((string)($_POST['zone'] ?? $selected)));
        $note = trim((string)($_POST['note'] ?? ''));
        $raw  = (string)($_POST['names'] ?? '');

        if ($zoneNames === []) {
            $errors[] = 'Create a zone first (Zones page): reserved names belong to a suffix.';
        } elseif (!in_array($zone, $zoneNames, true)) {
            $errors[] = 'Choose one of the configured zones.';
        } elseif (trim($raw) === '') {
            $errors[] = 'Enter at least one name.';
        } else {
            $lines = preg_split('/[\r\n,;]+/', $raw) ?: [];
            $seen  = [];
            foreach ($lines as $line) {
                $label = normalize_label(trim($line), $zone);
                if ($label === '') {
                    continue;
                }
                if (isset($seen[$label])) {
                    continue;
                }
                $seen[$label] = true;

                $problem = reserved_label_problem($zone, $label);
                if ($problem !== '') {
                    $skipped[] = $label . ' (' . reserved_problem_text($problem) . ')';
                    continue;
                }

                $result = reserved_name_add($zone, $label, $note, (string)$admin['username']);
                if ($result === '') {
                    $added++;
                    log_action('reserved_added', 'reserved', null, $label . '.' . $zone);
                } else {
                    $skipped[] = $label . ' (' . reserved_problem_text($result) . ')';
                }
            }

            if ($added === 0 && $skipped === []) {
                $errors[] = 'No name could be read from that input.';
            }
        }

        $selected = $zone;
    }

    if ($action === 'remove') {
        $ids = array_map('intval', (array)($_POST['remove'] ?? []));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            $errors[] = 'Select at least one reserved name to remove.';
        } else {
            foreach ($ids as $id) {
                $row = db_one('SELECT * FROM reserved_names WHERE id = ?', [$id]);
                if ($row === null) {
                    continue;
                }
                reserved_name_delete($id);
                log_action('reserved_removed', 'reserved', null, (string)$row['label'] . '.' . (string)$row['zone']);
            }
            flash(count($ids) . ' reserved name(s) removed.', 'ok');
            redirect('reserved.php?zone=' . urlencode((string)($_POST['zone'] ?? $selected)));
        }
    }

    if ($action === 'add' && $errors === []) {
        $message = $added . ' reserved name(s) added.';
        if ($skipped !== []) {
            $message .= ' Skipped: ' . implode(', ', array_slice($skipped, 0, 8))
                . (count($skipped) > 8 ? ' ...' : '');
        }
        flash($message, $added > 0 ? 'ok' : 'error');
        redirect('reserved.php?zone=' . urlencode($selected));
    }
}

/** Ready-made messages for reserved_label_problem() results. */
function reserved_problem_text(string $problem): string
{
    switch ($problem) {
        case 'zone':
            return 'unknown zone';
        case 'empty':
            return 'empty name';
        case 'long':
            return 'longer than 63 characters';
        case 'dots':
            return 'enter one label without dots';
        case 'chars':
            return 'only a-z, 0-9 and "-" (not at the start or end)';
        case 'exists':
            return 'already on the list';
        case 'notable':
            return 'the reserved_names table is missing - run upgrade-reserved.sql';
        default:
            return $problem;
    }
}

$tableReady = reserved_table_ready();
$rows = reserved_name_rows();

/** Current registration state of every reserved name, for the table. */
$registered = [];
if ($rows !== []) {
    foreach ($rows as $row) {
        $domain = db_one(
            'SELECT name, status, request_code FROM domains WHERE name = ?',
            [(string)$row['label'] . '.' . (string)$row['zone']]
        );
        if ($domain !== null && !in_array((string)$domain['status'], ['rejected', 'deleted'], true)) {
            $registered[(int)$row['id']] = $domain;
        }
    }
}

$zoneRows = array_values(array_filter($rows, static fn(array $row): bool =>
    $selected === '' || strcasecmp((string)$row['zone'], $selected) === 0));

page_header('Reserved names');
?>
<p>
A name on this list cannot be registered through the ordinary request form: the applicant is told to
tick <em>“This is a reserved name”</em>, to describe what the name will be used for, and only then the
request goes through - with the usual manual review. Reserved names may be a single character long.
The list is not published on the public pages.
</p>
<p class="hint">
The built-in protection list (<code>www</code>, <code>ns1</code>, <code>postmaster</code>, …) still
refuses those names: they are never handed out, not even through the reserved channel. Such a name can
be put on this list, but a request for it is still refused - the table marks them.
</p>

<?= error_list($errors) ?>

<?php if (!$tableReady): ?>
<div class="msg error">
  <strong>The reserved list is not set up in the database yet.</strong>
  <p>
  The table <code>reserved_names</code> does not exist, so nothing can be listed or added. Import
  <code>upgrade-reserved.sql</code> with phpMyAdmin, or run these statements once:
  </p>
  <pre class="wrap code-block">CREATE TABLE IF NOT EXISTS reserved_names (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone       VARCHAR(190) NOT NULL,
  label      VARCHAR(63)  NOT NULL,
  note       VARCHAR(190) DEFAULT NULL,
  created_by VARCHAR(32)  DEFAULT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reserved_name (zone, label),
  KEY idx_reserved_zone (zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE domains ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER language;</pre>
  <p class="small">
  Reload this page afterwards. <a href="selftest.php">Self test</a> also reports the missing table.
  </p>
</div>
<?php endif; ?>

<h2>Add reserved names</h2>
<form method="post" action="reserved.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="add">

<label for="zone">Zone</label>
<select id="zone" name="zone">
  <?php foreach ($zones as $zoneOption): ?>
  <option value="<?= h((string)$zoneOption['name']) ?>"
      <?= strcasecmp((string)$zoneOption['name'], $selected) === 0 ? ' selected' : '' ?>>
    <?= h((string)$zoneOption['name']) ?>
  </option>
  <?php endforeach; ?>
</select>
<p class="hint">Reserved names are kept per suffix.</p>

<label for="names">Names (one per line, or separated by commas)</label>
<textarea id="names" name="names" rows="6" placeholder="a&#10;b&#10;news"></textarea>
<p class="hint">
Enter one label per line, for example <code>news</code>. A full name such as
<code>news.<?= h($selected === '' ? 'zone' : $selected) ?></code> is accepted too; the suffix is
removed automatically. Single-character labels are allowed here.
</p>

<label for="note">Note for yourself (optional)</label>
<input type="text" id="note" name="note" maxlength="190" value="">

<p><input type="submit" value="Add to the reserved list"></p>
</form>

<h2>Reserved names (<?= count($zoneRows) ?> in <?= h($selected === '' ? 'all zones' : $selected) ?>)</h2>

<?php if ($zoneRows === []): ?>
<p>Nothing is reserved in this zone yet. Untick nothing - just use the form above.</p>
<?php else: ?>
<form method="post" action="reserved.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="remove">
<input type="hidden" name="zone" value="<?= h($selected) ?>">
<table>
  <tr>
    <th>Name</th><th>Note</th><th>Added</th><th>Registered</th><th>Remove</th>
  </tr>
  <?php foreach ($zoneRows as $row): ?>
  <?php
  $label  = (string)$row['label'];
  $fqdn   = $label . '.' . (string)$row['zone'];
  $state  = $registered[(int)$row['id']] ?? null;
  $always = in_array($label, reserved_labels(), true);
  ?>
  <tr>
    <td>
      <code><?= h($fqdn) ?></code>
      <?php if ($always): ?>
      <span class="err">built-in protection</span>
      <?php endif; ?>
    </td>
    <td class="small"><?= h((string)($row['note'] ?? '')) ?></td>
    <td class="small">
      <?= h(format_date((string)$row['created_at'])) ?>
      <?= trim((string)($row['created_by'] ?? '')) === '' ? '' : ' ' . h((string)$row['created_by']) ?>
    </td>
    <td class="small">
      <?php if ($state === null): ?>
      -
      <?php else: ?>
      <?= h(status_text((string)$state['status'])) ?> <?= h((string)$state['request_code']) ?>
      <?php endif; ?>
    </td>
    <td><input type="checkbox" name="remove[]" value="<?= (int)$row['id'] ?>"></td>
  </tr>
  <?php endforeach; ?>
</table>
<p><input type="submit" value="Remove selected"></p>
</form>
<?php endif; ?>

<h2>How it looks to the applicant</h2>
<ul>
  <li>Ordinary request for a reserved name → refused, and the applicant is told to tick the
      reserved-name option.</li>
  <li>Reserved option for a name that is <strong>not</strong> on this list → refused as well, so the
      option cannot be used to skip the ordinary rules.</li>
  <li>Reserved option for a name that <strong>is</strong> on the list → accepted only with a
      description; single-character names are allowed.</li>
  <li>The request is then stored with a “reserved” marker, you receive the notification mail (it marks
      the request in the body) and it waits in the normal review queue.</li>
</ul>
<?php
page_footer();
