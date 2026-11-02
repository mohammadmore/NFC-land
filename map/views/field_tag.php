<?php
/**
 * NFC Event Tag Scoring - Tag field panel (نصب / تأیید / جمع‌آوری)
 * با لمس تگ توسط کادر لاگین‌شده یا از فهرست کار میدانی باز می‌شود.
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'field';
$extra_scripts = ['/assets/field.js'];
$modes = field_modes();
$tagHex = normalize_hex(isset($tag['color_hex']) ? $tag['color_hex'] : '', '#94a3b8');
$hasVenue = !empty($tag['venue_id']);
$status = $tag['status'];
$fmtUser = function ($name, $display) {
    return $display ?: ($name ?: '—');
};

// داده‌های ساختار محل برای انتخاب طبقه/بخش/نقطه در مرورگر
$jsData = ['floors' => [], 'zones' => [], 'spots' => []];
if ($structure) {
    foreach ($structure['floors'] as $f) {
        $jsData['floors'][] = ['id' => (int)$f['id'], 'name' => $f['name'], 'plan' => floor_plan_background_html($f)];
    }
    foreach ($structure['zones'] as $z) {
        $jsData['zones'][] = ['id' => (int)$z['id'], 'floor_id' => (int)$z['floor_id'], 'name' => $z['name']];
    }
    foreach ($structure['spots'] as $s) {
        $jsData['spots'][] = [
            'id' => (int)$s['id'],
            'zone_id' => (int)$s['zone_id'],
            'name' => $s['name'],
            'desc' => (string)$s['description'],
            'thumb' => spot_photo_url($s, 'near', true) ?: spot_photo_url($s, 'wide', true),
            'occupied_by' => ($s['occupied_tag_id'] && (int)$s['occupied_tag_id'] !== (int)$tag['id']) ? $s['occupied_tag_name'] : null,
            'is_current' => ((int)$s['id'] === (int)$tag['spot_id']),
            'px' => $s['plan_x'] !== null ? (float)$s['plan_x'] : null,
            'py' => $s['plan_y'] !== null ? (float)$s['plan_y'] : null,
        ];
    }
}
$currentFloorId = $spot ? (int)$spot['floor_id'] : 0;
$currentZoneId = $spot ? (int)$spot['zone_id'] : 0;
$returnField = '<input type="hidden" name="return" value="' . e($return_to) . '">';
$actionBase = path_url('/admin/field/tag/' . (int)$tag['id']);
?>

<div class="field-panel" style="--tag-hex: <?= e($tagHex) ?>;">

    <!-- سربرگ تگ -->
    <div class="card field-tag-head mb-3">
        <div class="field-tag-stripe"></div>
        <div class="card-body">
            <div class="d-flex-between" style="gap: 0.5rem; flex-wrap: wrap;">
                <div>
                    <div class="text-muted" style="font-size: 0.85rem;"><?= e($tag['event_name']) ?><?= $hasVenue ? ' · ' . e($tag['venue_name']) : '' ?></div>
                    <h1 class="field-tag-name">
                        <span class="color-dot" style="background-color: <?= e($tagHex) ?>;"></span>
                        <?= e($tag['name']) ?>
                    </h1>
                    <div class="text-muted" style="font-size: 0.85rem;">رنگ: <?= e(!empty($tag['color_name']) ? $tag['color_name'] : 'بدون رنگ') ?></div>
                </div>
                <span class="badge <?= e(tag_status_class($status)) ?> field-status-badge"><?= e(tag_status_label($status)) ?></span>
            </div>

            <p class="mt-2 mb-0" style="font-size: 0.85rem;"><a href="<?= e(path_url('/admin/field/event/' . (int)$tag['event_id'])) ?>">🗺️ نقشه و فهرست کار این رویداد</a></p>

            <div class="field-mode-switch field-mode-switch-sm mt-3" role="group" aria-label="حالت کار">
                <?php foreach ($modes as $mk => $mv): ?>
                    <form method="POST" action="<?= e(path_url('/admin/field/mode')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mode" value="<?= e($mk) ?>">
                        <input type="hidden" name="return" value="<?= e(strtok($panel_url, '?')) ?>">
                        <button type="submit" class="field-mode-btn<?= $mode === $mk ? ' is-active' : '' ?>" aria-pressed="<?= $mode === $mk ? 'true' : 'false' ?>"><?= $mv['icon'] ?> <?= e($mv['label']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($just_replaced):
        $newUrl = url('/' . $tag['code']);
    ?>
        <div class="card mb-3 field-replace-box">
            <div class="card-header"><h2 class="card-title">🔁 لینک جدید این تگ را روی یک تگ نو رایت کنید</h2></div>
            <div class="card-body">
                <div class="copy-url-group">
                    <input type="text" readonly value="<?= e($newUrl) ?>" class="form-control form-control-sm dir-ltr" tabindex="-1">
                    <button type="button" class="btn btn-sm btn-secondary btn-copy" data-text="<?= e($newUrl) ?>">کپی</button>
                </div>
                <button type="button" class="btn btn-primary btn-block mt-2 js-nfc-write-one" data-url="<?= e($newUrl) ?>" style="display: none;">📲 رایت روی تگ نو با همین گوشی</button>
                <p class="text-muted mt-2 mb-0 js-nfc-write-status" style="font-size: 0.85rem;">روی iPhone: لینک را کپی کرده و با اپ NFC Tools رایت کنید، یا به‌جای این روش از یک «تگ رزرو» استفاده کنید. سپس تگ نو را در همین نقطه نصب کنید.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$hasVenue): ?>
        <div class="alert alert-warning">
            <div class="alert-content"><span class="alert-icon">⚠</span>
                <span>برای این رویداد هنوز «محل برگزاری» تعیین نشده است؛ ثبت محل نصب ممکن نیست. <?= $is_admin ? 'آن را در تب تنظیمات رویداد انتخاب یا بسازید.' : 'به مدیر رویداد اطلاع دهید.' ?></span>
            </div>
        </div>
        <?php if ($is_admin): ?>
            <p><a class="btn btn-primary" href="<?= e(path_url('/admin/events/' . (int)$tag['event_id'] . '?tab=settings')) ?>">رفتن به تنظیمات رویداد</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <!-- اقدام اصلی بر اساس حالت کار -->
    <?php if ($mode === 'collect'): ?>
        <div class="card field-primary mb-3">
            <div class="card-body text-center">
                <?php if ($status === 'collected'): ?>
                    <div class="field-done">📦 این تگ جمع‌آوری شده است<?= $tag['collected_at'] ? ' (' . e(format_jalali($tag['collected_at'])) . ' توسط ' . e($fmtUser($tag['collected_by_name'], $tag['collected_by_display'])) . ')' : '' ?>.</div>
                <?php else: ?>
                    <form method="POST" action="<?= e($actionBase . '/collect') ?>">
                        <?= csrf_field() ?><?= $returnField ?>
                        <button type="submit" class="btn btn-success btn-lg btn-block field-big-btn">📦 جمع‌آوری شد</button>
                    </form>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">پس از زدن دکمه، تگ را از محل بردارید.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($mode === 'verify'): ?>
        <div class="card field-primary mb-3">
            <div class="card-body text-center">
                <?php if (!$spot): ?>
                    <div class="field-warn">این تگ هنوز نصب نشده است. حالت «نصب» را انتخاب کنید و محل آن را ثبت کنید.</div>
                <?php elseif ($status === 'verified'): ?>
                    <div class="field-done">✔️ تأییدشده<?= $tag['verified_at'] ? ' (' . e(format_jalali($tag['verified_at'])) . ' توسط ' . e($fmtUser($tag['verified_by_name'], $tag['verified_by_display'])) . ')' : '' ?></div>
                <?php else: ?>
                    <form method="POST" action="<?= e($actionBase . '/verify') ?>">
                        <?= csrf_field() ?><?= $returnField ?>
                        <button type="submit" class="btn btn-success btn-lg btn-block field-big-btn">✔️ تأیید: تگ سالم و در جای خود است</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- محل فعلی تگ -->
    <?php if ($spot): ?>
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">📍 محل نصب</h2></div>
            <div class="card-body">
                <div class="field-location-path">
                    <span><?= e($spot['floor_name']) ?></span> ›
                    <span><?= e($spot['zone_name']) ?></span> ›
                    <strong><?= e($spot['name']) ?></strong>
                </div>
                <?php if (!empty($spot['description'])): ?>
                    <p class="field-location-desc"><?= nl2br(e($spot['description'])) ?></p>
                <?php endif; ?>

                <?php
                $spotFloor = null;
                foreach ($structure ? $structure['floors'] : [] as $sf) {
                    if ((int)$sf['id'] === (int)$spot['floor_id']) { $spotFloor = $sf; }
                }
                $spotPlanBg = $spotFloor ? floor_plan_background_html($spotFloor) : '';
                if ($spotPlanBg !== '' && $spot['plan_x'] !== null): ?>
                    <div class="plan-box mt-2">
                        <?= $spotPlanBg ?>
                        <span class="plan-pin plan-pin-current" style="left: <?= (float)$spot['plan_x'] ?>%; top: <?= (float)$spot['plan_y'] ?>%;" title="<?= e($spot['name']) ?>"></span>
                    </div>
                <?php endif; ?>

                <div class="field-photos">
                    <?php foreach (['near' => 'عکس نزدیک', 'wide' => 'عکس باز (فضای اطراف)'] as $pk => $pl):
                        $thumb = spot_photo_url($spot, $pk, true);
                        $full = spot_photo_url($spot, $pk, false);
                    ?>
                        <figure class="field-photo">
                            <?php if ($thumb): ?>
                                <a href="<?= e($full) ?>" target="_blank" rel="noopener"><img src="<?= e($thumb) ?>" alt="<?= e($pl) ?>" loading="lazy"></a>
                            <?php else: ?>
                                <div class="field-photo-empty">بدون عکس</div>
                            <?php endif; ?>
                            <figcaption><?= e($pl) ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>

                <ul class="field-meta">
                    <?php if ($tag['placed_at']): ?><li>نصب: <?= e(format_jalali($tag['placed_at'])) ?> · <?= e($fmtUser($tag['placed_by_name'], $tag['placed_by_display'])) ?></li><?php endif; ?>
                    <?php if ($tag['verified_at']): ?><li>تأیید: <?= e(format_jalali($tag['verified_at'])) ?> · <?= e($fmtUser($tag['verified_by_name'], $tag['verified_by_display'])) ?></li><?php endif; ?>
                    <?php if ($tag['collected_at']): ?><li>جمع‌آوری: <?= e(format_jalali($tag['collected_at'])) ?> · <?= e($fmtUser($tag['collected_by_name'], $tag['collected_by_display'])) ?></li><?php endif; ?>
                </ul>

                <details class="field-details">
                    <summary>📷 گرفتن عکس جدید برای این نقطه</summary>
                    <form method="POST" action="<?= e($actionBase . '/photos') ?>" enctype="multipart/form-data" class="js-photo-form mt-2">
                        <?= csrf_field() ?><?= $returnField ?>
                        <?php include __DIR__ . '/partials_field_photos.php'; ?>
                        <button type="submit" class="btn btn-primary btn-block">ذخیره عکس‌ها</button>
                    </form>
                </details>

                <details class="field-details">
                    <summary>✏️ ویرایش نام و توضیح نقطه</summary>
                    <form method="POST" action="<?= e($actionBase . '/spot-edit') ?>" class="mt-2">
                        <?= csrf_field() ?><?= $returnField ?>
                        <div class="form-group">
                            <label class="form-label">نام نقطه</label>
                            <input type="text" name="spot_name" class="form-control" value="<?= e($spot['name']) ?>" maxlength="150" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">توضیح دقیق محل</label>
                            <textarea name="spot_desc" class="form-control" rows="3" maxlength="500"><?= e($spot['description']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">ذخیره</button>
                    </form>
                </details>
            </div>
        </div>
    <?php endif; ?>

    <!-- فرم ثبت نصب / تغییر محل -->
    <?php if ($hasVenue): ?>
        <div class="card mb-3">
            <details class="field-details field-place-details" <?= (!$spot && $mode === 'install') ? 'open' : '' ?>>
                <summary class="card-header"><h2 class="card-title" style="display: inline;"><?= $spot ? '🔁 تغییر محل نصب' : '📍 ثبت محل نصب' ?></h2></summary>
                <div class="card-body">
                    <?php if (empty($structure['floors'])): ?>
                        <p class="text-muted">برای این محل هنوز طبقه‌ای تعریف نشده است. اولین طبقه را همین‌جا اضافه کنید.</p>
                    <?php endif; ?>
                    <form method="POST" action="<?= e($actionBase . '/place') ?>" enctype="multipart/form-data" class="js-photo-form js-place-form"
                          data-structure="<?= e(json_encode($jsData, JSON_UNESCAPED_UNICODE)) ?>"
                          data-floor-url="<?= e(path_url('/admin/field/venue/' . (int)$tag['venue_id'] . '/floors')) ?>"
                          data-zone-url="<?= e(path_url('/admin/field/venue/' . (int)$tag['venue_id'] . '/zones')) ?>"
                          data-current-floor="<?= $currentFloorId ?>" data-current-zone="<?= $currentZoneId ?>">
                        <?= csrf_field() ?><?= $returnField ?>

                        <div class="form-group">
                            <label class="form-label">طبقه</label>
                            <div class="field-select-row">
                                <select name="floor_id" class="form-control form-select js-floor"></select>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-floor">+ طبقه</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">بخش</label>
                            <div class="field-select-row">
                                <select name="zone_id" class="form-control form-select js-zone"></select>
                                <button type="button" class="btn btn-sm btn-outline-primary js-add-zone">+ بخش</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">نقطه</label>
                            <div class="field-spot-list js-spot-list" role="radiogroup"></div>
                            <label class="field-spot-card field-spot-new">
                                <input type="radio" name="spot_id" value="new" class="js-spot-new-radio">
                                <span class="field-spot-thumb">＋</span>
                                <span class="field-spot-info"><strong>نقطه جدید</strong><small>جایی که قبلاً ثبت نشده</small></span>
                            </label>
                        </div>

                        <div class="js-new-spot-fields" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">نام نقطه (اختیاری)</label>
                                <input type="text" name="spot_name" class="form-control" maxlength="150" placeholder="مثال: کنار آسانسور ۲">
                            </div>
                            <div class="form-group">
                                <label class="form-label">توضیح دقیق محل</label>
                                <textarea name="spot_desc" class="form-control" rows="2" maxlength="500" placeholder="مثال: پشت تابلوی نقاشی، ارتفاع حدود ۱ متر"></textarea>
                            </div>
                        </div>

                        <div class="form-group js-plan-picker" style="display: none;">
                            <label class="form-label">🗺️ محل روی پلان طبقه <small class="text-muted">(اختیاری؛ روی پلان بزنید)</small></label>
                            <div class="plan-box plan-pick js-plan-box"></div>
                            <input type="hidden" name="plan_x" class="js-plan-x">
                            <input type="hidden" name="plan_y" class="js-plan-y">
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 js-plan-clear" style="display: none;">پاک کردن علامت</button>
                        </div>

                        <?php include __DIR__ . '/partials_field_photos.php'; ?>

                        <button type="submit" class="btn btn-primary btn-lg btn-block field-big-btn"><?= $spot ? 'ثبت محل جدید' : '📍 ثبت نصب' ?></button>
                    </form>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <!-- تگ خراب یا گم‌شده -->
    <?php if ($spot && in_array($status, ['placed', 'verified', 'missing'], true)): ?>
        <div class="card mb-3">
            <details class="field-details">
                <summary class="card-header"><h2 class="card-title" style="display: inline;">🛠️ این تگ خراب یا گم شده است</h2></summary>
                <div class="card-body">
                    <p style="font-size: 0.9rem;">ثبت‌ها و امتیازهای این تگ و محل نصبش در هر دو روش حفظ می‌شوند.</p>
                    <ol style="font-size: 0.9rem; padding-right: 1.2rem;">
                        <li><strong>با تگ رزرو (همه گوشی‌ها):</strong> یک تگ رایت‌شده و استفاده‌نشده از همین رویداد را لمس کنید و در بخش «جایگزین تگ خراب» آن، همین تگ را انتخاب کنید.</li>
                        <li><strong>با کد جدید (نیاز به رایت):</strong> کد این تگ عوض می‌شود و تگ فعلی فوراً باطل می‌شود؛ لینک جدید باید روی یک تگ نو رایت شود.</li>
                    </ol>
                    <form method="POST" action="<?= e($actionBase . '/replace-code') ?>" onsubmit="return confirm('کد این تگ عوض شود؟ تگ فیزیکی فعلی دیگر کار نخواهد کرد.');">
                        <?= csrf_field() ?><?= $returnField ?>
                        <button type="submit" class="btn btn-outline-primary btn-block">🔁 ساخت کد جدید برای رایت روی تگ نو</button>
                    </form>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <?php if (!empty($replace_targets)): ?>
        <div class="card mb-3">
            <details class="field-details">
                <summary class="card-header"><h2 class="card-title" style="display: inline;">♻️ جایگزین تگ خراب (استفاده به‌عنوان تگ رزرو)</h2></summary>
                <div class="card-body">
                    <p style="font-size: 0.9rem;">این تگ هنوز نصب نشده است. اگر یکی از تگ‌های نصب‌شده خراب یا گم شده، آن را انتخاب کنید: این تگ جای آن را می‌گیرد (با همان محل، ثبت‌ها و امتیازها) و تگ قبلی باطل می‌شود.</p>
                    <form method="POST" action="<?= e($actionBase . '/adopt') ?>" onsubmit="return confirm('این تگ جایگزین تگ انتخاب‌شده شود؟ تگ قبلی باطل خواهد شد.');">
                        <?= csrf_field() ?><?= $returnField ?>
                        <div class="form-group">
                            <select name="target_tag_id" class="form-control form-select" required>
                                <option value="">— تگ خراب را انتخاب کنید —</option>
                                <?php foreach ($replace_targets as $rt): ?>
                                    <option value="<?= (int)$rt['id'] ?>"><?= e($rt['name']) ?> — <?= e($rt['floor_name']) ?> › <?= e($rt['zone_name']) ?> › <?= e($rt['spot_name']) ?><?= $rt['status'] === 'missing' ? ' (گم‌شده)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">♻️ جایگزین کن</button>
                    </form>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
        <!-- اطلاعات مخصوص مدیر -->
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">🏆 ثبت‌های تیم‌ها (<?= fa_num(count($claims)) ?> از <?= fa_num($tag['positions_count']) ?>)</h2></div>
            <div class="card-body">
                <?php if (empty($claims)): ?>
                    <p class="text-muted mb-0">هنوز تیمی این تگ را ثبت نکرده است.</p>
                <?php else: ?>
                    <ul class="field-meta">
                        <?php foreach ($claims as $c): ?>
                            <li><?= e(rank_to_words_fa($c['claim_rank'])) ?>: <strong><?= e($c['team_name']) ?></strong> · <?= e(format_jalali($c['created_at'])) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="field-admin-actions mt-3">
                    <a class="btn btn-sm btn-secondary" href="<?= e(path_url('/' . $tag['code']) . '?as=player') ?>">👁️ باز کردن به‌عنوان شرکت‌کننده</a>
                    <a class="btn btn-sm btn-secondary" href="<?= e(path_url('/admin/events/' . (int)$tag['event_id'] . '?tab=tags')) ?>">مدیریت تگ‌های رویداد</a>
                    <?php if ($status !== 'missing'): ?>
                        <form method="POST" action="<?= e($actionBase . '/missing') ?>" class="inline-form" onsubmit="return confirm('این تگ گم‌شده ثبت شود؟');">
                            <?= csrf_field() ?><?= $returnField ?>
                            <button type="submit" class="btn btn-sm btn-danger">ثبت گم‌شده</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($spot): ?>
                        <form method="POST" action="<?= e($actionBase . '/unassign') ?>" class="inline-form" onsubmit="return confirm('محل این تگ حذف شود و به «آماده نصب» برگردد؟ (نقطه و عکس‌هایش باقی می‌مانند)');">
                            <?= csrf_field() ?><?= $returnField ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">حذف محل نصب</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($log)): ?>
        <div class="card mb-3">
            <details class="field-details">
                <summary class="card-header"><h2 class="card-title" style="display: inline;">🕓 تاریخچه</h2></summary>
                <div class="card-body">
                    <ul class="field-meta">
                        <?php foreach ($log as $l): ?>
                            <li><?= e(format_jalali($l['created_at'])) ?> · <strong><?= e(tag_log_action_label($l['action'])) ?></strong> · <?= e($fmtUser($l['username'], $l['display_name'])) ?><?= $l['note'] ? ' · ' . e($l['note']) : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        </div>
    <?php endif; ?>
</div>
