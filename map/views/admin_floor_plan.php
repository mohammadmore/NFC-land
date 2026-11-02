<?php
/**
 * NFC Event Tag Scoring - Floor plan editor: plan image / simple sketch + spot pins
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'venues';
$extra_scripts = ['/assets/plan.js'];
$vBase = '/admin/venues/' . (int)$venue['id'];
$pBase = $vBase . '/floors/' . (int)$floor['id'] . '/plan';
$planBg = floor_plan_background_html($floor);
$hasImage = !empty($floor['plan_image']);
$sketch = sanitize_sketch($floor['sketch_json']);
$pinnedCount = 0;
foreach ($spots as $s) {
    if ($s['plan_x'] !== null) {
        $pinnedCount++;
    }
}
?>
<div class="event-mgmt-header">
    <div class="breadcrumb">
        <a href="<?= e(path_url('/admin/venues')) ?>">محل‌ها</a>
        <span class="separator">/</span>
        <a href="<?= e(path_url($vBase)) ?>"><?= e($venue['name']) ?></a>
        <span class="separator">/</span>
        <span class="current">پلان <?= e($floor['name']) ?></span>
    </div>
    <h1 class="page-title">🗺️ پلان «<?= e($floor['name']) ?>»</h1>
</div>

<nav class="nav-tabs" role="tablist">
    <a href="<?= e(path_url($pBase)) ?>" class="tab-item <?= $mode === 'pins' ? 'active' : '' ?>">📍 پین نقطه‌ها (<?= fa_num($pinnedCount) ?> از <?= fa_num(count($spots)) ?>)</a>
    <a href="<?= e(path_url($pBase) . '?mode=sketch') ?>" class="tab-item <?= $mode === 'sketch' ? 'active' : '' ?>">✏️ کروکی ساده</a>
</nav>

<div class="tab-content">
<?php if ($mode === 'pins'): ?>

    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title">تصویر پلان</h2></div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 0.9rem;">
                منبع پیشنهادی: عکس <strong>نقشه تخلیه اضطراری</strong> نصب‌شده در طبقه، اسکرین‌شات از PDF پلان هتل، یا هر کروکی دستی. اگر تصویری ندارید، از تب «کروکی ساده» چند مستطیل برای بخش‌ها بکشید.
                <?php if (!$hasImage && !empty($sketch['shapes'])): ?><br>در حال حاضر <strong>کروکی</strong> به‌عنوان پلان این طبقه استفاده می‌شود.<?php endif; ?>
            </p>
            <form method="POST" action="<?= e(path_url($pBase . '/upload')) ?>" enctype="multipart/form-data" class="inline-form" style="flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="file" name="plan" accept="image/png,image/jpeg,image/webp" class="form-control" required style="flex: 1; min-width: 200px;">
                <button type="submit" class="btn btn-primary"><?= $hasImage ? 'جایگزینی تصویر پلان' : 'بارگذاری تصویر پلان' ?></button>
            </form>
            <?php if ($hasImage): ?>
                <form method="POST" action="<?= e(path_url($pBase . '/delete')) ?>" class="mt-2" onsubmit="return confirm('تصویر پلان حذف شود؟ (پین‌ها باقی می‌مانند)');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">حذف تصویر پلان</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($planBg === ''): ?>
        <div class="alert alert-info"><div class="alert-content"><span class="alert-icon">ℹ</span><span>برای علامت‌گذاری نقطه‌ها ابتدا تصویر پلان بارگذاری کنید یا کروکی بکشید.</span></div></div>
    <?php else: ?>
        <div class="plan-editor js-pin-editor" data-pin-url="<?= e(path_url($vBase . '/spots')) ?>" data-csrf="<?= e(csrf_token()) ?>">
            <div class="plan-editor-main">
                <p class="text-muted" style="font-size: 0.9rem;">یک نقطه را از فهرست انتخاب کنید و روی پلان بزنید، یا پین‌های موجود را بکشید و جابه‌جا کنید.</p>
                <div class="plan-box plan-edit js-pin-box">
                    <?= $planBg ?>
                    <?php foreach ($spots as $s): if ($s['plan_x'] === null) continue; ?>
                        <span class="plan-pin plan-pin-edit js-pin" data-spot="<?= (int)$s['id'] ?>" style="left: <?= (float)$s['plan_x'] ?>%; top: <?= (float)$s['plan_y'] ?>%;" title="<?= e($s['name']) ?>"></span>
                    <?php endforeach; ?>
                </div>
                <p class="js-pin-status text-muted mt-2" style="font-size: 0.85rem;" aria-live="polite"></p>
            </div>
            <div class="plan-editor-side">
                <h3 style="font-size: 1rem;">نقطه‌های این طبقه</h3>
                <?php if (empty($spots)): ?>
                    <p class="text-muted" style="font-size: 0.85rem;">هنوز نقطه‌ای در این طبقه ثبت نشده است. نقطه‌ها هنگام نصب تگ ساخته می‌شوند.</p>
                <?php endif; ?>
                <div class="plan-spot-list">
                    <?php foreach ($spots as $s): ?>
                        <div class="plan-spot-item js-spot-item<?= $s['plan_x'] !== null ? ' has-pin' : '' ?>" data-spot="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>" tabindex="0" role="button">
                            <span class="plan-spot-flag"><?= $s['plan_x'] !== null ? '📍' : '○' ?></span>
                            <span class="plan-spot-text"><strong><?= e($s['name']) ?></strong><small><?= e($s['zone_name']) ?></small></span>
                            <button type="button" class="btn btn-sm btn-outline-primary js-pin-clear" title="حذف پین"<?= $s['plan_x'] === null ? ' style="display: none;"' : '' ?>>✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>

    <div class="card">
        <div class="card-header"><h2 class="card-title">✏️ کروکی ساده طبقه</h2></div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 0.9rem;">
                با کشیدن روی صفحه یک مستطیل برای هر بخش (لابی، رستوران، راهرو، آسانسور...) بکشید و نامش را بنویسید. روی هر مستطیل بزنید تا نامش را عوض یا حذفش کنید. دقت لازم نیست؛ فقط برای جهت‌یابی است.
                <?php if ($hasImage): ?><br><strong>توجه:</strong> این طبقه تصویر پلان دارد و تا وقتی تصویر حذف نشود، تصویر به جای کروکی نمایش داده می‌شود.<?php endif; ?>
            </p>
            <div class="sketch-toolbar">
                <button type="button" class="btn btn-sm btn-outline-primary js-sketch-undo">↶ بازگشت</button>
                <button type="button" class="btn btn-sm btn-danger js-sketch-clear">پاک کردن همه</button>
                <span class="text-muted js-sketch-count" style="font-size: 0.85rem;"></span>
            </div>
            <div class="plan-box sketch-box">
                <svg class="plan-bg plan-sketch js-sketch-svg" viewBox="0 0 1000 700" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"
                     data-sketch="<?= e(json_encode($sketch, JSON_UNESCAPED_UNICODE)) ?>"></svg>
            </div>
            <form method="POST" action="<?= e(path_url($pBase . '/sketch')) ?>" class="mt-3 js-sketch-form">
                <?= csrf_field() ?>
                <input type="hidden" name="sketch" class="js-sketch-input">
                <button type="submit" class="btn btn-primary btn-lg">💾 ذخیره کروکی</button>
            </form>
        </div>
    </div>

<?php endif; ?>
</div>
