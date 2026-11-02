<?php
/**
 * NFC Event Tag Scoring - Venues list
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'venues';
?>
<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">محل‌های برگزاری</h1>
        <p class="page-subtitle">هر محل (هتل یا ساختمان) طبقات، بخش‌ها و نقطه‌های نصب خودش را دارد که در رویدادهای بعدی همان محل دوباره استفاده می‌شوند.</p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h2 class="card-title">لیست محل‌ها (<?= fa_num(count($venues)) ?>)</h2></div>
        <div class="card-body p-0">
            <?php if (empty($venues)): ?>
                <p class="text-center text-muted p-4">هنوز محلی ثبت نشده است.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>نام محل</th><th>طبقات</th><th>نقطه‌ها</th><th>رویدادها</th></tr></thead>
                        <tbody>
                            <?php foreach ($venues as $v): ?>
                                <tr>
                                    <td class="font-bold"><a href="<?= e(path_url('/admin/venues/' . (int)$v['id'])) ?>"><?= e($v['name']) ?></a></td>
                                    <td><?= fa_num($v['floors_count']) ?></td>
                                    <td><?= fa_num($v['spots_count']) ?></td>
                                    <td><?= fa_num($v['events_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">افزودن محل جدید</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= e(path_url('/admin/venues/create')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">نام محل *</label>
                    <input type="text" name="name" class="form-control" required maxlength="150" placeholder="مثال: هتل اسپیناس پالاس">
                </div>
                <button type="submit" class="btn btn-success btn-block">ساخت محل</button>
            </form>
        </div>
    </div>
</div>
