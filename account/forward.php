<?php
declare(strict_types=1);

/**
 * freedns.my - console: my mail forwarding.
 *
 * A holder may only forward when the registry granted it, and only as many addresses as were granted; the
 * limit lives in users.forward_quota and is enforced inside inc/mailroute.php, so this page cannot get
 * around it even if it wanted to. Asking for more happens right here.
 *
 * Deleting an address is allowed, but it is written to the audit log and the account is mailed about it: if
 * somebody else ever gets hold of the account, silently removing a forwarding address would quietly cut the
 * owner off from his mail, and that mail would be noticed.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
邮件转发（旧版）
');

$user = require_user();

/*
 * This is the OLD page: Cloudflare's routing, forwarding only. The registry is retiring it in favour of the
 * BillionMail service, so it can be put into read-only mode (existing addresses keep working, nothing new) or
 * closed altogether - then it simply hands the visitor over to the new page.
 */
if (setting_bool('forward_legacy_closed', false)) {
    redirect(url('account/email.php'));
}
$legacyReadonly = setting_bool('forward_legacy_readonly', false);
$zh   = function_exists('lang') && is_zh();
$errors = [];
$notice = '';

/*
 * The old Cloudflare forwarding has been switched off, because the registry moved to its own mail service.
 * Opening this page then says so and points at the new place - never a bare "this feature is not open", so
 * nobody is left wondering where his forwarding went.
 */
if (!mailroute_enabled()) {
    page_header($zh ? '邮件转发（旧版）' : 'Mail Forwarding (Old)');
    ?>
    <div class="msg ok">
      <strong><?= $zh ? '已迁移到新版 Email' : 'Moved to the new Email service' ?></strong><br>
      <?= $zh
          ? '旧的邮件转发（Cloudflare 电子邮件路由，仅能收信）已停止使用。注册局现在自建邮件服务，可以收发邮件，支持网页邮箱与任意邮件客户端。'
          : 'The old mail forwarding (Cloudflare e-mail routing, receive only) has been retired. The registry now runs its own mail service, which can send and receive, and works in webmail or any mail client.' ?>
    </div>
    <p>
      <a href="<?= h(url('account/email.php')) ?>"><strong><?= $zh
          ? '前往新版 Email（我的邮箱）' : 'Go to the new Email page (my mailboxes)' ?></strong></a>
    </p>
    <p class="hint"><?= $zh
        ? '如果你只是想把某个地址的来信转到一个已有邮箱，新版服务也能做到：在那边创建一个邮箱，再在邮件客户端里设置转发即可。需要帮助请用页脚的联系方式找我们。'
        : 'If all you wanted was to have mail for an address forwarded to an existing mailbox, the new service covers that too: create a mailbox there and set up forwarding in your mail client. Ask us through the contact details in the footer if you need a hand.' ?></p>
    <?php
    page_footer();
    exit;
}

/*
 * The schema this page needs may not be imported yet (upgrade-mailvault.sql adds the request table and the
 * quota column). A missing table must never turn into a 500 on a page a holder can open: it becomes a notice
 * instead, and everything that does not need the missing piece keeps working.
 */
$requestsReady = false;
$quotaReady    = false;
try {
    $requestsReady = db_one("SHOW TABLES LIKE 'mail\\_forward\\_requests'") !== null;
} catch (Throwable $e) {
    $requestsReady = false;
}
try {
    $quotaReady = db_one("SHOW COLUMNS FROM users LIKE 'forward_quota'") !== null;
} catch (Throwable $e) {
    $quotaReady = false;
}
$schemaMissing = !$requestsReady || !$quotaReady;

