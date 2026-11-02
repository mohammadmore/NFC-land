<?php
/**
 * NFC Event Tag Scoring - Single venue: floors, zones and spots
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'venues';
$base = '/admin/venues/' . (int)$venue['id'];
$zonesByFloor = [];
foreach ($structure['zones'] as $z) {
    $zonesByFloor[(int)$z['floor_id']][] = $z;
}
$spotsByZone = [];
foreach ($structure['spots'] as $s) {
    $spotsByZone[(int)$s['zone_id']][] = $s;
}
?>
<div class="event-mgmt-header">
    <div class="breadcrumb">
        <a href="<?= e(path_url('/admin/venues')) ?>">محل‌های برگزاری</a>
        <span class="separator">/</span>
        <span class="current"><?= e($venue['name']) ?></span>
    </div>
    <div class="event-title-row">
        <form method="POST" action="<?= e(path_url($base . '/update')) ?>" class="inline-form" style="flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="text" name="name" value="<?= e($venue['name']) ?>" class="form-control" maxlength="150" required style="font-size: 1.2rem; font-weight: 700;">
            <button type="submit" class="btn btn-sm btn-outline-primary">💾 ذخیره نام</button>
        </form>
        <div class="text-muted" style="font-size: 0.9rem;">
            رویدادهای این محل:
            <?php if (empty($venue_events)): ?>ندارد<?php else: ?>
                <?php foreach ($venue_events as $i => $ve): ?><?= $i ? '، ' : '' ?><a href="<?= e(path_url('/admin/events/' . (int)$ve['id'])) ?>"><?= e($ve['name']) ?></a><?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h2 class="card-title">افزودن طبقه</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= e(path_url($base . '/floors/create')) ?>" class="inline-form" style="flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="text" name="name" class="form-control" required maxlength="100" placeholder="مثال: همکف، طبقه ۲، زیرزمین" style="flex: 1; min-width: 200px;">
            <button type="submit" class="btn btn-success">افزودن طبقه</button>
        </form>
        <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">نصاب‌ها هم حین نصب می‌توانند طبقه و بخش جدید اضافه کنند. نقطه‌ها فقط هنگام نصب تگ (با لمس تگ) ساخته می‌شوند.</p>
    </div>
</div>

<?php if (empty($structure['floors'])): ?>
    <p class="text-center text-muted p-4">هنوز طبقه‌ای تعریف نشده است.</p>
<?php endif; ?>

<?php foreach ($structure['floors'] as $f): $fid = (int)$f['id']; ?>
    <div class="card mb-4 venue-floor">
        <div class="card-header d-flex-between" style="flex-wrap: wrap; gap: 0.5rem;">
            <form method="POST" action="<?= e(path_url($base . '/floors/' . $fid . '/update')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <span title="طبقه">🏢</span>
                <input type="text" name="name" value="<?= e($f['name']) ?>" class="form-control form-control-sm font-bold" maxlength="100" required>
                <input type="number" name="sort_order" value="<?= (int)$f['sort_order'] ?>" class="form-control form-control-sm" style="width: 70px;" title="ترتیب">
                <button type="submit" class="btn btn-sm btn-outline-primary" title="ذخیره">💾</button>
            </form>
            <form method="POST" action="<?= e(path_url($base . '/floors/' . $fid . '/delete')) ?>" class="inline-form" onsubmit="return confirm('طبقه «<?= e($f['name']) ?>» با همه بخش‌ها، نقطه‌ها و عکس‌هایش حذف شود؟');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">حذف طبقه</button>
            </form>
        </div>
        <div class="card-body">
            <?php foreach (isset($zonesByFloor[$fid]) ? $zonesByFloor[$fid] : [] as $z): $zid = (int)$z['id']; $zSpots = isset($spotsByZone[$zid]) ? $spotsByZone[$zid] : []; ?>
                <div class="venue-zone">
                    <div class="d-flex-between" style="flex-wrap: wrap; gap: 0.5rem;">
                        <form method="POST" action="<?= e(path_url($base . '/zones/' . $zid . '/update')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <span title="بخش">🚪</span>
                            <input type="text" name="name" value="<?= e($z['name']) ?>" class="form-control form-control-sm" maxlength="100" required>
                            <input type="number" name="sort_order" value="<?= (int)$z['sort_order'] ?>" class="form-control form-control-sm" style="width: 70px;" title="ترتیب">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="ذخیره">💾</button>
                            <span class="text-muted" style="font-size: 0.85rem;"><?= fa_num(count($zSpots)) ?> نقطه</span>
                        </form>
                        <form method="POST" action="<?= e(path_url($base . '/zones/' . $zid . '/delete')) ?>" class="inline-form" onsubmit="return confirm('بخش «<?= e($z['name']) ?>» با نقطه‌ها و عکس‌هایش حذف شود؟');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">حذف بخش</button>
                        </form>
                    </div>
                    <?php if (!empty($zSpots)): ?>
                        <div class="venue-spots">
                            <?php foreach ($zSpots as $s): $sid = (int)$s['id']; $thumb = spot_photo_url($s, 'near', true) ?: spot_photo_url($s, 'wide', true); $used = isset($usage[$sid]) ? $usage[$sid] : 0; ?>
                                <div class="venue-spot">
                                    <div class="venue-spot-thumb">
                                        <?php if ($thumb): ?><a href="<?= e(spot_photo_url($s, $s['photo_near'] ? 'near' : 'wide')) ?>" target="_blank" rel="noopener"><img src="<?= e($thumb) ?>" alt="" loading="lazy"></a><?php else: ?>📍<?php endif; ?>
                                    </div>
                                    <form method="POST" action="<?= e(path_url($base . '/spots/' . $sid . '/update')) ?>" class="venue-spot-form">
                                        <?= csrf_field() ?>
                                        <input type="text" name="name" value="<?= e($s['name']) ?>" class="form-control form-control-sm" maxlength="150" required>
                                        <textarea name="description" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="توضیح محل"><?= e($s['description']) ?></textarea>
                                        <div class="d-flex-between" style="gap: 0.35rem;">
                                            <small class="text-muted">استفاده در <?= fa_num($used) ?> تگ</small>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">💾</button>
                                        </div>
                                    </form>
                                    <?php if ($used === 0): ?>
                                        <form method="POST" action="<?= e(path_url($base . '/spots/' . $sid . '/delete')) ?>" onsubmit="return confirm('نقطه «<?= e($s['name']) ?>» و عکس‌هایش حذف شود؟');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger btn-block">حذف</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <form method="POST" action="<?= e(path_url($base . '/zones/create')) ?>" class="inline-form mt-3" style="flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="floor_id" value="<?= $fid ?>">
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="100" placeholder="بخش جدید در این طبقه (مثلاً لابی، رستوران)" style="flex: 1; min-width: 200px;">
                <button type="submit" class="btn btn-sm btn-success">+ افزودن بخش</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($venue_events)): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= e(path_url($base . '/delete')) ?>" onsubmit="return confirm('محل «<?= e($venue['name']) ?>» با همه طبقات، نقطه‌ها و عکس‌ها برای همیشه حذف شود؟');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">🗑️ حذف کامل این محل</button>
            </form>
        </div>
    </div>
<?php endif; ?>
