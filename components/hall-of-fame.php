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

<style>
    /* ---------- Compact card ---------- */
    .hof {
        --hof-max-h: 225px;

        background: var(--color-surface, #fff);
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: var(--radius-lg, 14px);
        padding: 14px;
        box-shadow: var(--shadow-sm, 0 1px 3px rgba(15, 23, 42, .08));
    }

    /* ---------- Header ---------- */
    .hof__head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        margin-bottom: 10px; padding-bottom: 8px;
        border-bottom: 1px solid #dbe7f7;
    }
    .hof__title {
        display: flex; align-items: center; gap: 6px; margin: 0;
        font-size: 0.95rem; font-weight: 800; color: var(--color-dark, #0b1b3a);
    }
    .hof__title .material-symbols-outlined { font-size: 1.3rem; color: #f5a623; }
    .hof__total {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        background: #e6efff; color: #1e3a8a;
        font-size: 0.72rem; font-weight: 600; white-space: nowrap;
    }
    .hof__total .material-symbols-outlined { font-size: 0.95rem; color: #2563eb; }
    .hof__total b { font-size: 0.85rem; font-weight: 800; color: #0b1b3a; }

    /* ---------- Scrollable section (whole list scrolls, scrollbar hidden) ---------- */
    .hof__list {
        display: flex; flex-direction: column; gap: 8px;
        max-height: var(--hof-max-h);
        overflow-y: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .hof__list::-webkit-scrollbar { display: none; }

    /* ---------- Tier row ---------- */
    .hof-row {
        position: relative; overflow: hidden; isolation: isolate;
        flex: 0 0 auto;
        display: flex; align-items: center; gap: 10px;
        padding: 8px 36px 8px 8px;
        border-radius: var(--radius-md, 10px); border: 1.5px solid;
    }
    .hof-row::before {
        content: ""; position: absolute; z-index: -1;
        top: -20%; bottom: -20%; right: 0; width: 30%;
        transform: skewX(-28deg); transform-origin: top right;
        background: var(--streak);
    }
    .hof-row__deco {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        font-size: 1.4rem !important; color: var(--deco); opacity: .55;
    }

    .hof-row__badge { flex: 0 0 auto; width: 50px; height: 50px; }
    .hof-row__badge img {
        width: 100%; height: 100%; object-fit: contain; display: block;
        filter: drop-shadow(0 2px 5px var(--glow));
        border-radius: 50%;
    }

    .hof-row__body { flex: 1; min-width: 0; }
    .hof-row__head { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
    .hof-row__name { margin: 0; font-size: 0.88rem; font-weight: 800; line-height: 1.2; color: var(--title); }
    .hof-row__pill {
        padding: 1px 8px; border-radius: 999px;
        font-size: 0.62rem; font-weight: 800; letter-spacing: .3px; text-transform: uppercase;
        background: var(--pill-bg); color: var(--pill-text); border: 1px solid var(--pill-border);
    }

    /* ---------- Holders (no inner scroll — they wrap and the row grows) ---------- */
    .hof-holders { display: flex; flex-wrap: wrap; gap: 2px 14px; }
    .hof-holder { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 3px 6px; line-height: 1.3; }
    .hof-holder__name {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 0.78rem; font-weight: 700; color: var(--holder);
    }
    .hof-holder__name .material-symbols-outlined { font-size: 1rem; }
    .hof-holder__you {
        padding: 0 7px; border-radius: 999px;
        font-size: 0.58rem; font-weight: 800; letter-spacing: .3px;
        background: var(--you-bg); color: var(--you-text);
    }
    .hof-holder__meta {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 0.68rem; font-weight: 500; color: var(--meta);
    }
    .hof-holder__meta .material-symbols-outlined { font-size: 0.95rem; }
    .hof-empty { font-size: 0.74rem; font-weight: 600; font-style: italic; color: var(--meta); }

    /* ---------- Tier themes ---------- */
    .hof-row--goat {
        background: linear-gradient(100deg, #1a1408 0%, #0f0c06 55%, #16110a 100%);
        border-color: #f5b82e;
        --streak: linear-gradient(90deg, rgba(245,184,46,.05), rgba(245,184,46,.45));
        --deco: #b8860b; --glow: rgba(245,184,46,.45);
        --title: #f5c542;
        --pill-bg: transparent; --pill-text: #f5c542; --pill-border: #f5b82e;
        --holder: #fff; --meta: #c9b27a;
        --you-bg: #f5c542; --you-text: #3b2a05;
    }
    .hof-row--legend {
        background: linear-gradient(100deg, #0b3fb8 0%, #0a46c9 55%, #0d52dd 100%);
        border-color: #2f7bff;
        --streak: linear-gradient(90deg, rgba(120,190,255,.05), rgba(120,190,255,.35));
        --deco: #7fb4ff; --glow: rgba(100,170,255,.55);
        --title: #fff;
        --pill-bg: #7dd3fc; --pill-text: #0c3a6b; --pill-border: #7dd3fc;
        --holder: #fff; --meta: #a9ccff;
        --you-bg: #fff; --you-text: #0b3fb8;
    }
    .hof-row--knight {
        background: linear-gradient(100deg, #c9effa 0%, #e2f6fc 55%, #d3f1fb 100%);
        border-color: #8fdcf3;
        --streak: linear-gradient(90deg, rgba(103,209,240,.1), rgba(103,209,240,.55));
        --deco: #57bfe0; --glow: rgba(60,170,210,.4);
        --title: #0b3b5a;
        --pill-bg: #a7e4f7; --pill-text: #0b3b5a; --pill-border: #a7e4f7;
        --holder: #0b2e4a; --meta: #4a7390;
        --you-bg: #0b3b5a; --you-text: #fff;
    }
    .hof-row--ace {
        background: linear-gradient(100deg, #2b2f35 0%, #3a3f46 55%, #2f343a 100%);
        border-color: #565d66;
        --streak: linear-gradient(90deg, rgba(170,178,188,.1), rgba(170,178,188,.5));
        --deco: #9aa3ad; --glow: rgba(190,198,208,.35);
        --title: #f1f3f5;
        --pill-bg: #4a5058; --pill-text: #f1f3f5; --pill-border: #5c636c;
        --holder: #ffffff; --meta: #a3acb6;
        --you-bg: #f1f3f5; --you-text: #1f2328;
    }
</style>

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
