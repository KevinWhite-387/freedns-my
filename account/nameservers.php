<?php
declare(strict_types=1);

/** freedns.my - console: use my own name servers (delegation) for a name. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$zh     = function_exists('is_zh') && is_zh();
$errors = [];
$action = (string)($_POST['action'] ?? '');

/* ------------------------------------------------------------------ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $domain = null;
    foreach (user_domains($user) as $candidate) {
        if ((int)$candidate['id'] === (int)($_POST['domain_id'] ?? 0)) {
            $domain = $candidate;
        }
    }

    if ($domain === null) {
        $errors[] = t('val.ns_own');
    } elseif ($action === 'clear') {
        $result = ns_clear($domain);
        if ($result['ok']) {
            flash(t('ns.cleared'), 'ok');
            redirect(url('account/nameservers.php?zone=' . urlencode((string)$domain['zone'])));
        }
        $errors[] = $result['error'];
    } elseif ($action === 'apply') {
        $check = ns_validate(
            $domain,
            (array)($_POST['ns'] ?? []),
            (array)($_POST['glue_host'] ?? []),
            (array)($_POST['glue_value'] ?? [])
        );
        if (!$check['ok']) {
            $errors[] = $check['error'];
        } else {
            $result = ns_apply($domain, $check['nameservers'], $check['glue']);
            if ($result['ok']) {
                flash(t('ns.applied'), 'ok');
                redirect(url('account/nameservers.php?zone=' . urlencode((string)$domain['zone'])));
            }
            $errors[] = $result['error'];
        }
    }
}

/* ------------------------------------------------------------------ data */
$eligible = [];
foreach (user_domains($user) as $candidate) {
    if ((string)$candidate['status'] === 'active' && ns_zone_allows((string)$candidate['zone'])) {
        $eligible[] = $candidate;
    }
}

$zones = [];
foreach ($eligible as $candidate) {
    $zones[(string)$candidate['zone']] = true;
}
$zones = array_keys($zones);
sort($zones);

$zone = (string)($_GET['zone'] ?? '');
if ($zone === '' && $zones !== []) {
    $zone = $zones[0];
}
if ($zone !== '' && !in_array($zone, $zones, true)) {
    $zone = $zones[0] ?? '';
}

$one = null;
$wantId = (int)($_GET['domain'] ?? ($_POST['domain_id'] ?? 0));
if ($wantId > 0) {
    foreach ($eligible as $candidate) {
        if ((int)$candidate['id'] === $wantId) {
            $one = $candidate;
            $zone = (string)$candidate['zone'];
        }
    }
}

$inZone = [];
foreach ($eligible as $candidate) {
    if ((string)$candidate['zone'] === $zone) {
        $inZone[] = $candidate;
    }
}

