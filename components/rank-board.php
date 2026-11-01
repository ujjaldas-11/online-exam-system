<?php

$rb_total = count($class_leaderboard ?? []);
?>

<aside class="rank-board card" aria-label="Class leaderboard">
    <div class="rank-board__head">
        <h3 class="rank-board__title">
            <span class="material-symbols-outlined">trophy</span> Class Leaderboard
        </h3>
        <span class="badge" title="Total active class members"><?= $rb_total ?> students</span>
    </div>

    <!-- My rank summary -->
    <?php if ($my_rank !== null): ?>
        <div style="padding: 0 16px;">
            <div class="rank-me">
                <div>
                    <span class="rank-me__lbl">Your Position</span>
                    <div class="rank-me__val">
                        #<?= $my_rank ?> <span class="rank-me__of">/ <?= $rb_total ?></span>
                    </div>
                </div>
                <div class="rank-me__avg">
                    <span>Average</span>
                    <strong><?= $my_avg !== null ? number_format($my_avg, 1) . '%' : '—' ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top 5 preview -->
    <ol class="rank-list">
        <?php if (empty($top_leaders)): ?>
            <li class="rank-empty">No exam data available for this class yet.</li>
        <?php else: ?>
            <?php foreach ($top_leaders as $idx => $r): ?>
                <?php
                $pos = $idx + 1;
                $is_me = ($r['id'] === $student_id);
                $medal = match ($pos) {
                    1 => '🥇', 2 => '🥈', 3 => '🥉', default => '#' . $pos,
                };
                ?>
                <li class="rank-row <?= $is_me ? 'rank-row--me' : '' ?>">
                    <span class="rank-pos <?= $pos <= 3 ? 'rank-pos--' . $pos : '' ?>"><?= $medal ?></span>
                    <span class="rank-name" title="<?= e($r['name']) ?>">
                        <?= e($r['name']) ?><?= $is_me ? ' (You)' : '' ?>
                    </span>
                    <span class="rank-avg"><?= number_format((float)$r['avg_score'], 1) ?>%</span>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ol>

    <?php if ($rb_total > 5): ?>
        <div style="padding: 0 16px 16px;">
            <button class="btn btn-outline btn-block btn-sm" id="btn-view-all-ranks" type="button">
                View full class ranks (<?= $rb_total ?>)
            </button>
        </div>
    <?php endif; ?>
</aside>

<!-- Full rank modal -->
<div class="modal-overlay" id="rankModal" role="dialog" aria-modal="true" aria-labelledby="rankModalTitle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="rankModalTitle">Class Leaderboard</h3>
            <button class="modal-close" id="rankModalClose" type="button" aria-label="Close modal">&times;</button>
        </div>

        <div style="margin: 0; padding: 0 1rem;">
            <?php include __DIR__ . '/../components/searchbar.php'; ?>
        </div>

        <div class="rank-modal-scroll">
            <div class="table-wrap">
                <table class="table" id="fullRankTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Roll Number</th>
                            <th>Student</th>
                            <th style="text-align: right;">Average</th>
                            <th style="text-align: right;">Attempts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($class_leaderboard ?? []) as $i => $r): ?>
                            <?php $is_me = ($r['id'] === $student_id); ?>
                            <tr class="<?= $is_me ? 'rank-modal-row--me' : '' ?>">
                                <td><strong><?= $i + 1 ?></strong></td>
                                <td><?= e($r['roll_number']) ?></td>
                                <td><?= e($r['name']) ?><?= $is_me ? ' <span class="badge badge-primary">You</span>' : '' ?></td>
                                <td style="text-align: right;"><strong><?= number_format((float)$r['avg_score'], 1) ?>%</strong></td>
                                <td style="text-align: right;"><?= (int)$r['exams_taken'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('rankModal');
    const openBtn = document.getElementById('btn-view-all-ranks');
    const closeBtn = document.getElementById('rankModalClose');
    if (!modal) return;

    const open = () => { modal.classList.add('active');};
    const close = () => modal.classList.remove('active');

    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

});
</script>