$T = [
    'heading'  => $zh ? '邮件转发（旧版）' : 'Mail Forwarding (Old)',
    'intro'    => $zh
        ? '邮件转发需要注册局授权。每封被转发的邮件都会先存档并做安全检查：正常内容继续转发，出现威胁、勒索等内容则不转发，改由人工处理。'
        : 'Forwarding is granted by the registry. Every forwarded message is archived and checked first: '
          . 'ordinary mail goes on, anything that looks like a threat is held for a human instead.',
    'mine'     => $zh ? '我的转发地址' : 'My forwarding addresses',
    'quota'    => $zh ? '已授权 %s 个，已使用 %s 个' : '%s address(es) granted, %s in use',
    'nogr'     => $zh ? '你还没有被授权使用邮件转发。请在下面提交申请，注册局审核后会通知你。'
                      : 'You are not allowed to forward yet. Send a request below; the registry answers it.',
    'addr'     => $zh ? '转发地址' : 'Address',
    'to'       => $zh ? '转发到' : 'Forward to',
    'actions'  => $zh ? '操作' : 'Actions',
    'del'      => $zh ? '删除' : 'Delete',
    'none'     => $zh ? '还没有转发地址。' : 'No forwarding address yet.',
    'add'      => $zh ? '新增转发地址' : 'Add a forwarding address',
    'domain'   => $zh ? '用哪个域名' : 'Which name',
    'local'    => $zh ? '地址前半部分（例如 info）' : 'Part before the @ (for example info)',
    'target'   => $zh ? '转发到哪个邮箱' : 'Forward to which mailbox',
    'save'     => $zh ? '保存' : 'Save',
    'ask'      => $zh ? '申请邮件转发' : 'Ask for mail forwarding',
    'ask_want' => $zh ? '需要几个地址' : 'How many addresses',
    'ask_why'  => $zh ? '用途说明（至少 10 个字）' : 'What it is for (at least 10 characters)',
    'send'     => $zh ? '提交申请' : 'Send the request',
    'reqs'     => $zh ? '我的申请' : 'My requests',
    'when'     => $zh ? '时间' : 'When',
    'state'    => $zh ? '状态' : 'State',
    'withdraw' => $zh ? '撤回' : 'Withdraw',
    'states'   => [
        'pending'   => $zh ? '待审核' : 'pending',
        'approved'  => $zh ? '已批准' : 'approved',
        'rejected'  => $zh ? '已拒绝' : 'rejected',
        'withdrawn' => $zh ? '已撤回' : 'withdrawn',
    ],
];

