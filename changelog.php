<?php
declare(strict_types=1);

/** freedns.my - the changelog page (English; /zh/changelog.php forces Chinese). */

require __DIR__ . '/inc/bootstrap.php';

page_header(t('changelog.heading'));
?>
<p><?= h(t('changelog.intro')) ?></p>

<?= text_block((string)setting_lang('changelog')) ?>

<p class="hint"><?= h(t('changelog.footer')) ?></p>

<p><a href="<?= h(url('index.php')) ?>"><?= h(t('nav.home')) ?></a> &middot;
<a href="<?= h(url('chat.php')) ?>"><?= h(t('nav.chat')) ?></a> &middot;
<a href="<?= h(url('doc.php')) ?>"><?= h(t('nav.doc')) ?></a></p>
<?php
page_footer();
