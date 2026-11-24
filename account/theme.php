<?php
declare(strict_types=1);

/**
 * freedns.my - console: switching the interface.
 *
 * The classic interface is what everybody gets; the new one is an experiment a holder may turn on for his own
 * session, and only after confirming three times - because the new look is still being built and a half-done
 * page is worse than a plain one.
 *
 *   1. read the warning and tick the box;
 *   2. type the phrase by hand (it differs per direction, so a copied screenshot is not enough);
 *   3. press the confirm button.
 *
 * Going back is deliberately easy: no confirmations, one link, also in the bar the new interface shows.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';


$user = require_user();
$zh   = function_exists('lang') && is_zh();
$errors = [];

$target = strtolower(trim((string)($_POST['target'] ?? $_GET['target'] ?? '')));
if (!in_array($target, ['modern2', 'classic'], true)) {
    $target = 'classic';
}
$phrase = theme_confirm_phrase($target);

/* ------------------------------------------------------------------ going back needs nothing */
if (strtolower(trim((string)($_GET['back'] ?? ''))) === '1') {
    theme_store('classic');
    log_action('theme_switch', 'settings', null, 'back to the classic interface');
    flash($zh ? '已切回旧版界面。' : 'Back on the classic interface.', 'ok');
    redirect(url('account/theme.php'));
}

/* ------------------------------------------------------------------ going forward: three confirmations */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'switch') {
        $step   = max(1, min(3, (int)($_POST['step'] ?? 1)));
        $agree  = !empty($_POST['agree']);
        $typed  = trim((string)($_POST['phrase'] ?? ''));
        $again  = !empty($_POST['again']);

        if ($target === 'classic') {
            theme_store('classic');
            log_action('theme_switch', 'settings', null, 'back to the classic interface');
            flash($zh ? '已切回旧版界面。' : 'Back on the classic interface.', 'ok');
            redirect(url('account/theme.php'));
        }

        if ($step === 1) {
            if (!$agree) {
                $errors[] = $zh ? '第 1 步：请先勾选"我明白"。' : 'Step 1: tick the box first.';
            } else {
                // Move on to the phrase; nothing is stored yet.
                $step = 2;
                $stage = 'phrase';
            }
        }
        if ($errors === [] && $step === 2) {
            if (strtoupper($typed) !== $phrase) {
                $errors[] = $zh
                    ? '第 2 步：请手动输入 ' . $phrase . '（大小写不敏感）'
                    : 'Step 2: type ' . $phrase . ' by hand (case does not matter).';
            } else {
                $step  = 3;
                $stage = 'final';
            }
        }
        if ($errors === [] && $step === 3) {
            if (!$again) {
                $errors[] = $zh ? '第 3 步：请再点一次确认按钮。' : 'Step 3: press the confirm button once more.';
            } else {
                theme_store('modern2');
                log_action('theme_switch', 'settings', null, 'switched to the new interface');
                flash($zh ? '新版界面已开启。页面顶部随时可以一键回到旧版。'
                          : 'The new interface is on. The bar at the top takes you back at any time.', 'ok');
                redirect(url('account/index.php'));
            }
        }
    }
}

$stage = $stage ?? 'agree';      // agree -> phrase -> final
$active = theme_active();

page_header($zh ? '界面切换' : 'Interface');
?>
<p><?= $zh
    ? '本站默认使用<strong>旧版界面</strong>。新版界面（控制台风格，仍在开发中）可以在这里开启，但需要<strong>三次确认</strong>，而且只对你自己这次登录的会话有效 —— 退出登录后会自动回到旧版。'
    : 'This site uses the <strong>classic interface</strong> by default. The new one (the control-panel look, still being built) can be turned on here, but it takes <strong>three confirmations</strong> and applies only to your own session - signing out brings the classic look back.' ?></p>

<p class="hint"><?= $zh ? '当前：' : 'Right now: ' ?><strong><?= h(theme_choices()[$active][$zh ? 'zh' : 'en']) ?></strong></p>

<?php if ($active === 'modern2'): ?>
<div class="msg ok">
  <?= $zh ? '你正在使用新版界面。' : 'You are on the new interface.' ?>
  <a href="<?= h(url('account/theme.php?back=1')) ?>"><?= $zh ? '一键回到旧版' : 'back to the classic look' ?></a>
</div>
<?php endif; ?>

<?= error_list($errors) ?>

<h2><?= $zh ? '切换到新版界面' : 'Switch to the new interface' ?></h2>