/* ------------------------------------------------------------------ actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'apply' && $legacyReadonly) {
        $errors[] = $zh
            ? '旧的邮件转发已转为只读：不能再新增地址。请改用新版 Email（可发可收），或联系注册局说明充分理由。'
            : 'The old mail forwarding is read-only now: no new address can be added. Please use the new Email '
              . 'service (it can send and receive), or contact the registry with a good reason.';
    } elseif ($action === 'apply' && !$requestsReady) {
        $errors[] = $zh ? '申请功能还在准备中（数据库尚未升级），请稍后再试或直接联系注册局。'
                        : 'Requesting is not available yet (the database has not been upgraded); '
                          . 'please try later or contact the registry.';
    } elseif ($action === 'apply') {
        $purpose = trim((string)($_POST['purpose'] ?? ''));
        $wanted  = max(1, min(20, (int)($_POST['wanted'] ?? 1)));
        if (mb_strlen($purpose) < 10) {
            $errors[] = $zh ? '用途说明请至少写 10 个字，注册局要据此判断。' : 'Please describe the use in at least 10 characters.';
        } elseif (db_one("SELECT id FROM mail_forward_requests WHERE user_id = ? AND status = 'pending'",
                [(int)$user['id']]) !== null) {
            $errors[] = $zh ? '你已经有一份待审核的申请了。' : 'You already have a request waiting.';
        } else {
            db_query(
                "INSERT INTO mail_forward_requests (user_id, purpose, wanted, status, created_at)
                 VALUES (?, ?, ?, 'pending', NOW())",
                [(int)$user['id'], $purpose, $wanted]
            );
            log_action('forward_request', 'mail', null, 'account #' . (int)$user['id'] . ' asked for '
                . $wanted . ' forwarding address(es)');
            $admin = trim((string)setting('notify_admin_email', ''));
            if ($admin !== '') {
                $previous = lang_switch('en');
                send_mail($admin, 'freedns.my: mail forwarding requested', implode("\n", [
                    t('mail.dear', $admin), '',
                    (string)$user['username'] . ' (' . (string)$user['email'] . ') asks for mail forwarding.',
                    'Addresses wanted: ' . $wanted, '', $purpose, '',
                    rtrim(app_base_url(), '/') . '/admin/forward-requests.php',
                ]));
                lang_switch($previous);
            }
            flash($zh ? '申请已提交。' : 'The request was sent.', 'ok');
            redirect(url('account/forward.php'));
        }
    }

    if ($action === 'withdraw') {
        db_query("UPDATE mail_forward_requests SET status = 'withdrawn', decided_at = NOW()
                  WHERE id = ? AND user_id = ? AND status = 'pending'",
            [(int)($_POST['id'] ?? 0), (int)$user['id']]);
        flash($zh ? '已撤回申请。' : 'The request was withdrawn.', 'ok');
        redirect(url('account/forward.php'));
    }

    if ($action === 'add') {
        // Ownership is recorded as the registrant's address, never as a user id.
        $domain = db_one('SELECT * FROM domains WHERE id = ? AND registrant_email = ?',
            [(int)($_POST['domain_id'] ?? 0), (string)$user['email']]);
        if ($domain === null) {
            $errors[] = $zh ? '请选择你自己的、状态正常的域名。' : 'Choose one of your own active names.';
        } else {
            $result = mailroute_add($domain, $user, (string)($_POST['local'] ?? ''),
                trim((string)($_POST['forward_to'] ?? '')));
            if ($result['ok']) {
                flash($zh ? '转发地址已建立。' : 'The forwarding address is in place.', 'ok');
                redirect(url('account/forward.php'));
            }
            $errors[] = $result['error'];
        }
    }

    if ($action === 'delete') {
        $route = db_one('SELECT * FROM mail_routes WHERE id = ? AND user_id = ?',
            [(int)($_POST['id'] ?? 0), (int)$user['id']]);
        if ($route === null) {
            $errors[] = $zh ? '找不到这条转发地址。' : 'That forwarding address is not there.';
        } else {
            db_query('DELETE FROM mail_routes WHERE id = ? AND user_id = ?',
                [(int)$route['id'], (int)$user['id']]);
            log_action('mailroute_delete', 'mail', (int)$route['id'],
                'account #' . (int)$user['id'] . ' removed ' . (string)$route['local_part'] . ' -> '
                . (string)$route['forward_to']);
            // Told on purpose: a silently deleted address would cut the owner off from his mail.
            if (valid_email_addr((string)$user['email']) && mail_pref_allows((string)$user['email'], 'account')) {
                $previous = lang_switch('en');
                send_mail((string)$user['email'], 'A forwarding address was removed', implode("\n", [
                    t('mail.dear', (string)$user['username']), '',
                    'The forwarding address below was removed from your account:',
                    '  ' . (string)$route['local_part'] . ' -> ' . (string)$route['forward_to'],
                    '',
                    'If this was not you, change your password at once and tell the registry.',
                    '', rtrim(app_base_url(), '/') . '/account/forward.php',
                ]));
                lang_switch($previous);
            }
            flash($zh ? '已删除，并给你发了一封提醒邮件。' : 'Removed, and a notice was mailed to you.', 'ok');
            redirect(url('account/forward.php'));
        }
    }
}

/* ------------------------------------------------------------------ the page */
$routes  = mailroute_table_ready() ? mailroute_of_user((int)$user['id']) : [];
$quota   = $quotaReady
    ? (int)db_value('SELECT forward_quota FROM users WHERE id = ?', [(int)$user['id']], 0)
    : 0;
/*
 * Reading the data is wrapped: a database problem (a column that is missing, a table that has not been
 * imported) must show as a readable message instead of a blank 500 page. The holder sees a short note; an
 * administrator also sees the technical line, which is what makes it possible to fix it.
 */
