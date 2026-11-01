<?php
/*
 * Hall of Fame — compact version of the redesigned look.
 * Replace the 'img' URLs below with your own badge images (transparent PNG works best).
 */
$hof_img_base = '../assets/badges/';

$hof_tiers = [
    'goat'   => ['title' => 'GOAT',   'streak' => 'all', 'img' => 'goat.jpg',   'deco' => 'workspace_premium'],
    'legend' => ['title' => 'Legend', 'streak' => '7',  'img' => 'legend.jpg', 'deco' => 'workspace_premium'],
    'knight' => ['title' => 'Knight', 'streak' => '3',  'img' => 'knight.jpg', 'deco' => 'shield'],
    'ace' => ['title' => 'ace', 'streak' => '1', 'img' => 'ace.png', 'deco' => 'star' ]
];

$hof_groups = array_fill_keys(array_keys($hof_tiers), []);
foreach (($badge_holders ?? []) as $bh) {
    $tier_key = $bh['badge']['tier'] ?? '';
    if (isset($hof_groups[$tier_key])) {
        $hof_groups[$tier_key][] = $bh;
    }
}

$hof_my_count = (int) ($college_stats[$student_id]['full_marks_count'] ?? 0);
?>

<div class="hof">
    <div class="hof__head">
        <h3 class="hof__title">
            <span class="material-symbols-outlined icon-filled">emoji_events</span> Hall of Fame
        </h3>
        <span class="hof__total" title="Your total full marks">
            <span class="material-symbols-outlined icon-filled">star</span>
            <b><?= $hof_my_count ?></b> Total Full Marks
        </span>
    </div>

    <div class="hof__list">
        <?php foreach ($hof_tiers as $key => $tier): ?>
            <?php $holders = $hof_groups[$key]; ?>
            <div class="hof-row hof-row--<?= e($key) ?>">

                <div class="hof-row__badge">
                    <img src="<?=  e($hof_img_base . $tier['img']) ?>" alt="<?= e($tier['title']) ?> badge" loading="lazy">
                </div>

                <div class="hof-row__body">
                    <div class="hof-row__head">
                        <h4 class="hof-row__name"><?= e($tier['title']) ?></h4>
                        <span class="hof-row__pill"><?= e($tier['streak']) ?> × Full Marks</span>
                    </div>

                    <div class="hof-holders">
                        <?php if (empty($holders)): ?>
                            <span class="hof-empty">No one has earned this yet.</span>
                        <?php else: ?>
                            <?php foreach ($holders as $h): ?>
                                <?php
                                $d = e($h['student_dept'] ?? $h['dept'] ?? 'N/A');
                                $s = e((string) ($h['student_sem'] ?? $h['sem'] ?? ''));
                                $isMe = ((int) $h['id'] === (int) $student_id);
                                ?>
                                <div class="hof-holder">
                                    <span class="hof-holder__name">
                                        <span class="material-symbols-outlined icon-filled">person</span>
                                        <?= e($h['name']) ?>
                                        <?php if ($isMe): ?><span class="hof-holder__you">YOU</span><?php endif; ?>
                                    </span>
                                    <span class="hof-holder__meta">
                                        <span class="material-symbols-outlined icon-filled">school</span>
                                        <?= $d ?>, Sem <?= $s ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <span class="material-symbols-outlined icon-filled hof-row__deco"><?= e($tier['deco']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
