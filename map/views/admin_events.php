<?php
/**
 * NFC Event Tag Scoring - Admin Events List View
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'events';
$extra_scripts = ['/assets/admin.js'];
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">مدیریت رویدادها</h1>
        <p class="page-subtitle">مشاهده، ایجاد و مدیریت رویدادهای مسابقه</p>
    </div>
</div>

<div class="dashboard-grid">
    <!-- بخش لیست رویدادها -->
    <div class="card events-list-card">
        <div class="card-header">
            <h2 class="card-title">لیست رویدادها (<?= fa_num(count($events)) ?> رویداد)</h2>
        </div>
        <div class="card-body p-0">
            <?php if (empty($events)): ?>
                <div class="empty-state p-4 text-center">
                    <p class="text-muted">هنوز هیچ رویدادی ایجاد نشده است. از فرم روبرو برای ساخت اولین رویداد استفاده کنید.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table events-table">
                        <thead>
                            <tr>
                                <th>نام رویداد</th>
                                <th>وضعیت</th>
                                <th>تعداد تیم‌ها</th>
                                <th>تعداد تگ‌ها</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="font-bold">
                                        <a href="<?= e(path_url('/admin/events/' . $ev['id'])) ?>" class="event-name-link">
                                            <?= e($ev['name']) ?>
                                        </a>
                                        <span class="event-palette-dots" title="پالت رنگ رویداد"><?php foreach (event_brand_palette($ev) as $pc): ?><i style="background: <?= e($pc) ?>;"></i><?php endforeach; ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$ev['is_active'] === 1): ?>
                                            <span class="badge badge-success">فعال</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= fa_num($ev['teams_count']) ?> تیم</td>
                                    <td><?= fa_num($ev['tags_count']) ?> تگ</td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <a href="<?= e(path_url('/admin/events/' . $ev['id'])) ?>" class="btn btn-sm btn-primary">
                                                مدیریت
                                            </a>

                                            <!-- فرم کپی رویداد -->
                                            <form method="POST" action="<?= e(path_url('/admin/events/' . $ev['id'] . '/duplicate')) ?>" class="inline-form" onsubmit="return confirm('آیا از ایجاد یک کپی کامل از این رویداد اطمینان دارید؟ تگ‌های رویداد جدید کدهای جدید خواهند داشت.');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    کپی
                                                </button>
                                            </form>

                                            <!-- دکمه حذف رویداد با تایپ نام -->
                                            <button type="button" class="btn btn-sm btn-danger btn-delete-event" 
                                                    data-id="<?= (int)$ev['id'] ?>" 
                                                    data-name="<?= e($ev['name']) ?>">
                                                حذف
                                            </button>
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

    <!-- فرم ایجاد رویداد جدید -->
    <div class="card create-event-card">
        <div class="card-header">
            <h2 class="card-title">ایجاد رویداد جدید</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= e(path_url('/admin/events/create')) ?>">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="event_name" class="form-label">نام رویداد *</label>
                    <input type="text" id="event_name" name="name" class="form-control" required placeholder="مثال: مسابقه بهاری ۱۴۰۵">
                </div>

                <div class="form-group">
                    <label for="positions_count" class="form-label">تعداد جایگاه‌های قابل ثبت در هر تگ (۱ تا ۱۰)</label>
                    <input type="number" id="positions_count" name="positions_count" class="form-control" min="1" max="10" value="4" required>
                    <small class="form-text text-muted">حداکثر چند تیم می‌توانند هر تگ را اسکن و امتیاز دریافت کنند (پیش‌فرض ۴).</small>
                </div>

                <div class="form-group">
                    <label class="custom-control-label">
                        <input type="checkbox" name="show_result_to_scanner" value="1" checked class="custom-checkbox">
                        نمایش جایگاه و امتیاز کسب‌شده به اسکن‌کننده
                    </label>
                </div>

                <div class="form-group">
                    <label class="custom-control-label">
                        <input type="checkbox" name="is_active" value="1" checked class="custom-checkbox">
                        رویداد فعال باشد
                    </label>
                </div>

                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-success btn-block btn-lg">
                        ایجاد رویداد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- پنجره تایید حذف رویداد با تایپ نام -->
<div id="deleteEventModal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title text-danger">تأیید حذف رویداد</h3>
        </div>
        <form id="deleteEventForm" method="POST" action="">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p>
                    آیا از حذف کامل رویداد <strong id="deleteEventTargetName" class="text-danger"></strong> اطمینان دارید؟
                </p>
                <p class="text-danger small">
                    ⚠️ هشدار: با حذف رویداد، تمام تگ‌ها، تیم‌ها، رنگ‌ها و امتیازات ثبت‌شده به طور برگشت‌ناپذیر پاک خواهند شد.
                </p>
                <div class="form-group mt-3">
                    <label for="confirmDeleteNameInput" class="form-label">
                        برای تأیید، نام دقیق رویداد را در کادر زیر بنویسید:
                    </label>
                    <input type="text" id="confirmDeleteNameInput" class="form-control" required placeholder="نام رویداد">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" id="btnConfirmDeleteEvent" class="btn btn-danger btn-lg" disabled>
                    حذف قطعی رویداد
                </button>
                <button type="button" id="btnCancelDeleteEvent" class="btn btn-secondary btn-lg">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>