$loadError = '';
$routes    = [];
$quota     = 0;
$domains   = [];
$requests  = [];
try {
    $routes = mailroute_table_ready() ? mailroute_of_user((int)$user['id']) : [];
    $quota  = $quotaReady
        ? (int)db_value('SELECT forward_quota FROM users WHERE id = ?', [(int)$user['id']], 0)
        : 0;
    // Ownership of a name lives in domains.registrant_email, not in a user id - a holder is known by his address.
    $domains = db_all(
        "SELECT id, name FROM domains WHERE registrant_email = ? AND status = 'active' ORDER BY name ASC",
        [(string)$user['email']]
    );
    $requests = $requestsReady
        ? db_all('SELECT * FROM mail_forward_requests WHERE user_id = ? ORDER BY id DESC LIMIT 30',
            [(int)$user['id']])
        : [];
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    log_action('forward_page_error', 'mail', null, substr($loadError, 0, 250));
}
$requests = $requestsReady
    ? db_all('SELECT * FROM mail_forward_requests WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$user['id']])
    : [];

page_header($T['heading']);
?>
<p><?= h($T['intro']) ?></p>
<?php
// The registry's own wording about this page being retired; both languages live in the settings.
$legacyNotice = trim((string)setting_lang('forward_legacy_note'));
if ($legacyNotice === '') {
    $legacyNotice = $zh
        ? "Mail Forwarding 已全新升级为 Email，并由原来旧版的 Cloudflare 电子邮件路由（仅转发）升级成新版 BillionMail 电子邮件（可发可收）。\n"
          . "目前 Mail Forwarding（Old）仍然可申请，除非理由充分得当，我们将拒绝该申请，并更推荐您使用新版 Mail。\n"
          . "旧的 Mail Forwarding 很快就会转为只读不可新增模式，只读后，我们将为您提供约 3 个月的时间去迁移，迁移期间过后，我们将完全关闭，后续点击 Mail Forwarding（Old）时，将自动跳转到 Email。\n"
          . "新版 Email 结尾是 @用户名.freedns.my，后缀不可更改，除非当您更改用户名后，您可以申请迁移。"
        : "Mail Forwarding has been replaced by Email: the old Cloudflare routing (forwarding only) is now the "
          . "BillionMail service, which can send as well as receive.\n"
          . "Mail Forwarding (Old) can still be requested, but unless the reason is a good one it will be "
          . "refused, and the new Email service is recommended instead.\n"
          . "The old Mail Forwarding will soon become read-only (nothing new can be added). After that you have "
          . "about three months to migrate, and then it closes for good - clicking it afterwards takes you to Email.\n"
          . "A new Email address ends in @yourname.freedns.my and the suffix cannot be changed; changing your "
          . "name is the only way to migrate, and that has to be requested.";
}
?>
<div class="msg info" style="white-space:pre-wrap"><?= h($legacyNotice) ?></div>
<?php if ($legacyReadonly): ?>
<div class="msg error"><?= $zh
    ? '本页已转为<strong>只读</strong>：现有转发地址继续有效，但不能再新增。请改用新版 Email。'
    : 'This page is <strong>read only</strong> now: existing addresses keep working, but nothing new can be '
      . 'added. Please use the new Email service.' ?></div>
<?php endif; ?>
<?= error_list($errors) ?>
<?php if ($loadError !== ''): ?>
<div class="msg error">
  <?= $zh ? '读取数据时出错，页面部分内容可能不完整。' : 'Something went wrong while reading the data; part of this page may be missing.' ?>
  <?php if (function_exists('is_admin') && is_admin()): ?>
  <br><span class="small"><code><?= h($loadError) ?></code></span>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($schemaMissing): ?>
<div class="msg error"><?= $zh
    ? '系统的邮件转发功能正在升级中（数据库尚未升级）。创建与删除转发地址暂不可用，申请功能也暂不可用 —— 请联系注册局处理。'
    : 'The forwarding feature is still being upgraded (the database has not been upgraded yet). Creating and '
      . 'removing addresses is not available for the moment, and neither is requesting - please contact the '
      . 'registry.' ?></div>
<?php endif; ?>

<h2><?= h($T['mine']) ?></h2>
<p class="hint"><?= h(sprintf($T['quota'], (string)$quota, (string)count($routes))) ?></p>
<?php if ($quota <= 0): ?>
<div class="msg error"><?= h($T['nogr']) ?></div>
<?php endif; ?>

<?php if ($routes === []): ?>
<p><?= h($T['none']) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h($T['addr']) ?></th><th><?= h($T['to']) ?></th><th><?= h($T['actions']) ?></th></tr>
  <?php foreach ($routes as $route): ?>
  <tr>
    <td><?= h((string)$route['local_part']) ?>@<?= h((string)($route['name'] ?? '')) ?></td>
    <td><?= h((string)$route['forward_to']) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="<?= h(url('account/forward.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$route['id'] ?>">
        <input type="submit" value="<?= h($T['del']) ?>">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!$legacyReadonly && $quota > 0 && count($routes) < $quota && $domains !== []): ?>
