<?php
$hof_tiers = [
    'goat'   => ['title' => 'GOAT',   'icon' => '𓃵',  'streak' => 10, 'bg' => 'var(--color-primary)', 'border' => 'var(--color-gold)', 'text' => 'var(--color-gold)'],
    'legend' => ['title' => 'Legend', 'icon' => '🗿',  'streak' => 7,  'bg' => 'var(--color-primary-hover)', 'border' => 'var(--color-primary)', 'text' => 'var(--color-primary-soft)'],
    'knight' => ['title' => 'Knight', 'icon' => '🥷🏻', 'streak' => 3,  'bg' => '#dbeafe', 'border' => 'var(--color-primary)', 'text' => '#1d4ed8'],
];

$hof_groups = array_fill_keys(array_keys($hof_tiers), []);
foreach (($badge_holders ?? []) as $bh) {
    $tier_key = $bh['badge']['tier'] ?? '';
    if (isset($hof_groups[$tier_key])) {
        $hof_groups[$tier_key][] = $bh;
    }
}

// Updated to use full_marks_count to match our new backend logic
$hof_my_count = (int) ($college_stats[$student_id]['full_marks_count'] ?? 0);
?>

<style>
    /* Compact Container */
    .hof-compact {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 14px;
        box-shadow: var(--shadow-sm);
    }

    /* Header */
    .hof-compact__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .hof-compact__title { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--color-dark); }
    .hof-compact__title .material-symbols-outlined { color: var(--color-gold); font-size: 1.3rem; }
    .hof-compact__streak { padding: 3px 10px; border-radius: var(--radius-full); font-size: 0.72rem; font-weight: 600; color: var(--color-text-secondary); background: var(--color-gray-100); border: 1px solid var(--color-border); }

    /* Column List Layout */
    .hof-list { display: flex; flex-direction: column; gap: 8px; }

    /* Individual Colored Cards (fixed short height) */
    .hof-tier-row {
        display: flex; align-items: center; gap: 10px; padding: 8px 12px;
        border-radius: var(--radius-md); border-left: 4px solid;
    }

    /* Fixed the typo: 3 dashes changed to 2 dashes */
    .hof-tier-icon {
        color: var(--color-gold);
        font-size: 2rem;
    }

    .hof-tier-content { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }

    .hof-tier-title { margin: 0 0 4px 0; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 6px; line-height: 1.2; }
    .hof-tier-title small { font-size: 0.66rem; font-weight: 600; opacity: 0.75; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Achievers */
    .hof-holders {
        display: flex; flex-wrap: wrap; gap: 5px;
        max-height: 54px; overflow-y: auto; padding-right: 2px;
        scrollbar-width: thin;
        scrollbar-color: var(--color-text-muted) transparent;
    }
    .hof-holders::-webkit-scrollbar { width: 5px; }
    .hof-holders::-webkit-scrollbar-thumb { background: var(--color-text-muted); border-radius: var(--radius-full); }

    .hof-holder-badge {
        background: var(--color-primary);
        padding: 2px 8px;
        border-radius: var(--radius-sm);
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--color-gold);
        border: 1px solid var(--color-border);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        line-height: 1.5;
    }
    .hof-holder-meta { font-size: 0.66rem; font-weight: 600; opacity: 0.8; }
    .hof-me-tag { color: var(--color-error); font-weight: 800; font-size: 0.7rem; }

    /* Empty State */
    .hof-unclaimed { font-size: 0.76rem; font-weight: 600; font-style: italic; opacity: 0.7; }
</style>

<div class="hof-compact">
    <div class="hof-compact__head">
        <h3 class="hof-compact__title">
            <span class="material-symbols-outlined icon-filled">military_tech</span> Hall of Fame
        </h3>
        <span class="hof-compact__streak" title="Your total full marks">
            Total Full Marks: <b style="color: var(--color-dark); margin-left: 4px;"><?= $hof_my_count ?></b>
        </span>
    </div>

    <div class="hof-list">
        <?php foreach ($hof_tiers as $key => $tier): ?>
            <?php $holders = $hof_groups[$key]; ?>
            <div class="hof-tier-row" style="background: <?= $tier['bg'] ?>; border-left-color: <?= $tier['border'] ?>;">
                
                <div class="hof-tier-icon">
                    <?= $tier['icon'] ?>
                </div>
                
                <div class="hof-tier-content">
                    <h4 class="hof-tier-title" style="color: <?= $tier['text'] ?>;">
                        <?= e($tier['title']) ?> <small>(<?= (int) $tier['streak'] ?>x Full Marks)</small>
                    </h4>

                    <div class="hof-holders">
                        <?php if (empty($holders)): ?>
                            <span class="hof-unclaimed" style="color: <?= $tier['text'] ?>;">No one has earned this yet.</span>
                        <?php else: ?>
                            <?php foreach ($holders as $h): ?>
                                <?php
                                $d = e($h['student_dept'] ?? $h['dept'] ?? 'N/A');
                                $s = e((string) ($h['student_sem'] ?? $h['sem'] ?? ''));
                                $isMe = ((int) $h['id'] === (int) $student_id);
                                ?>
                                <div class="hof-holder-badge">
                                    <?= e($h['name']) ?>
                                    <?php if ($isMe): ?><span class="hof-me-tag">(You)</span><?php endif; ?>
                                    <span class="hof-holder-meta">(<?= $d ?>, Sem <?= $s ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
