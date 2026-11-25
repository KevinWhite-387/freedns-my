<?php
declare(strict_types=1);

/**
 * freedns.my - policy and documentation, both editable from the administration area.
 */

require __DIR__ . '/inc/bootstrap.php';

$zones    = zone_list();
$example  = default_zone();
$exampleZ = $example === null ? 'example.org' : (string)$example['name'];

page_header(t('doc.heading'));
?>
<p><?= h(t('doc.intro')) ?></p>

<h2><?= h(t('doc.policy')) ?></h2>
<?= text_block((string)setting_lang('policy')) ?>

<h2><?= h(t('doc.documentation')) ?></h2>
<?= text_block((string)setting_lang('documentation')) ?>

<h2><?= h(t('doc.quickref')) ?></h2>
<table>
  <tr>
    <th><?= h(t('doc.zones_offered')) ?></th>
    <td>
      <?php if ($zones === []): ?>
      <?= h(t('doc.none_configured')) ?>
      <?php else: ?>
      <?php
      $parts = [];
      foreach ($zones as $zone) {
          $parts[] = '<code>' . h((string)$zone['name']) . '</code>'
              . ((int)$zone['is_open'] === 1 ? '' : h(t('doc.closed')));
      }
      echo implode(', ', $parts);
      ?>
      <?php endif; ?>
    </td>
  </tr>
  <tr><th><?= h(t('doc.records_required')) ?></th><td><?= t('doc.records_value') ?></td></tr>
  <tr>
    <th><?= h(t('doc.record_types')) ?></th>
    <td><?= t('doc.record_types_v', max_records_per_domain()) ?></td>
  </tr>
  <tr><th><?= h(t('doc.subnames')) ?></th><td><?= t('doc.subnames_v', h($exampleZ)) ?></td></tr>
  <tr>
    <th><?= h(t('doc.changes')) ?></th>
    <td><?= t('doc.changes_v', '<a href="' . h(url('manage.php')) . '">' . h(t('nav.manage')) . '</a>') ?></td>
  </tr>
  <tr><th><?= h(t('doc.nameservers')) ?></th><td><?= t('doc.nameservers_v') ?></td></tr>
  <tr><th><?= h(t('doc.cost')) ?></th><td><?= h(t('doc.cost_v')) ?></td></tr>
</table>

<h2><?= h(t('doc.example')) ?></h2>
<p><?= t('doc.example_intro', h($exampleZ)) ?></p>
<pre class="wrap code-block">example.<?= h($exampleZ) ?>.        IN A      203.0.113.10
www.example.<?= h($exampleZ) ?>.    IN CNAME  example.net.
example.<?= h($exampleZ) ?>.        IN TXT    "v=spf1 -all"</pre>
<p class="small"><?= t('doc.docrange') ?></p>
<p><?= h(t('doc.tell')) ?></p>

<h2><?= h(t('doc.why')) ?></h2>
<p><?= t('doc.why_1') ?></p>
<p><?= t('doc.why_2') ?></p>
<?php
page_footer();
