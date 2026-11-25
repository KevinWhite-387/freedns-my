<?php
declare(strict_types=1);

/**
 * freedns.my - back office: reviews.
 *
 * A review that looked like advertising, or mentioned gambling, adult content or drugs, was held back when it
 * was written. This page is where it is decided: publish it, refuse it with a reason, or delete it altogether.
 * The writer sees the decision on /reviews.php - his own review is shown to him whatever its state, and a
 * refusal carries the reason.
 *
 * The average on the public page counts published reviews only, so anything still pending here does not affect
 * the score.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
评价本站
');
require_once dirname(__DIR__) . '/inc/reviews.php';

$admin  = require_admin();
require_owner('Reviews');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));
    $row    = $id > 0 ? db_one('SELECT * FROM reviews WHERE id = ?', [$id]) : null;

    if ($row === null) {
        $errors[] = 'That review does not exist.';
    } elseif ($action === 'review_publish') {
        db_query("UPDATE reviews SET status = 'published', note = NULL, decided_by = ?, updated_at = NOW()
                  WHERE id = ?", [(string)$admin['username'], $id]);
        log_action('review_published', 'user', (int)$row['user_id'], 'review #' . $id . ' published');
        flash('Review #' . $id . ' is published.', 'ok');
        redirect('reviews.php');
    } elseif ($action === 'review_refuse') {
        if ($note === '') {
            $errors[] = 'Give a reason when refusing a review - the writer is shown it.';
        } else {
            db_query("UPDATE reviews SET status = 'rejected', note = ?, decided_by = ?, updated_at = NOW()
                      WHERE id = ?", [substr($note, 0, 255), (string)$admin['username'], $id]);
            log_action('review_refused', 'user', (int)$row['user_id'], 'review #' . $id . ' refused: ' . $note);
            flash('Review #' . $id . ' was refused, with the reason.', 'ok');
            redirect('reviews.php');
        }
    } elseif ($action === 'review_delete') {
        db_query('DELETE FROM reviews WHERE id = ?', [$id]);
        log_action('review_deleted', 'user', (int)$row['user_id'], 'review #' . $id . ' deleted');
        flash('Review #' . $id . ' was deleted.', 'ok');
        redirect('reviews.php');
    }
}

$pending   = [];
$published = [];
$rejected  = [];
if (reviews_ready()) {
    try {
        $pending   = db_all("SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON u.id = r.user_id
                             WHERE r.status = 'pending' ORDER BY r.id ASC");
        $published = db_all("SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON u.id = r.user_id
                             WHERE r.status = 'published' ORDER BY r.updated_at DESC LIMIT 100");
        $rejected  = db_all("SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON u.id = r.user_id
                             WHERE r.status = 'rejected' ORDER BY r.updated_at DESC LIMIT 50");
    } catch (Throwable $e) {
        $errors[] = 'The reviews table could not be read: ' . $e->getMessage();
    }
}

$stats = review_stats();

page_header('Reviews');
?>
<p>
The public page is <a href="<?= h(url('reviews.php')) ?>">/reviews.php</a> (Chinese:
<code>/zh/reviews.php</code>). One vote per account; the average counts published reviews only.
</p>

<div class="msg <?= (int)$stats['count'] === 0 ? 'info' : 'ok' ?>">
  Published: <strong><?= (int)$stats['count'] ?></strong> &middot; average
  <strong><?= h(number_format((float)$stats['average'], 2)) ?></strong> / 5
  <?= h(review_stars((int)round((float)$stats['average']))) ?>
  &middot; waiting here: <strong><?= count($pending) ?></strong>
</div>
<?= error_list($errors) ?>

<h2>Waiting for a look (<?= count($pending) ?>)</h2>
<?php if ($pending === []): ?>
<p class="hint">Nothing is waiting. A review only arrives here when it looked like advertising, or mentioned
gambling, adult content or drugs.</p>
<?php else: ?>
<table>
  <?php foreach ($pending as $row): ?>
  <tr>
    <td>#<?= (int)$row['id'] ?></td>
    <td><code><?= h(review_stars((int)$row['stars'])) ?></code></td>
    <td class="small"><?= h(review_author($row)) ?><br><?= h((string)$row['updated_at']) ?></td>
    <td class="small"><?= h(trim((string)($row['body'] ?? '')) === ''
        ? '(a score, no words)' : (string)$row['body']) ?>
      <?php if (trim((string)($row['note'] ?? '')) !== ''): ?>
      <br><span class="hint">held back because: <?= h((string)$row['note']) ?></span>
      <?php endif; ?></td>
    <td>
      <form class="inline" method="post" action="reviews.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="text" name="note" maxlength="190" size="16" placeholder="reason (for a refusal)">
        <input type="submit" name="action" value="review_publish">
        <input type="submit" name="action" value="review_refuse">
        <input type="submit" name="action" value="review_delete">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><strong>review_publish</strong> = it goes on the page and into the average &middot;
<strong>review_refuse</strong> needs a reason: the writer sees it, and the review stays visible to him only
&middot; <strong>review_delete</strong> removes it for good.</p>
<?php endif; ?>

<h2>Published (<?= count($published) ?>)</h2>
<?php if ($published === []): ?>
<p class="hint">Nothing published yet.</p>
<?php else: ?>
<table>
  <?php foreach ($published as $row): ?>
  <tr>
    <td>#<?= (int)$row['id'] ?></td>
    <td><code><?= h(review_stars((int)$row['stars'])) ?></code></td>
    <td class="small"><?= h(review_author($row)) ?><br><?= h((string)$row['updated_at']) ?></td>
    <td class="small"><?= h(trim((string)($row['body'] ?? '')) === ''
        ? '(a score, no words)' : (string)$row['body']) ?></td>
    <td>
      <form class="inline" method="post" action="reviews.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="text" name="note" maxlength="190" size="16" placeholder="reason">
        <input type="submit" name="action" value="review_refuse">
        <input type="submit" name="action" value="review_delete">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">An unflattering review is not removed for being unflattering: refuse or delete only advertising,
unlawful content and personal attacks.</p>
<?php endif; ?>

<?php if ($rejected !== []): ?>
<h2>Refused (<?= count($rejected) ?>)</h2>
<table>
  <?php foreach ($rejected as $row): ?>
  <tr>
    <td>#<?= (int)$row['id'] ?></td>
    <td><code><?= h(review_stars((int)$row['stars'])) ?></code></td>
    <td class="small"><?= h(review_author($row)) ?></td>
    <td class="small"><?= h((string)($row['note'] ?? '')) ?></td>
    <td>
      <form class="inline" method="post" action="reviews.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" name="action" value="review_publish">
        <input type="submit" name="action" value="review_delete">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!reviews_ready()): ?>
<div class="msg error">The <code>reviews</code> table is not there yet. Import <code>upgrade-all-in-one.sql</code>
or create it with the statement from the delivery notes.</div>
<?php endif; ?>
<?php
page_footer();
