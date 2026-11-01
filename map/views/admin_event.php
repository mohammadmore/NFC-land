<?php
/**
 * NFC Event Tag Scoring - Admin Single Event Management (Tabs 1 to 6)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'events';
$extra_scripts = ['/assets/admin.js'];
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'leaderboard';
$validTabs = ['settings', 'colors', 'teams', 'tags', 'leaderboard', 'export'];
if (!in_array($currentTab, $validTabs, true)) {
    $currentTab = 'leaderboard';
}
$positionsCount = (int)$event['positions_count'];
?>

<div class="event-mgmt-header">
    <div class="breadcrumb">
        <a href="<?= e(path_url('/admin/events')) ?>">رویدادها</a>
        <span class="separator">/</span>
        <span class="current"><?= e($event['name']) ?></span>
    </div>

    <div class="event-title-row">
        <h1 class="page-title"><?= e($event['name']) ?></h1>
        <div class="event-status-badges">
            <?php if ((int)$event['is_active'] === 1): ?>
                <span class="badge badge-success">رویداد فعال</span>
            <?php else: ?>
                <span class="badge badge-secondary">رویداد غیرفعال</span>
            <?php endif; ?>
            <span class="badge badge-info"><?= fa_num($positionsCount) ?> جایگاه امتیاز در هر تگ</span>
        </div>
    </div>
</div>

<!-- تب‌های مدیریت رویداد -->
<nav class="nav-tabs" role="tablist">
    <a href="?tab=leaderboard" class="tab-item <?= $currentTab === 'leaderboard' ? 'active' : '' ?>">🏆 لیدربورد و ثبت‌ها</a>
    <a href="?tab=tags" class="tab-item <?= $currentTab === 'tags' ? 'active' : '' ?>">🏷️ تگ‌ها (<?= fa_num(count($tags)) ?>)</a>
    <a href="?tab=teams" class="tab-item <?= $currentTab === 'teams' ? 'active' : '' ?>">👥 تیم‌ها (<?= fa_num(count($teams)) ?>)</a>
    <a href="?tab=colors" class="tab-item <?= $currentTab === 'colors' ? 'active' : '' ?>">🎨 رنگ‌ها و امتیازات</a>
    <a href="?tab=settings" class="tab-item <?= $currentTab === 'settings' ? 'active' : '' ?>">⚙️ تنظیمات</a>
    <a href="?tab=export" class="tab-item <?= $currentTab === 'export' ? 'active' : '' ?>">📥 خروجی اکسل (CSV)</a>
</nav>

<div class="tab-content">
    <?php if ($currentTab === 'leaderboard'): ?>
        <!-- ==================== تب ۵: لیدربورد ==================== -->
        <div class="leaderboard-tab">
            <div class="card mb-4">
                <div class="card-header d-flex-between">
                    <div>
                        <h2 class="card-title">جدول رده‌بندی تیم‌ها</h2>
                        <small class="text-muted">مرتب‌سازی: ۱. مجموع امتیاز، ۲. تعداد تگ بیشتر، ۳. ثبت زودتر</small>
                        <small class="text-muted d-block">
                            لیدربورد عمومی:
                            <?php if (!empty($event['public_board']) && (int)$event['public_board'] === 1 && !empty($event['board_token'])): ?>
                                <a href="<?= e(path_url('/board/' . $event['board_token'])) ?>" target="_blank" rel="noopener">فعال (مشاهده)</a>
                            <?php else: ?>
                                غیرفعال (قابل فعال‌سازی از تب تنظیمات)
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="leaderboard-controls" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <label class="auto-refresh-toggle">
                            <input type="checkbox" id="autoRefreshCheckbox" checked>
                            <span>به‌روزرسانی خودکار (۲۰ ثانیه)</span>
                        </label>
                        <button type="button" id="btnManualRefresh" class="btn btn-sm btn-primary">
                            🔄 به‌روزرسانی
                        </button>
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/reset-claims')) ?>" class="inline-form" onsubmit="return confirm('⚠️ آیا از پاکسازی و حذف تمام ثبت‌های این مسابقه اطمینان دارید؟ تمام امتیازات صفر و قفل گوشی‌ها آزاد خواهند شد (مناسب برای بعد از تست‌های اولیه).');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger" title="صفر کردن تمام امتیازات">
                                🗑️ پاکسازی ثبت‌ها
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover leaderboard-table">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">رتبه</th>
                                    <th>نام تیم</th>
                                    <th>مجموع امتیاز</th>
                                    <th>تعداد تگ‌های ثبت‌شده</th>
                                    <th>جزئیات</th>
                                </tr>
                            </thead>
                            <tbody id="leaderboardTbody">
                                <?php if (empty($leaderboard)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted p-4">هنوز تیمی ثبت‌نام نکرده یا امتیازی ثبت نشده است.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rank = 1; foreach ($leaderboard as $row): ?>
                                        <tr class="leaderboard-row" data-team-id="<?= (int)$row['team_id'] ?>" data-team-name="<?= e($row['team_name']) ?>">
                                            <td class="font-bold text-center">
                                                <?php if ($rank === 1): ?>🥇 <?= fa_num($rank) ?><?php elseif ($rank === 2): ?>🥈 <?= fa_num($rank) ?><?php elseif ($rank === 3): ?>🥉 <?= fa_num($rank) ?><?php else: ?><?= fa_num($rank) ?><?php endif; ?>
                                            </td>
                                            <td class="font-bold"><?= e($row['team_name']) ?></td>
                                            <td><span class="points-badge"><?= fa_num($row['total_points']) ?></span></td>
                                            <td><?= fa_num($row['tag_count']) ?> تگ</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-view-team-claims" data-team-id="<?= (int)$row['team_id'] ?>" data-team-name="<?= e($row['team_name']) ?>">
                                                    مشاهده ثبت‌ها
                                                </button>
                                            </td>
                                        </tr>
                                    <?php $rank++; endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- بخش ثبت‌ها به تفکیک تگ -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">ثبت‌ها به تفکیک تگ و مدیریت ترتیب رتبه‌ها</h2>
                    <small class="text-muted">در صورت حذف هر ثبت، رتبه‌های بعدی به طور خودکار بازشماری و امتیازات تصحیح می‌شوند.</small>
                </div>
                <div class="card-body">
                    <?php if (empty($claims_by_tag)): ?>
                        <p class="text-muted text-center p-3">هیچ تگی وجود ندارد یا ثبتی انجام نشده است.</p>
                    <?php else: ?>
                        <div class="tags-claims-accordion">
                            <?php foreach ($claims_by_tag as $item): 
                                $tg = $item['tag'];
                                $cList = $item['claims'];
                                $tColorHex = !empty($tg['color_hex']) ? $tg['color_hex'] : '#94a3b8';
                            ?>
                                <div class="tag-claims-block">
                                    <div class="tag-claims-header">
                                        <span class="color-dot" style="background-color: <?= e($tColorHex) ?>;"></span>
                                        <strong class="tag-title"><?= e($tg['name']) ?></strong>
                                        <span class="badge badge-secondary"><?= fa_num(count($cList)) ?> از <?= fa_num($positionsCount) ?> ثبت</span>
                                    </div>
                                    <div class="tag-claims-body">
                                        <?php if (empty($cList)): ?>
                                            <span class="text-muted small">هنوز تیمی این تگ را اسکن نکرده است.</span>
                                        <?php else: ?>
                                            <div class="claims-chips">
                                                <?php foreach ($cList as $cl): ?>
                                                    <div class="claim-chip">
                                                        <span class="claim-rank"><?= rank_to_words_fa($cl['rank']) ?>:</span>
                                                        <span class="claim-team font-bold"><?= e($cl['team_name']) ?></span>
                                                        <span class="claim-pts">(<?= fa_num($cl['points']) ?> امتیاز)</span>
                                                        <span class="claim-time"><?= format_jalali($cl['created_at'], true, true) ?></span>
                                                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/claims/' . $cl['claim_id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('آیا از حذف این ثبت اطمینان دارید؟ رتبه‌های بعدی بازشماری خواهند شد.');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn-delete-chip" title="حذف ثبت">×</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($currentTab === 'tags'): ?>
        <!-- ==================== تب ۴: تگ‌ها ==================== -->
        <div class="tags-tab">
            <div class="dashboard-grid mb-4">
                <!-- ساخت چند تگ گروهی -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">ساخت تگ گروهی</h3></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/tags/create-bulk')) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label">تعداد تگ برای ایجاد (۱ تا ۵۰)</label>
                                <input type="number" name="count" class="form-control" min="1" max="50" value="5" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">پیشوند نام تگ‌ها (اختیاری)</label>
                                <input type="text" name="prefix" class="form-control" placeholder="مثال: تگ سالن A-">
                            </div>
                            <div class="form-group">
                                <label class="form-label">رنگ پیش‌فرض</label>
                                <select name="color_id" class="form-control form-select">
                                    <option value="">بدون رنگ</option>
                                    <?php foreach ($colors as $col): ?>
                                        <option value="<?= (int)$col['id'] ?>"><?= e($col['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">تولید خودکار تگ‌ها</button>
                        </form>
                    </div>
                </div>

                <!-- افزودن یک تگ تکی -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">افزودن تگ تکی</h3></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/tags/create-single')) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label">نام تگ *</label>
                                <input type="text" name="name" class="form-control" required placeholder="مثال: ورودی کتابخانه">
                            </div>
                            <div class="form-group">
                                <label class="form-label">رنگ تگ</label>
                                <select name="color_id" class="form-control form-select">
                                    <option value="">بدون رنگ</option>
                                    <?php foreach ($colors as $col): ?>
                                        <option value="<?= (int)$col['id'] ?>"><?= e($col['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">افزودن تگ</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- جدول تگ‌ها -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">لیست تگ‌های تعریف‌شده (<?= fa_num(count($tags)) ?> تگ)</h3></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table tags-table">
                            <thead>
                                <tr>
                                    <th>نام تگ</th>
                                    <th>رنگ</th>
                                    <th>لینک کامل جهت رایت روی NFC</th>
                                    <th>وضعیت ثبت‌ها</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr><td colspan="5" class="text-center text-muted p-4">هنوز تگی برای این رویداد ساخته نشده است.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $tg): 
                                        $fullUrl = url('/' . $tg['code']);
                                    ?>
                                        <tr>
                                            <td>
                                                <form id="editTagForm<?= (int)$tg['id'] ?>" method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/tags/' . $tg['id'] . '/update')) ?>" style="display:none;">
                                                    <?= csrf_field() ?>
                                                </form>
                                                <input type="text" form="editTagForm<?= (int)$tg['id'] ?>" name="name" value="<?= e($tg['name']) ?>" class="form-control form-control-sm" required>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.35rem; align-items: center;">
                                                    <select form="editTagForm<?= (int)$tg['id'] ?>" name="color_id" class="form-control form-control-sm form-select">
                                                        <option value="">بدون رنگ</option>
                                                        <?php foreach ($colors as $col): ?>
                                                            <option value="<?= (int)$col['id'] ?>" <?= ((int)$tg['color_id'] === (int)$col['id']) ? 'selected' : '' ?>>
                                                                <?= e($col['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" form="editTagForm<?= (int)$tg['id'] ?>" class="btn btn-sm btn-outline-primary" title="ذخیره ویرایش نام و رنگ">💾</button>
                                                </div>
                                            </td>
                                            <td class="link-cell">
                                                <div class="copy-url-group">
                                                    <input type="text" readonly value="<?= e($fullUrl) ?>" class="form-control form-control-sm dir-ltr url-input">
                                                    <button type="button" class="btn btn-sm btn-secondary btn-copy" data-text="<?= e($fullUrl) ?>">کپی</button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= ((int)$tg['claims_count'] >= $positionsCount) ? 'badge-danger' : 'badge-info' ?>">
                                                    <?= fa_num($tg['claims_count']) ?> از <?= fa_num($positionsCount) ?> پر
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/tags/' . $tg['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('آیا از حذف این تگ اطمینان دارید؟ لینک این تگ فوراً باطل خواهد شد.');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($currentTab === 'teams'): ?>
        <!-- ==================== تب ۳: تیم‌ها ==================== -->
        <div class="teams-tab">
            <div class="dashboard-grid mb-4">
                <!-- افزودن گروهی تیم‌ها -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">افزودن گروهی تیم‌ها</h3></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/teams/create-bulk')) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label">نام هر تیم را در یک خط وارد نمایید:</label>
                                <textarea name="teams_text" rows="5" class="form-control" placeholder="تیم شاهین&#10;تیم عقاب&#10;تیم سیمرغ" required></textarea>
                                <small class="text-muted">فاصله‌های اضافه و خطوط تکراری به طور خودکار پالایش می‌شوند.</small>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">افزودن تیم‌ها</button>
                        </form>
                    </div>
                </div>

                <!-- افزودن یک تیم تکی -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">افزودن یک تیم</h3></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/teams/create-single')) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="form-label">نام تیم *</label>
                                <input type="text" name="name" class="form-control" required placeholder="مثال: تیم کهکشان">
                            </div>
                            <button type="submit" class="btn btn-success btn-block">افزودن تیم</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- جدول تیم‌ها -->
            <div class="card">
                <div class="card-header d-flex-between">
                    <div>
                        <h3 class="card-title">لیست تیم‌های این رویداد (<?= fa_num(count($teams)) ?> تیم)</h3>
                        <small class="text-muted">قفل گوشی روی تیم: <?= (!empty($event['lock_device_team']) && (int)$event['lock_device_team'] === 1) ? 'فعال' : 'غیرفعال (قابل فعال‌سازی از تب تنظیمات)' ?></small>
                    </div>
                    <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/devices/reset')) ?>" class="inline-form" onsubmit="return confirm('قفل تمام گوشی‌های این رویداد آزاد شود؟');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-secondary">🔓 آزادسازی همه گوشی‌ها</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام تیم</th>
                                    <th>تعداد ثبت‌ها</th>
                                    <th>گوشی‌های قفل‌شده</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teams)): ?>
                                    <tr><td colspan="5" class="text-center text-muted p-4">هنوز تیمی تعریف نشده است.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($teams as $tm): ?>
                                        <tr>
                                            <td><?= fa_num($tm['id']) ?></td>
                                            <td>
                                                <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/teams/' . $tm['id'] . '/update')) ?>" class="inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="text" name="name" value="<?= e($tm['name']) ?>" class="form-control form-control-sm" required>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="ذخیره نام">💾</button>
                                                </form>
                                            </td>
                                            <td><?= fa_num($tm['claims_count']) ?> ثبت</td>
                                            <td>
                                                <?= fa_num($tm['devices_count']) ?> گوشی
                                                <?php if ((int)$tm['devices_count'] > 0): ?>
                                                    <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/teams/' . $tm['id'] . '/reset-devices')) ?>" class="inline-form" onsubmit="return confirm('گوشی‌های این تیم آزاد شوند تا بتوانند برای هر تیمی ثبت کنند؟');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="آزادسازی قفل گوشی‌ها">🔓 آزادسازی</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/teams/' . $tm['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('<?= ((int)$tm['claims_count'] > 0) ? 'هشدار: این تیم ثبت‌هایی دارد. با حذف تیم، تمام ثبت‌های آن پاک شده و رتبه تگ‌ها بازشماری می‌شود. ادامه می‌دهید؟' : 'آیا از حذف این تیم اطمینان دارید؟' ?>');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($currentTab === 'colors'): ?>
        <!-- ==================== تب ۲: رنگ‌ها و امتیازات ==================== -->
        <div class="colors-tab">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">افزودن رنگ جدید به رویداد</h3></div>
                <div class="card-body">
                    <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/colors/create')) ?>" class="form-inline-grid">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label">نام رنگ *</label>
                            <input type="text" name="name" class="form-control" required placeholder="مثال: طلایی، قرمز">
                        </div>
                        <div class="form-group">
                            <label class="form-label">کد رنگ</label>
                            <div class="color-picker-group">
                                <input type="color" name="hex" value="#ef4444" class="form-control form-control-color">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتیب نمایش</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="form-group d-flex-end">
                            <button type="submit" class="btn btn-success">افزودن رنگ</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول ماتریس امتیازات بر اساس رنگ و جایگاه -->
            <div class="card">
                <div class="card-header d-flex-between">
                    <div>
                        <h3 class="card-title">جدول امتیازدهی (رنگ‌ها × جایگاه‌ها)</h3>
                        <small class="text-muted">امتیاز هر جایگاه برای هر رنگ را وارد کنید. امتیازها بلافاصله در لیدربورد اعمال می‌شوند.</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($colors)): ?>
                        <p class="text-center text-muted p-4">هیچ رنگی تعریف نشده است. ابتدا یک رنگ اضافه کنید.</p>
                    <?php else: ?>
                        <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/colors/update-points')) ?>">
                            <?= csrf_field() ?>
                            <div class="table-responsive">
                                <table class="table points-matrix-table">
                                    <thead>
                                        <tr>
                                            <th>رنگ</th>
                                            <th>کد و نمونه</th>
                                            <th>ترتیب</th>
                                            <?php for ($r = 1; $r <= $positionsCount; $r++): ?>
                                                <th class="text-center">جایگاه <?= rank_to_words_fa($r) ?></th>
                                            <?php endfor; ?>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($colors as $col): 
                                            $cid = (int)$col['id'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="text" name="colors[<?= $cid ?>][name]" value="<?= e($col['name']) ?>" class="form-control form-control-sm" required>
                                                </td>
                                                <td>
                                                    <div class="color-edit-cell">
                                                        <input type="color" name="colors[<?= $cid ?>][hex]" value="<?= e(normalize_hex($col['hex'])) ?>" class="form-control form-control-color">
                                                        <span class="color-hex-label dir-ltr"><?= e(normalize_hex($col['hex'])) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" name="colors[<?= $cid ?>][sort_order]" value="<?= (int)$col['sort_order'] ?>" class="form-control form-control-sm" style="width: 70px;">
                                                </td>
                                                <?php for ($r = 1; $r <= $positionsCount; $r++): 
                                                    $ptsVal = isset($points_matrix[$cid][$r]) ? $points_matrix[$cid][$r] : 0;
                                                ?>
                                                    <td class="text-center">
                                                        <input type="number" name="points[<?= $cid ?>][<?= $r ?>]" value="<?= (int)$ptsVal ?>" class="form-control form-control-sm text-center point-input">
                                                    </td>
                                                <?php endfor; ?>
                                                <td>
                                                    <button type="submit"
                                                       formaction="<?= e(path_url('/admin/events/' . $event['id'] . '/colors/' . $cid . '/delete')) ?>"
                                                       formnovalidate
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('با حذف این رنگ، تگ‌های مرتبط بدون رنگ می‌شوند و تغییرات ذخیره‌نشده جدول از بین می‌رود. ادامه می‌دهید؟');">
                                                        حذف
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer d-flex-end p-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    💾 ذخیره کل جدول امتیازات و رنگ‌ها
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($currentTab === 'settings'): ?>
        <!-- ==================== تب ۱: تنظیمات ==================== -->
        <div class="settings-tab">
            <div class="card max-w-700">
                <div class="card-header"><h3 class="card-title">تنظیمات اصلی رویداد</h3></div>
                <div class="card-body">
                    <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/settings/update')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label for="event_name_edit" class="form-label">نام رویداد *</label>
                            <input type="text" id="event_name_edit" name="name" class="form-control" value="<?= e($event['name']) ?>" required>
                        </div>

                        <!-- بخش آپلود لوگوی سازمان با قابلیت انطباق رنگی با تگ‌ها -->
                        <div class="logo-setting-box p-3 my-3" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: #1e293b;">🏢 لوگوی سازمان یا رویداد</h4>
                            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                                این لوگو در بالای صفحه اسکن نمایش داده می‌شود و به‌صورت پویا با رنگ تگ اسکن‌شده (مانند هاله طلایی برای تگ طلایی) جلوه و درخشش گلسمورفیسم پیدا می‌کند.
                            </p>

                            <?php if (!empty($event['logo_path']) && file_exists(__DIR__ . '/../' . $event['logo_path'])): ?>
                                <div class="current-logo-preview mb-3" style="display: flex; align-items: center; gap: 1rem; background: #fff; padding: 0.75rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <div style="width: 70px; height: 70px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; padding: 5px;">
                                        <img src="<?= e(path_url('/' . $event['logo_path'])) ?>" alt="لوگوی فعلی" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                    </div>
                                    <div>
                                        <span class="badge badge-info" style="margin-bottom: 0.35rem; display: inline-block;">لوگوی اختصاصی بارگذاری شده</span>
                                        <label class="custom-control-label text-danger" style="display: block; font-size: 0.85rem; cursor: pointer;">
                                            <input type="checkbox" name="delete_logo" value="1" class="custom-checkbox">
                                            🗑️ حذف لوگوی فعلی
                                        </label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted" style="font-size: 0.85rem;">هنوز لوگویی بارگذاری نشده است (نماد استاندارد با ترکیب رنگ تگ‌ها نمایش داده می‌شود).</p>
                            <?php endif; ?>

                            <div class="form-group mb-3">
                                <label for="event_logo_input" class="form-label" style="font-size: 0.9rem;">انتخاب فایل جدید لوگو (PNG, JPG, WEBP - حداکثر ۳ مگابایت):</label>
                                <input type="file" id="event_logo_input" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                                <small class="text-muted">پیشنهاد: تصاویر PNG با پس‌زمینه شفاف زیباترین هماهنگی را با طراحی شیشه‌ای (Glassmorphism) ایجاد می‌کنند. (فایل SVG به دلایل امنیتی پذیرفته نمی‌شود.)</small>
                            </div>

                            <div class="form-group mb-0">
                                <label class="custom-control-label" style="font-size: 0.88rem;">
                                    <input type="checkbox" name="logo_tint_mode" value="1" <?= (!isset($event['logo_tint_mode']) || (int)$event['logo_tint_mode'] === 1) ? 'checked' : '' ?> class="custom-checkbox">
                                    انطباق هوشمند رنگ و هاله نوری لوگو با رنگ تگ اسکن‌شده
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="positions_count_edit" class="form-label">تعداد جایگاه‌های مجاز برای هر تگ (۱ تا ۱۰)</label>
                            <input type="number" id="positions_count_edit" name="positions_count" class="form-control" min="1" max="10" value="<?= $positionsCount ?>" required>
                            <small class="text-muted">توجه: کاهش این عدد به مقداری کمتر از بیشترین جایگاه ثبت‌شده فعلی در مسابقه مجاز نمی‌باشد.</small>
                        </div>

                        <div class="form-group">
                            <label class="custom-control-label">
                                <input type="checkbox" name="show_result_to_scanner" value="1" <?= ((int)$event['show_result_to_scanner'] === 1) ? 'checked' : '' ?> class="custom-checkbox">
                                نمایش رتبه و امتیاز به اسکن‌کننده تگ
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="custom-control-label">
                                <input type="checkbox" name="is_active" value="1" <?= ((int)$event['is_active'] === 1) ? 'checked' : '' ?> class="custom-checkbox">
                                رویداد فعال باشد (در صورت غیرفعال بودن، پیام «رویداد غیرفعال است» نمایش داده می‌شود)
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="custom-control-label">
                                <input type="checkbox" name="public_board" value="1" <?= (!empty($event['public_board']) && (int)$event['public_board'] === 1) ? 'checked' : '' ?> class="custom-checkbox">
                                نمایش عمومی لیدربورد (صفحه جدول رده‌بندی زنده برای شرکت‌کنندگان و نمایش روی پروژکتور، همراه با لینک آن در صفحه اسکن تگ‌ها)
                            </label>
                            <small class="text-muted d-block">در صورت خاموش بودن، جدول رده‌بندی فقط در پنل مدیریت دیده می‌شود.</small>
                        </div>

                        <div class="form-group">
                            <label class="custom-control-label">
                                <input type="checkbox" name="lock_device_team" value="1" <?= (!empty($event['lock_device_team']) && (int)$event['lock_device_team'] === 1) ? 'checked' : '' ?> class="custom-checkbox">
                                قفل گوشی روی تیم (هر گوشی پس از اولین ثبت، فقط برای همان تیم می‌تواند ثبت کند)
                            </label>
                            <small class="text-muted d-block">جلوی ثبت تگ به نام تیم‌های دیگر برای پر کردن ظرفیت تگ را می‌گیرد. در صورت اشتباه، از تب تیم‌ها می‌توانید قفل گوشی‌ها را آزاد کنید. «پاکسازی ثبت‌ها» قفل گوشی‌ها را هم آزاد می‌کند.</small>
                        </div>

                        <div class="form-actions mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">ذخیره تنظیمات رویداد</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($event['board_token'])):
                $boardFullUrl = url('/board/' . $event['board_token']);
                $boardOn = (int)$event['public_board'] === 1;
            ?>
            <div class="card max-w-700 mt-4">
                <div class="card-header"><h3 class="card-title">🏆 لیدربورد عمومی</h3></div>
                <div class="card-body">
                    <p class="mb-3">
                        وضعیت:
                        <?php if ($boardOn): ?>
                            <span class="badge badge-success">فعال - برای همه قابل مشاهده است</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">غیرفعال - لینک زیر صفحه ۴۰۴ نشان می‌دهد</span>
                        <?php endif; ?>
                    </p>
                    <div class="form-group">
                        <label class="form-label">لینک صفحه لیدربورد عمومی:</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <code class="dir-ltr" style="word-break: break-all; flex: 1;"><?= e($boardFullUrl) ?></code>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-copy" data-text="<?= e($boardFullUrl) ?>">کپی</button>
                            <?php if ($boardOn): ?>
                                <a href="<?= e($boardFullUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">مشاهده</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" action="<?= e(path_url('/admin/events/' . $event['id'] . '/board/regenerate')) ?>" class="inline-form" onsubmit="return confirm('لینک فعلی لیدربورد عمومی باطل می‌شود. ادامه می‌دهید؟');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-danger">🔁 ساخت لینک جدید (ابطال لینک فعلی)</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($currentTab === 'export'): ?>
        <!-- ==================== تب ۶: خروجی ==================== -->
        <div class="export-tab">
            <div class="card max-w-700">
                <div class="card-header"><h3 class="card-title">خروجی اکسل و گزارش‌ها</h3></div>
                <div class="card-body">
                    <p class="mb-4">
                        فایل‌های خروجی با فرمت استاندارد CSV با یونیکد UTF-8 و BOM به همراه هدرهای فارسی تولید می‌شوند و مستقیماً بدون بهم‌ریختگی در نرم‌افزار اکسل (Microsoft Excel) باز می‌شوند.
                    </p>

                    <div class="export-buttons-grid">
                        <div class="export-box">
                            <div class="export-icon">📊</div>
                            <h4 class="export-title">خروجی لیدربورد</h4>
                            <p class="export-desc">شامل رتبه نهایی، نام تیم، مجموع امتیاز و تعداد تگ‌های ثبت‌شده</p>
                            <a href="<?= e(path_url('/admin/events/' . $event['id'] . '/export/leaderboard')) ?>" class="btn btn-success btn-block">
                                📥 دانلود لیدربورد (CSV)
                            </a>
                        </div>

                        <div class="export-box">
                            <div class="export-icon">📝</div>
                            <h4 class="export-title">خروجی ریز ثبت‌ها</h4>
                            <p class="export-desc">شامل تمام لاگ‌های ثبت: نام تیم، تگ، رنگ، رتبه، امتیاز، تاریخ و ساعت دقیق</p>
                            <a href="<?= e(path_url('/admin/events/' . $event['id'] . '/export/claims')) ?>" class="btn btn-primary btn-block">
                                📥 دانلود ریز ثبت‌ها (CSV)
                            </a>
                        </div>

                        <div class="export-box">
                            <div class="export-icon">🏷️</div>
                            <h4 class="export-title">خروجی لینک‌های تگ‌ها</h4>
                            <p class="export-desc">شامل لینک کامل و کد تمام تگ‌ها برای رایت سریع روی چیپ‌های NFC</p>
                            <a href="<?= e(path_url('/admin/events/' . $event['id'] . '/export/tags')) ?>" class="btn btn-secondary btn-block">
                                📥 دانلود لیست تگ‌ها (CSV)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- پنجره مودال نمایش جزئیات ثبت‌های تیم -->
<div id="teamClaimsModal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-box modal-lg">
        <div class="modal-header d-flex-between">
            <h3 class="modal-title">ثبت‌های تیم <span id="teamModalTitle" class="text-primary font-bold"></span></h3>
            <button type="button" id="btnCloseTeamModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <div id="teamClaimsLoading" class="text-center p-3">در حال دریافت اطلاعات...</div>
            <div id="teamClaimsContent" style="display: none;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>نام تگ</th>
                                <th>رنگ</th>
                                <th>جایگاه</th>
                                <th>امتیاز</th>
                                <th>زمان ثبت</th>
                            </tr>
                        </thead>
                        <tbody id="teamClaimsTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="btnDismissTeamModal" class="btn btn-secondary">بستن</button>
        </div>
    </div>
</div>
