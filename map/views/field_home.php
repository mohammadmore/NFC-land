<?php
/**
 * NFC Event Tag Scoring - Field work home (placers & admins)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'field';
$modes = field_modes();
?>
<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">کار میدانی</h1>
        <p class="page-subtitle">سلام <?= e($me['display_name'] ?: $me['username']) ?>! حالت کار را انتخاب کنید و سپس با <strong>همین گوشی و همین مرورگر</strong> تگ‌ها را لمس کنید. لمس تگ توسط شما هیچ امتیازی ثبت نمی‌کند.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="field-mode-switch" role="group" aria-label="حالت کار">
            <?php foreach ($modes as $mk => $mv): ?>
                <form method="POST" action="<?= e(path_url('/admin/field/mode')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="<?= e($mk) ?>">
                    <button type="submit" class="field-mode-btn<?= $mode === $mk ? ' is-active' : '' ?>" aria-pressed="<?= $mode === $mk ? 'true' : 'false' ?>">
                        <span class="field-mode-icon"><?= $mv['icon'] ?></span>
                        <span><?= e($mv['label']) ?></span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
        <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
            <?php if ($mode === 'install'): ?>
                📍 <strong>نصب:</strong> تگ را در جای خود بگذارید، لمسش کنید، طبقه و بخش را انتخاب کنید، دو عکس (نزدیک و باز) بگیرید و «ثبت نصب» را بزنید.
            <?php elseif ($mode === 'verify'): ?>
                ✔️ <strong>تأیید:</strong> قبل از شروع مسابقه همه تگ‌ها را یک دور لمس کنید و «تأیید» را بزنید تا مطمئن شوید سالم و در جای خود هستند.
            <?php else: ?>
                📦 <strong>جمع‌آوری:</strong> پس از مسابقه هر تگ را لمس کرده، «جمع‌آوری شد» را بزنید و تگ را بردارید.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">رویدادها</h2></div>
    <div class="card-body p-0">
        <?php if (empty($events)): ?>
            <p class="text-center text-muted p-4">هنوز رویدادی ساخته نشده است.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رویداد</th>
                            <th>محل</th>
                            <th>وضعیت تگ‌ها</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td class="font-bold">
                                    <?= e($ev['name']) ?>
                                    <?php if ((int)$ev['is_active'] === 1): ?><span class="badge badge-success">فعال</span><?php endif; ?>
                                </td>
                                <td><?= $ev['venue_name'] ? e($ev['venue_name']) : '<span class="text-danger">تعیین نشده</span>' ?></td>
                                <td>
                                    <div class="status-chips">
                                        <span class="badge badge-secondary">کل: <?= fa_num($ev['total']) ?></span>
                                        <?php foreach (['new', 'placed', 'verified', 'collected', 'missing'] as $st): if ((int)$ev['st_' . $st] === 0) continue; ?>
                                            <span class="badge <?= e(tag_status_class($st)) ?>"><?= e(tag_status_label($st)) ?>: <?= fa_num($ev['st_' . $st]) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