page_header(t('ns.heading'));
?>
<h1><?= h(t('ns.heading')) ?></h1>
<p><?= h(t('ns.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!ns_columns_ready()): ?>

<div class="msg error"><?= h(t('ns.no_table')) ?></div>

<?php elseif ($eligible === []): ?>

<p><?= h(t('ns.none')) ?></p>

<?php else: ?>

<?php /* ---------- step 1: pick the suffix ---------- */ ?>
<h2><?= h($zh ? '一、选择后缀' : '1. Pick a suffix') ?></h2>
<p class="small"><?= h($zh
    ? '只有允许使用自有域名服务器的后缀会出现在这里。'
    : 'Only suffixes that allow their own name servers appear here.') ?></p>
<?php if (count($zones) > 1): ?>
<p>
<?php foreach ($zones as $z): ?>
  <?php if ($z === $zone && $one === null): ?>
    <strong><?= h($z) ?></strong>
  <?php else: ?>
    <a href="<?= h(url('account/nameservers.php?zone=' . urlencode($z))) ?>"><?= h($z) ?></a>
  <?php endif; ?>
  <?php if ($z !== end($zones)): ?> &middot; <?php endif; ?>
<?php endforeach; ?>
</p>
<?php else: ?>
<p><code><?= h($zone) ?></code></p>
<?php endif; ?>

<?php if ($one === null): ?>

  <?php /* ---------- step 2: the names in that suffix ---------- */ ?>
  <h2><?= h($zh ? '二、选择域名' : '2. Pick a name') ?></h2>
  <?php if ($inZone === []): ?>
    <p class="small"><?= h($zh ? '这个后缀下没有可委派的域名。' : 'No delegable name under this suffix.') ?></p>
  <?php else: ?>
  <table>
    <tr>
      <th><?= h(t('ns.name')) ?></th>
      <th><?= h(t('ns.state')) ?></th>
      <th><?= h(t('ns.servers')) ?></th>
      <th style="width:8em"></th>
    </tr>
    <?php foreach ($inZone as $candidate): ?>
    <?php $servers = ns_of_domain($candidate); ?>
    <?php $isDel = ns_domain_delegated((int)$candidate['id']); ?>
    <tr>
      <td><code><?= h((string)$candidate['name']) ?></code></td>
      <td><?= $isDel
          ? '<span class="ok">' . h(t('ns.state_delegated')) . '</span>'
          : h(t('ns.state_here')) ?></td>
      <td class="small"><?= $servers === [] ? '-' : h(implode(', ', $servers)) ?></td>
      <td>
        <a href="<?= h(url('account/nameservers.php?zone=' . urlencode($zone) . '&domain=' . (int)$candidate['id'])) ?>">
          <?= h($zh ? '修改' : 'Change') ?>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<?php else: ?>

  <?php /* ---------- step 3: edit one name ---------- */ ?>
  <?php $servers = ns_of_domain($one); ?>
  <?php $isDel = ns_domain_delegated((int)$one['id']); ?>

  <h2><?= h($zh ? '二、修改 ' : '2. Change ') ?><code><?= h((string)$one['name']) ?></code></h2>
  <p>
    <a href="<?= h(url('account/nameservers.php?zone=' . urlencode($zone))) ?>">
      <?= h($zh ? '← 返回域名列表' : '← Back to the list') ?>
    </a>
  </p>

  <table>
    <tr><th style="width:10em"><?= h(t('ns.state')) ?></th>
        <td><?= $isDel
            ? '<span class="ok">' . h(t('ns.state_delegated')) . '</span>'
            : h(t('ns.state_here')) ?></td></tr>
    <tr><th><?= h(t('ns.servers')) ?></th>
        <td class="small"><?= $servers === [] ? '-' : h(implode(', ', $servers)) ?></td></tr>
  </table>

  <form method="post" action="<?= h(url('account/nameservers.php?zone=' . urlencode($zone))) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="apply">
    <input type="hidden" name="domain_id" value="<?= (int)$one['id'] ?>">

    <?php for ($i = 0; $i < 4; $i++): ?>
      <label for="ns<?= $i ?>"><?= h(t('ns.server', (string)($i + 1))) ?></label>
      <input type="text" id="ns<?= $i ?>" name="ns[]" maxlength="190"
             value="<?= isset($servers[$i]) ? h((string)$servers[$i]) : '' ?>"
             placeholder="<?= $i === 0 ? 'ns1.cloudflare.com' : '' ?>">
    <?php endfor; ?>

    <p class="hint"><?= h(t('ns.glue_hint')) ?></p>

    <?php for ($i = 0; $i < 2; $i++): ?>
      <label for="gh<?= $i ?>"><?= h(t('ns.glue_host')) ?></label>
      <input type="text" id="gh<?= $i ?>" name="glue_host[]" maxlength="190" value=""
             placeholder="ns1.<?= h((string)$one['name']) ?>">
      <label for="gv<?= $i ?>"><?= h(t('ns.glue_value')) ?></label>
      <input type="text" id="gv<?= $i ?>" name="glue_value[]" maxlength="64" value="" placeholder="203.0.113.7">
    <?php endfor; ?>

    <p><input type="submit" value="<?= h(t('ns.apply')) ?>"></p>
  </form>

  <?php if ($isDel): ?>
  <form method="post" action="<?= h(url('account/nameservers.php?zone=' . urlencode($zone))) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear">
    <input type="hidden" name="domain_id" value="<?= (int)$one['id'] ?>">
    <p><input type="submit" value="<?= h(t('ns.clear')) ?>"></p>
  </form>
  <?php endif; ?>

<?php endif; ?>

<p class="hint"><?= h(t('ns.note')) ?></p>

<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();