<?php
/**
 * Rank Board component (class ranking card + "View all" modal)
 *
 * Expects from the including page:
 *   $top_10                      array       top 10 of the class (rank, id, name, average)
 *   $class_leaderboard           array       full class ranking (rank, id, name, roll, exams_taken, average)
 *   $current_student_rank_data   array|null
 *   $student_id                  int
 */
$rb_total = count($class_leaderboard ?? []);
?>

<style>
    /* ---------- Rank board card ---------- */
    .rank-board.card { padding: 0; margin: 0; overflow: hidden; }
    .rank-board__head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 16px 16px 12px;
    }
    .rank-board__title {
        display: flex; align-items: center; gap: 8px;
        margin: 0; font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; color: var(--color-dark);
    }
    .rank-board__title .material-symbols-outlined { color: var(--color-gold); }

    .rank-me {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        margin: 0 16px 12px; padding: 10px 12px;
        background: var(--color-primary); color: var(--color-surface);
        border-radius: var(--radius-md); border-bottom: 3px solid var(--color-gold);
    }
    .rank-me__lbl { font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; }
    .rank-me__val { font-size: 1.35rem; font-weight: 800; line-height: 1.1; color: var(--color-gold-hover); }
    .rank-me__of { font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); }
    .rank-me__avg { text-align: right; font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; }
    .rank-me__avg strong { display: block; font-size: 1.05rem; color: var(--color-surface); }

    /* ---------- Rank list ---------- */
    .rank-list { list-style: none; margin: 0; padding: 0 8px 12px; }
    .rank-row {
        display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: center; gap: 8px;
        padding: 8px; border-radius: var(--radius-md);
        font-size: 0.88rem; border-left: 3px solid transparent;
        transition: background-color 0.15s ease;
    }
    .rank-row:hover { background: var(--color-bg); }
    .rank-row--me { background: var(--color-primary-soft); border-left-color: var(--color-primary-light); font-weight: 600; }
    .rank-row--me:hover { background: var(--color-primary-soft); }
    .rank-pos { text-align: center; font-weight: 700; color: var(--color-text-secondary); font-size: 0.85rem; }
    .rank-pos--1, .rank-pos--2, .rank-pos--3 { font-size: 1.15rem; }
    .rank-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--color-text); }
    .rank-avg { font-weight: 700; color: var(--color-primary); font-variant-numeric: tabular-nums; }
    .rank-gap { text-align: center; color: var(--color-text-muted); letter-spacing: 3px; line-height: 1; padding: 2px 0; }
    .rank-empty { padding: 20px 16px 24px; text-align: center; color: var(--color-text-secondary); font-size: 0.88rem; }

    /* ---------- Modal extras ---------- */
    .rank-modal-search { padding: 14px 20px; background: var(--color-bg); border-bottom: 1px solid var(--color-border); }
    .rank-modal-scroll { max-height: 55vh; overflow-y: auto; }
    .rank-modal-scroll table { margin: 0; }
    .rank-modal-scroll thead th { position: sticky; top: 0; z-index: 1; }
    .rank-modal-row--me td { background: var(--color-primary-soft); font-weight: 600; }
</style>

<aside class="rank-board card" aria-label="Class leaderboard">
    <div class="rank-board__head">
        <h3 class="rank-board__title">
            <span class="material-symbols-outlined">trophy</span> Class rank
        </h3>
        <button type="button" id="btn-view-all-ranks" class="btn btn-outline btn-sm">View all</button>
    </div>

    <?php if (!empty($current_student_rank_data)): ?>
        <div class="rank-me">
            <div>
                <div class="rank-me__lbl">Your rank</div>
                <div class="rank-me__val">#<?= (int) $current_student_rank_data['rank'] ?> <span class="rank-me__of">of <?= $rb_total ?></span></div>
            </div>
            <div class="rank-me__avg">
                Average
                <strong><?= sprintf('%.1f', $current_student_rank_data['average']) ?>%</strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($top_10)): ?>
        <div class="rank-empty">No exams completed yet.</div>
    <?php else: ?>
        <ol class="rank-list">
            <?php foreach ($top_10 as $t): ?>
                <?php $isMe = ((int) $t['id'] === (int) $student_id); ?>
                <li class="rank-row<?= $isMe ? ' rank-row--me' : '' ?>">
                    <span class="rank-pos rank-pos--<?= (int) $t['rank'] ?>">
                        <?= $t['rank'] == 1 ? '🥇' : ($t['rank'] == 2 ? '🥈' : ($t['rank'] == 3 ? '🥉' : '#' . (int) $t['rank'])) ?>
                    </span>
                    <span class="rank-name"><?= e($t['name']) ?></span>
                    <span class="rank-avg"><?= sprintf('%.1f', $t['average']) ?>%</span>
                </li>
            <?php endforeach; ?>

            <?php if (!empty($current_student_rank_data) && $current_student_rank_data['rank'] > 10): ?>
                <li class="rank-gap" aria-hidden="true">&bull;&bull;&bull;</li>
                <li class="rank-row rank-row--me">
                    <span class="rank-pos">#<?= (int) $current_student_rank_data['rank'] ?></span>
                    <span class="rank-name"><?= e($current_student_rank_data['name']) ?></span>
                    <span class="rank-avg"><?= sprintf('%.1f', $current_student_rank_data['average']) ?>%</span>
                </li>
            <?php endif; ?>
        </ol>
    <?php endif; ?>
</aside>

<!-- Modal: full class leaderboard (uses admin-modal-* components) -->
<div id="rankModal" class="admin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="rankModalTitle">
    <div class="admin-modal-card admin-modal-card-wide">
        <div class="admin-modal-header">
            <h3 id="rankModalTitle"><span class="material-symbols-outlined">leaderboard</span> Full class leaderboard</h3>
            <button type="button" class="admin-modal-close" id="rankModalClose" aria-label="Close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="rank-modal-search">
                <?php include __DIR__ . '/../components/searchbar.php'; ?>
        </div>
        <div class="rank-modal-scroll">
            <table id="fullRankTable">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Roll no.</th>
                        <th>Exams</th>
                        <th>Avg score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($class_leaderboard as $s): ?>
                        <?php $isMe = ((int) $s['id'] === (int) $student_id); ?>
                        <tr<?= $isMe ? ' class="rank-modal-row--me"' : '' ?>>
                            <td><strong>#<?= (int) $s['rank'] ?></strong></td>
                            <td class="search-name"><?= e($s['name']) ?> <?= $isMe ? '<span class="badge badge-info">You</span>' : '' ?></td>
                            <td class="search-roll"><?= e((string) $s['roll']) ?></td>
                            <td><?= (int) $s['exams_taken'] ?></td>
                            <td><strong><?= sprintf('%.1f', $s['average']) ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('rankModal');
    const openBtn = document.getElementById('btn-view-all-ranks');
    const closeBtn = document.getElementById('rankModalClose');
    const searchInput = document.getElementById('rankSearchInput');
    const rows = document.querySelectorAll('#fullRankTable tbody tr');
    if (!modal) return;

    const open = () => { modal.classList.add('active'); if (searchInput) searchInput.focus(); };
    const close = () => modal.classList.remove('active');

    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

});
</script>