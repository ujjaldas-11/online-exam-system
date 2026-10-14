<?php
/**
 * Hall of Fame (Ultra-Compact Card List)
 * Displays the 3 college-wide achievement badges stacked vertically with distinct colors.
 */
$hof_tiers = [
    'goat'   => ['title' => 'GOAT',   'icon' => '🐐', 'streak' => 10, 'bg' => '#fef3c7', 'border' => '#fbbf24', 'text' => '#b45309'],
    'legend' => ['title' => 'Legend', 'icon' => '👑', 'streak' => 7,  'bg' => '#f3e8ff', 'border' => '#a78bfa', 'text' => '#6b21a8'],
    'knight' => ['title' => 'Knight', 'icon' => '🛡️', 'streak' => 3,  'bg' => '#dbeafe', 'border' => '#60a5fa', 'text' => '#1d4ed8'],
];

$hof_groups = array_fill_keys(array_keys($hof_tiers), []);
foreach (($badge_holders ?? []) as $bh) {
    $tier_key = $bh['badge']['tier'] ?? '';
    if (isset($hof_groups[$tier_key])) {
        $hof_groups[$tier_key][] = $bh;
    }
}

$hof_my_streak = (int) ($college_stats[$student_id]['current_streak'] ?? 0);
?>

<style>
    /* Compact Container */
    .hof-compact { 
        background: #fff; 
        border: 1px solid var(--color-border); 
        border-radius: 16px; 
        padding: 18px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        margin-bottom: 24px;
    }
    
    /* Header */
    .hof-compact__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .hof-compact__title { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--color-dark); }
    .hof-compact__title .material-symbols-outlined { color: #ca8a04; font-size: 1.4rem; }
    .hof-compact__streak { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; }
    
    /* Column List Layout */
    .hof-list { display: flex; flex-direction: column; gap: 10px; }
    
    /* Individual Colored Cards */
    .hof-tier-row { 
        display: flex; gap: 14px; padding: 12px 14px; 
        border-radius: 12px; border-left: 5px solid;
    }
    
    .hof-tier-icon { font-size: 2rem; display: flex; align-items: center; justify-content: center; }
    
    .hof-tier-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    
    .hof-tier-title { margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 800; display: flex; align-items: center; gap: 6px; }
    .hof-tier-title small { font-size: 0.7rem; font-weight: 600; opacity: 0.75; text-transform: uppercase; letter-spacing: 0.5px; }
    
    /* Achievers Tags */
    .hof-holders { display: flex; flex-wrap: wrap; gap: 6px; }
    
    .hof-holder-badge { 
        background: rgba(255, 255, 255, 0.7); 
        padding: 4px 10px; 
        border-radius: 8px; 
        font-size: 0.85rem; 
        font-weight: 700; 
        color: var(--color-dark);
        border: 1px solid rgba(0,0,0,0.06); 
        display: inline-flex; 
        align-items: center; 
        gap: 6px;
    }
    .hof-holder-meta { font-size: 0.7rem; font-weight: 600; opacity: 0.6; }
    .hof-me-tag { color: #dc2626; font-weight: 800; font-size: 0.75rem; }
    
    /* Empty State */
    .hof-unclaimed { font-size: 0.8rem; font-weight: 600; font-style: italic; opacity: 0.6; }
</style>

<div class="hof-compact">
    <div class="hof-compact__head">
        <h3 class="hof-compact__title">
            <span class="material-symbols-outlined icon-filled">military_tech</span> Hall of Fame
        </h3>
        <span class="hof-compact__streak" title="Your current 100% score streak">
            Your streak: <b style="color:var(--color-dark); margin-left:4px; font-size:0.85rem;"><?= $hof_my_streak ?></b>
        </span>
    </div>
    
    <div class="hof-list">
        <?php foreach ($hof_tiers as $key => $tier): ?>
            <?php $holders = $hof_groups[$key]; ?>
            <!-- Dynamic Colored Card -->
            <div class="hof-tier-row" style="background: <?= $tier['bg'] ?>; border-left-color: <?= $tier['border'] ?>;">
                <div class="hof-tier-icon"><?= $tier['icon'] ?></div>
                <div class="hof-tier-content">
                    <h4 class="hof-tier-title" style="color: <?= $tier['text'] ?>;">
                        <?= $tier['title'] ?> <small>(<?= $tier['streak'] ?> in a row)</small>
                    </h4>
                    
                    <div class="hof-holders">
                        <?php if (empty($holders)): ?>
                            <span class="hof-unclaimed" style="color: <?= $tier['text'] ?>;">No one has earned this yet.</span>
                        <?php else: ?>
                            <?php foreach ($holders as $h): ?>
                                <?php 
                                    // FIX: Check for 'student_dept' and 'student_sem' first!
                                    $d = e($h['student_dept'] ?? $h['dept'] ?? 'N/A');
                                    $s = e((string)($h['student_sem'] ?? $h['sem'] ?? ''));
                                    $isMe = ((int)$h['id'] === (int)$student_id);
                                ?>
                                <div class="hof-holder-badge">
                                    <?= e($h['name']) ?> 
                                    <?php if($isMe): ?><span class="hof-me-tag">(You)</span><?php endif; ?>
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