<h2><?= h($T['add']) ?></h2>
<form method="post" action="<?= h(url('account/forward.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="add">
<label for="domain_id"><?= h($T['domain']) ?></label>
<select id="domain_id" name="domain_id">
  <?php foreach ($domains as $domain): ?>
  <option value="<?= (int)$domain['id'] ?>"><?= h((string)$domain['name']) ?></option>
  <?php endforeach; ?>
</select>
<label for="local"><?= h($T['local']) ?></label>
<input type="text" id="local" name="local" maxlength="64" value="info">
<label for="forward_to"><?= h($T['target']) ?></label>
<input type="text" id="forward_to" name="forward_to" maxlength="190" autocomplete="off">
<p class="hint"><?= $zh
    ? '只能转发到你自己的其他邮箱；每封信都会先存档与检查，正常内容才继续转发。'
    : 'Forward to a mailbox of your own; every message is archived and checked before it goes on.' ?></p>
<p><input type="submit" value="<?= h($T['save']) ?>"></p>
</form>
<?php elseif ($quota > 0): ?>
<p class="hint"><?= $zh ? '已用满授权数量，需要更多请提交申请。' : 'You are using all the addresses you were granted; ask for more.' ?></p>
<?php endif; ?>

<h2><?= h($T['ask']) ?></h2>
<form method="post" action="<?= h(url('account/forward.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="apply">
<label for="wanted"><?= h($T['ask_want']) ?></label>
<input type="text" id="wanted" name="wanted" maxlength="2" size="3" value="<?= max(1, $quota) ?>">
<label for="purpose"><?= h($T['ask_why']) ?></label>
<textarea id="purpose" name="purpose" rows="4"></textarea>
<p><input type="submit" value="<?= h($T['send']) ?>"></p>
</form>

<?php if ($requests !== []): ?>
<h3><?= h($T['reqs']) ?></h3>
<table>
  <tr><th><?= h($T['when']) ?></th><th><?= h($T['ask_want']) ?></th><th><?= h($T['state']) ?></th><th></th></tr>
  <?php foreach ($requests as $request): ?>
  <tr>
    <td class="small"><?= h((string)$request['created_at']) ?></td>
    <td><?= (int)$request['wanted'] ?></td>
    <td><?= h($T['states'][(string)$request['status']] ?? (string)$request['status']) ?>
        <?php if ((string)$request['status'] === 'approved'): ?>
        <span class="hint">(<?= (int)$request['quota'] ?>)</span>
        <?php endif; ?>
        <?php if (trim((string)($request['reason'] ?? '')) !== ''): ?>
        <br><span class="small err"><?= h((string)$request['reason']) ?></span>
        <?php endif; ?></td>
    <td class="actions">
      <?php if ((string)$request['status'] === 'pending'): ?>
      <form class="inline" method="post" action="<?= h(url('account/forward.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="withdraw">
        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
        <input type="submit" value="<?= h($T['withdraw']) ?>">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();
