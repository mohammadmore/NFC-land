<?php
/**
 * NFC Event Tag Scoring - Field team: assignments & activity report (admin)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'field';
$theme_palette = event_brand_palette($event);
$base = '/admin/field/event/' . (int)$event['id'];
$zonesByFloor = [];
foreach ($structure['zones'] as $z) {
    $zonesByFloor[(int)$z['floor_id']][] = $z;
}
$floorName = [];
foreach ($structure['floors'] as $f) {
    $floorName[(int)$f['id']] = $f['name'];
}
$zoneName = [];
foreach ($structure['zones'] as $z) {
    $zoneName[(int)$z['id']] = $z['name'];
}
?>
<div class="event-mgmt-header">
    <div class="breadcrumb">
        <a href="<?= e(path_url('/admin/field')) ?>">کار میدانی</a>
        <span class="separator">/</span>
        <a href="<?= e(path_url($base)) ?>"><?= e($event['name']) ?></a>
        <span class="separator">/</span>
        <span class="current">تقسیم کار</span>
    </div>
    <h1 class="page-title">👥 تقسیم کار و گزارش نصاب‌ها</h1>
    <p class="text-muted">برای هر نفر طبقه‌ها یا بخش‌هایی را که مسئول آن است مشخص کنید. نصاب در نقشه رویداد فیلتر «کارهای من» و برگه چاپی محدوده خودش را می‌بیند. (تخصیص فقط راهنماست و مانع کار در بخش‌های دیگر نمی‌شود.)</p>
</div>

<?php if (empty($event['venue_id'])): ?>
    <div class="alert alert-warning"><div class="alert-content"><span>ابتدا محل برگزاری رویداد را در تنظیمات تعیین کنید.</span></div></div>
<?php elseif (empty($structure['floors'])): ?>
    <div class="alert alert-warning"><div class="alert-content"><span>هنوز طبقه‌ای برای این محل تعریف نشده است.</span></div></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><h2 class="card-title">📊 گزارش فعالیت در این رویداد</h2></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>کاربر</th><th>محدوده</th><th>نصب</th><th>تأیید</th><th>جمع‌آوری</th><th>تعویض</th><th>گم‌شده‌ها</th><th>آخرین فعالیت</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $u): $uid = (int)$u['id']; $a = isset($activity[$uid]) ? $activity[$uid] : null; $as = isset($assign[$uid]) ? $assign[$uid] : null; ?>
                        <tr>
                            <td class="font-bold"><?= e($u['display_name'] ?: $u['username']) ?> <small class="text-muted"><?= $u['role'] === 'admin' ? '(مدیر)' : '' ?></small></td>
                            <td style="font-size: 0.85rem;">
                                <?php if (!$as): ?><span class="text-muted">همه</span><?php else:
                                    $parts = [];
                                    foreach (array_keys($as['floors']) as $fid) { if (isset($floorName[$fid])) $parts[] = '🏢 ' . $floorName[$fid]; }
                                    foreach (array_keys($as['zones']) as $zid) { if (isset($zoneName[$zid])) $parts[] = '🚪 ' . $zoneName[$zid]; }
                                    echo e(implode('، ', $parts));
                                endif; ?>
                            </td>
                            <td><?= fa_num($a ? (int)$a['placed'] : 0) ?></td>
                            <td><?= fa_num($a ? (int)$a['verified'] : 0) ?></td>
                            <td><?= fa_num($a ? (int)$a['collected'] : 0) ?></td>
                            <td><?= fa_num($a ? (int)$a['replaced'] : 0) ?></td>
                            <td><?= !empty($missing_by[$uid]) ? '<span class="badge badge-danger">' . fa_num($missing_by[$uid]) . '</span>' : fa_num(0) ?></td>
                            <td style="font-size: 0.85rem;"><?= $a ? e(format_jalali($a['last_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($structure['floors'])): ?>
<div class="assign-grid">
    <?php foreach ($staff as $u): $uid = (int)$u['id']; $as = isset($assign[$uid]) ? $assign[$uid] : ['floors' => [], 'zones' => []]; ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= e($u['display_name'] ?: $u['username']) ?> <small class="text-muted"><?= $u['role'] === 'admin' ? '(مدیر)' : '(نصاب)' ?></small></h3></div>
            <div class="card-body">
                <form method="POST" action="<?= e(path_url($base . '/team/save')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                    <?php foreach ($structure['floors'] as $f): $fid = (int)$f['id']; ?>
                        <div class="assign-floor">
                            <label class="font-bold"><input type="checkbox" name="floors[]" value="<?= $fid ?>" <?= isset($as['floors'][$fid]) ? 'checked' : '' ?>> 🏢 کل <?= e($f['name']) ?></label>
                            <?php if (!empty($zonesByFloor[$fid])): ?>
                                <div class="assign-zones">
                                    <?php foreach ($zonesByFloor[$fid] as $z): ?>
                                        <label><input type="checkbox" name="zones[]" value="<?= (int)$z['id'] ?>" <?= isset($as['zones'][(int)$z['id']]) ? 'checked' : '' ?>> <?= e($z['name']) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm btn-block mt-2">ذخیره محدوده</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
