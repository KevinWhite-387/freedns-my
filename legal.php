<?php
declare(strict_types=1);

/** freedns.my - the terms of service and the privacy note (settings-backed, so they stay editable). */

require __DIR__ . '/inc/bootstrap.php';

$doc  = strtolower(trim((string)($_GET['doc'] ?? 'terms')));
$docs = [
    'terms'   => ['heading' => 'legal.terms',   'text' => 'terms_text'],
    'privacy' => ['heading' => 'legal.privacy', 'text' => 'privacy_text'],
];
if (!isset($docs[$doc])) {
    $doc = 'terms';
}

page_header(t($docs[$doc]['heading']));
?>
<p><?= h(t('legal.intro')) ?></p>

<?= text_block((string)setting_lang($docs[$doc]['text'])) ?>

<p class="hint"><?= h(t('legal.footer')) ?></p>

<p>
<?php if ($doc === 'terms'): ?>
<a href="<?= h(url('legal.php?doc=privacy')) ?>"><?= h(t('legal.privacy')) ?></a>
<?php else: ?>
<a href="<?= h(url('legal.php?doc=terms')) ?>"><?= h(t('legal.terms')) ?></a>
<?php endif; ?>
&middot; <a href="<?= h(url('abuse.php')) ?>"><?= h(t('abuse.heading')) ?></a>
&middot; <a href="<?= h(url('doc.php')) ?>"><?= h(t('nav.doc')) ?></a>
</p>
<?php
page_footer();