<?php if ($stage === 'agree'): ?>
<div class="msg error">
  <strong><?= $zh ? '请注意：' : 'Please read:' ?></strong>
  <ul>
    <li><strong><?= $zh ? '新版界面 2.0' : 'New interface 2.0' ?></strong><?= $zh
        ? '：仍在实验阶段，样式可能继续调整；发现问题欢迎上报。'
        : ': still experimental, and the layout may keep changing. Please report what looks wrong.being built.' ?></li>
    <li><?= $zh ? '新版界面仍在开发中，部分页面可能显示不完整或样式错位。'
                : 'The new interface is still being built; some pages may look incomplete or misaligned.' ?></li>
    <li><?= $zh ? '可能存在风险，请谨慎使用；重要操作请先切回旧版再执行。'
                : 'It may be risky - use it with care, and switch back before doing anything important.' ?></li>
    <li><?= $zh ? '出现问题请及时上报：把页面地址、你做了什么、看到什么写清楚，发到页脚的邮箱或群组。'
                : 'Please report problems: the page address, what you did and what you saw - by mail or in the groups in the footer.' ?></li>
    <li><?= $zh ? '实验性功能不保证长期保留，我们保留随时停止它的权利。'
                : 'Experimental features are not guaranteed to stay; the registry may stop offering this at any time.' ?></li>
    <li><?= $zh ? '旧版界面仍是官方界面，遇到问题请切回旧版再反馈。'
                : 'The classic interface remains the official one; switch back before reporting a problem.' ?></li>
    <li><?= $zh ? '这个选择只对本次登录会话有效，退出登录即恢复旧版。'
                : 'The choice lasts for this signed-in session only; signing out restores the classic look.' ?></li>
    <li><?= $zh ? '访客访问时始终是旧版界面，不受你的选择影响。'
                : 'Visitors always see the classic interface; your choice does not affect them.' ?></li>
  </ul>
  <p><a href="<?= h(url('experimental.php')) ?>"><?= $zh
      ? '实验性功能的完整声明' : 'The full statement about experimental features' ?></a></p>
</div>
<form method="post" action="<?= h(url('account/theme.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="switch">
  <input type="hidden" name="step" value="1">
  <input type="hidden" name="target" value="modern2">
  <p>
    <input type="checkbox" id="agree" name="agree" value="1">
    <label class="inline" for="agree"><?= $zh
        ? '我明白这是试验界面，可能出现显示问题' : 'I understand this is an experimental interface and may look broken' ?></label>
  </p>
  <p class="hint"><?= $zh ? '第 1 步，共 3 步。' : 'Step 1 of 3.' ?></p>
  <p><input type="submit" value="<?= $zh ? '下一步' : 'Next' ?>"></p>
</form>

<?php elseif ($stage === 'phrase'): ?>
<form method="post" action="<?= h(url('account/theme.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="switch">
  <input type="hidden" name="step" value="2">
  <input type="hidden" name="target" value="modern2">
  <label for="phrase"><?= $zh ? '请手动输入下面这行字（区分大小写不重要）' : 'Type this line by hand (case does not matter)' ?></label>
  <p><code><?= h($phrase) ?></code></p>
  <input type="text" id="phrase" name="phrase" maxlength="40" autocomplete="off" autocapitalize="off" spellcheck="false">
  <p class="hint"><?= $zh ? '第 2 步，共 3 步。' : 'Step 2 of 3.' ?></p>
  <p><input type="submit" value="<?= $zh ? '下一步' : 'Next' ?>"></p>
</form>

<?php else: ?>
<form method="post" action="<?= h(url('account/theme.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="switch">
  <input type="hidden" name="step" value="3">
  <input type="hidden" name="target" value="modern2">
  <p><?= $zh ? '最后一步：确认开启新版界面。' : 'Last step: confirm turning the new interface on.' ?></p>
  <p>
    <input type="checkbox" id="again" name="again" value="1">
    <label class="inline" for="again"><?= $zh ? '确认开启' : 'Yes, turn it on' ?></label>
  </p>
  <p class="hint"><?= $zh ? '第 3 步，共 3 步。' : 'Step 3 of 3.' ?></p>
  <p><input type="submit" value="<?= $zh ? '确认切换' : 'Switch it on' ?>"></p>
</form>
<?php endif; ?>

<h2><?= $zh ? '回到旧版界面' : 'Back to the classic interface' ?></h2>
<p><?= $zh ? '不需要任何确认，点一下即可：' : 'No confirmations at all - one click:' ?></p>
<p><a href="<?= h(url('account/theme.php?back=1')) ?>"><?= $zh ? '切回旧版界面' : 'Back to the classic interface' ?></a></p>
<?php
page_footer();
