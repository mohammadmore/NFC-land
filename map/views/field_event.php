<?php
/**
 * NFC Event Tag Scoring - Event zone map & field task lists (نقشه بخشی رویداد)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'field';
$extra_scripts = ['/assets/field.js'];
$total = count($tags);
$base = '/admin/field/event/' . (int)$event['id'];
$filters = [
    'all' => 'همه',
    'todo-install' => 'نصب‌نشده',
    'todo-verify' => 'تأییدنشده',
    'todo-collect' => 'جمع‌آوری‌نشده',
    'missing' => 'گم‌شده',
];
if ($is_admin && (int)$event['is_active'] === 1) {
    $filters['stale'] = '⚠️ مشکوک (' . fa_num(count($stale)) . ')';
}
$match = function ($t) use ($filter, $stale) {
    switch ($filter) {
        case 'todo-install': return $t['status'] === 'new';
        case 'todo-verify': return $t['status'] === 'placed';
        case 'todo-collect': return in_array($t['status'], ['placed', 'verified'], true);
        case 'missing': return $t['status'] === 'missing';
        case 'stale': return isset($stale[$t['id']]);
        default: return true;
    }
};

// گروه‌بندی: طبقه ← بخش ← تگ‌ها؛ تگ‌های بدون محل جداگانه
$groups = [];
$unplaced = [];
foreach ($tags as $t) {
    if (!$match($t)) {
        continue;
    }
    if (empty($t['zone_id'])) {
        $unplaced[] = $t;
        continue;
    }
    $groups[$t['floor_id']]['name'] = $t['floor_name'];
    $groups[$t['floor_id']]['zones'][$t['zone_id']]['name'] = $t['zone_name'];
    $groups[$t['floor_id']]['zones'][$t['zone_id']]['tags'][] = $t;
}
$pct = function ($n) use ($total) {
    return $total > 0 ? round($n * 100 / $total) : 0;
};
$done = $counts['placed'] + $counts['verified'] + $counts['collected'] + $counts['missing'];
?>

<div class="event-mgmt-header">
    <div class="breadcrumb">
        <a href="<?= e(path_url('/admin/field')) ?>">کار میدانی</a>
        <span class="separator">/</span>
        <span class="current"><?= e($event['name']) ?></span>
        <?php if ($is_admin): ?>
            <span class="separator">·</span>
            <a href="<?= e(path_url('/admin/events/' . (int)$event['id'])) ?>">مدیریت رویداد</a>
        <?php endif; ?>
    </div>
    <h1 class="page-title">🗺️ نقشه و کار میدانی</h1>
    <p class="text-muted"><?= $event['venue_name'] ? '📍 ' . e($event['venue_name']) : '<span class="text-danger">محل برگزاری تعیین نشده است.</span>' ?> · <?= fa_num($total) ?> تگ</p>
</div>

<!-- خلاصه پیشرفت -->
<div class="card mb-3">
    <div class="card-body">
        <div class="field-progress" role="img" aria-label="پیشرفت وضعیت تگ‌ها">
            <?php foreach (['verified' => '#16a34a', 'placed' => '#0ea5e9', 'collected' => '#64748b', 'missing' => '#dc2626', 'new' => '#e2e8f0'] as $st => $col): if (!$counts[$st]) continue; ?>
                <span style="width: <?= $pct($counts[$st]) ?>%; background: <?= $col ?>;" title="<?= e(tag_status_label($st)) ?>: <?= fa_num($counts[$st]) ?>"></span>
            <?php endforeach; ?>
        </div>
        <div class="status-chips mt-2">
            <?php foreach (tag_statuses() as $st => $info): ?>
                <span class="badge <?= e($info['class']) ?>"><?= e($info['label']) ?>: <?= fa_num($counts[$st]) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- فیلتر و جستجو -->
<div class="field-filters mb-3">
    <?php foreach ($filters as $fk => $fl): ?>
        <a href="<?= e(path_url($base) . '?filter=' . $fk) ?>" class="field-filter<?= $filter === $fk ? ' is-active' : '' ?>"><?= e($fl) ?></a>
    <?php endforeach; ?>
    <input type="search" class="form-control field-search js-tag-search" placeholder="🔍 جستجوی نام تگ یا نقطه..." aria-label="جستجو">
</div>

<?php if ($filter === 'todo-collect' && $is_admin && ($counts['placed'] + $counts['verified']) > 0): ?>
    <div class="alert alert-warning">
        <div class="alert-content" style="flex-wrap: wrap; gap: 0.5rem;">
            <span>پس از پایان جمع‌آوری، تگ‌هایی که پیدا نشدند را یک‌جا «گم‌شده» ثبت کنید تا گزارش نهایی آماده شود.</span>
            <form method="POST" action="<?= e(path_url($base . '/close-collection')) ?>" class="inline-form" onsubmit="return confirm('<?= fa_num($counts['placed'] + $counts['verified']) ?> تگ جمع‌آوری‌نشده «گم‌شده» ثبت شوند؟');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">پایان جمع‌آوری</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($groups) && empty($unplaced)): ?>
    <p class="text-center text-muted p-4">موردی برای نمایش وجود ندارد.</p>
<?php endif; ?>

<?php foreach ($groups as $floor): ?>
    <div class="card mb-3 field-floor">
        <div class="card-header"><h2 class="card-title">🏢 <?= e($floor['name']) ?></h2></div>
        <div class="card-body">
            <?php foreach ($floor['zones'] as $zone): ?>
                <div class="field-zone">
                    <h3 class="field-zone-title">🚪 <?= e($zone['name']) ?> <small class="text-muted">(<?= fa_num(count($zone['tags'])) ?>)</small></h3>
                    <div class="field-tag-list">
                        <?php foreach ($zone['tags'] as $t): include __DIR__ . '/partials_field_tag_row.php'; endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!empty($unplaced)): ?>
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title">📦 تگ‌های بدون محل (<?= fa_num(count($unplaced)) ?>)</h2></div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 0.85rem;">این تگ‌ها هنوز نصب نشده‌اند. تگ‌های اضافه را می‌توانید به‌عنوان «رزرو» برای جایگزینی تگ‌های خراب نگه دارید.</p>
            <div class="field-tag-list">
                <?php foreach ($unplaced as $t): include __DIR__ . '/partials_field_tag_row.php'; endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array($filter, ['all', 'todo-install'], true) && !empty($empty_spots)): ?>
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title">📍 نقطه‌های خالی این محل (<?= fa_num(count($empty_spots)) ?>)</h2></div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 0.85rem;">نقطه‌هایی که در رویدادهای قبلی ثبت شده‌اند و در این رویداد هنوز تگی ندارند. با عکس‌ها جای دقیق را پیدا کنید، تگ را نصب کنید و لمس کنید.</p>
            <div class="venue-spots">
                <?php foreach ($empty_spots as $s): $thumb = spot_photo_url($s, 'near', true) ?: spot_photo_url($s, 'wide', true); ?>
                    <div class="venue-spot js-search-item" data-search="<?= e($s['name'] . ' ' . $s['description'] . ' ' . $s['zone_name'] . ' ' . $s['floor_name']) ?>">
                        <div class="venue-spot-thumb"><?php if ($thumb): ?><a href="<?= e(spot_photo_url($s, $s['photo_near'] ? 'near' : 'wide')) ?>" target="_blank" rel="noopener"><img src="<?= e($thumb) ?>" alt="" loading="lazy"></a><?php else: ?>📍<?php endif; ?></div>
                        <strong><?= e($s['name']) ?></strong>
                        <small class="text-muted"><?= e($s['floor_name']) ?> › <?= e($s['zone_name']) ?></small>
                        <?php if (!empty($s['description'])): ?><small><?= e($s['description']) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
