<?php
/**
 * NFC Event Tag Scoring - Scanner Page View
 * Ultra-Modern Glassmorphism UI with Dynamic Tag Color Gradients & Motivational Quotes
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

$colorHex = !empty($tag['color_hex']) ? $tag['color_hex'] : '#eab308';
$colorName = !empty($tag['color_name']) ? $tag['color_name'] : 'طلایی ویژه';
$palette = !empty($palette) ? $palette : get_tag_color_palette($colorHex);
$logoPath = !empty($tag['logo_path']) ? $tag['logo_path'] : null;
$logoTintMode = isset($tag['logo_tint_mode']) ? (int)$tag['logo_tint_mode'] : 1;
$hasCustomLogo = !empty($logoPath) && file_exists(__DIR__ . '/../' . $logoPath);

// دریافت پیام انگیزشی (از نتیجه ثبت یا تصادفی برای اسکن فعلی)
$activeQuote = '';
if (!empty($flash_status) && !empty($flash_status['motivation_quote'])) {
    $activeQuote = $flash_status['motivation_quote'];
} elseif (!empty($motivation_quote)) {
    $activeQuote = $motivation_quote;
} elseif (function_exists('get_random_motivation_message')) {
    $activeQuote = get_random_motivation_message();
}

$extra_scripts = ['/assets/scan.js'];
$body_class = 'is-scan-view';
?>

<div class="scan-page-wrapper" style="
    --tag-color: <?= e($palette['hex']) ?>;
    --tag-color-light: <?= e($palette['light']) ?>;
    --tag-color-dark: <?= e($palette['dark']) ?>;
    --tag-glow: <?= e($palette['glow']) ?>;
    --tag-glow-soft: <?= e($palette['glow_soft']) ?>;
    --tag-subtle: <?= e($palette['subtle']) ?>;
    --tag-border: <?= e($palette['border']) ?>;
">
    <!-- افکت‌های نوری پویا و امبینت در پس‌زمینه بر اساس رنگ تگ -->
    <div class="ambient-glow ambient-glow-top"></div>
    <div class="ambient-glow ambient-glow-bottom"></div>

    <div class="scan-container">
        <div class="scan-glass-card">

            <!-- بخش لوگوی سازمان با اعمال Color Overlay همرنگ با تگ -->
            <div class="scan-logo-section">
                <div class="scan-logo-frame">
                    <?php if ($hasCustomLogo): 
                        $logoFullUrl = path_url('/' . $logoPath);
                    ?>
                        <div class="scan-logo-overlay-wrapper" style="--logo-url: url('<?= e($logoFullUrl) ?>');">
                            <!-- تصویر پایه لوگو -->
                            <img src="<?= e($logoFullUrl) ?>" alt="لوگوی سازمان" class="scan-org-logo-img">
                            <!-- لایه Color Overlay دقیق به رنگ تگ اسکن‌شده -->
                            <div class="scan-logo-color-overlay"></div>
                        </div>
                    <?php else: ?>
                        <!-- نشان پیش‌فرض شکیل مسابقه در صورت عدم آپلود لوگوی اختصاصی -->
                        <svg class="scan-default-logo-svg" viewBox="0 0 24 24" fill="none">
                            <defs>
                                <linearGradient id="crestGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="<?= e($palette['light']) ?>" />
                                    <stop offset="100%" stop-color="<?= e($palette['hex']) ?>" />
                                </linearGradient>
                            </defs>
                            <path d="M12 2L4 6v6c0 5.25 3.5 10.15 8 11.35 4.5-1.2 8-6.1 8-11.35V6l-8-4z" fill="url(#crestGrad)" fill-opacity="0.18" stroke="url(#crestGrad)" stroke-width="1.8" />
                            <path d="M9 12l2 2 4-4" stroke="<?= e($palette['light']) ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    <?php endif; ?>
                </div>
            </div>

            <!-- سربرگ تگ و رویداد -->
            <div class="scan-card-header">
                <div class="scan-event-badge"><?= e($tag['event_name']) ?></div>
                <h1 class="scan-tag-title"><?= e($tag['tag_name']) ?></h1>
                
                <!-- نشانگر رنگی و درخشان تگ -->
                <div class="tag-color-pill">
                    <span class="color-gem-dot"></span>
                    <span class="color-gem-label">رنگ تگ: <strong><?= e($colorName) ?></strong></span>
                </div>
            </div>

            <!-- کارت پیام انگیزشی و ترغیب‌کننده -->
            <?php if (!empty($activeQuote)): ?>
                <div class="glass-motivation-card">
                    <div class="motivation-sparkle-icon">✨</div>
                    <div class="motivation-body">
                        <p class="motivation-quote-text"><?= e($activeQuote) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="scan-card-body">
                <!-- نمایش وضعیت پیام فلش یا نتایج ثبت قبلی -->
                <?php if (!empty($flash_status)): ?>
                    <?php 
                        $fType = isset($flash_status['type']) ? $flash_status['type'] : 'info';
                        $fMsg = isset($flash_status['message']) ? $flash_status['message'] : '';
                    ?>
                    <div class="status-box status-<?= e($fType) ?> glass-status-box">
                        <div class="status-icon">
                            <?php if ($fType === 'success'): ?>🎉<?php endif; ?>
                            <?php if ($fType === 'error'): ?>✕<?php endif; ?>
                            <?php if ($fType === 'warning'): ?>⚠<?php endif; ?>
                            <?php if ($fType === 'info'): ?>ℹ<?php endif; ?>
                        </div>
                        <div class="status-text"><?= e($fMsg) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($is_inactive)): ?>
                    <!-- رویداد غیرفعال است -->
                    <div class="status-box status-warning glass-status-box">
                        <div class="status-icon">🔒</div>
                        <div class="status-text"><?= e($status_message) ?></div>
                    </div>

                <?php elseif (!empty($is_full)): ?>
                    <!-- ظرفیت تگ تکمیل است -->
                    <div class="status-box status-warning glass-status-box">
                        <div class="status-icon">🛑</div>
                        <div class="status-text">
                            این تگ قبلاً توسط <strong><?= fa_num($claims_count) ?></strong> تیم دیگر اسکن شده و ظرفیت امتیازگیری آن تکمیل شده است.
                        </div>
                    </div>

                <?php elseif (!empty($can_claim)): ?>
                    <!-- فرم انتخاب تیم و ثبت امتیاز با استایل شیشه‌ای -->
                    <div class="claim-form-section">
                        <p class="claim-instruction">
                            نام تیم خود را مشخص کرده و دکمه ثبت را لمس کنید:
                        </p>

                        <form id="claimForm" method="POST" action="<?= e(path_url('/' . $tag['code'])) ?>" class="claim-form">
                            <?= csrf_field() ?>

                            <div class="form-group mb-4">
                                <label for="teamSelect" class="form-label d-flex-between">
                                    <span>انتخاب تیم:</span>
                                    <span class="required-tag">* الزامی</span>
                                </label>
                                <div class="glass-select-container">
                                    <select name="team_id" id="teamSelect" class="form-control form-select glass-select" required>
                                        <option value="" selected>-- انتخاب تیم (لطفاً تیم خود را انتخاب کنید) --</option>
                                        <?php foreach ($teams as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="teamSelectFeedback" class="field-feedback" style="display: none;">
                                    ⚠️ لطفاً ابتدا نام تیم خود را از لیست بالا انتخاب کنید.
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" id="btnSubmitClaim" class="btn btn-tag-gradient btn-block btn-lg">
                                    <span class="btn-shine"></span>
                                    <span class="btn-text">ثبت این تگ برای تیم</span>
                                    <span class="btn-arrow">←</span>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- پنجره پاپ‌آپ تایید نهایی انتخاب تیم با استایل گلسمورفیسم (Modal Popup) -->
<div id="confirmModal" class="modal-backdrop glass-modal-backdrop" style="display: none;
    --tag-color: <?= e($palette['hex']) ?>;
    --tag-color-light: <?= e($palette['light']) ?>;
    --tag-color-dark: <?= e($palette['dark']) ?>;
    --tag-glow: <?= e($palette['glow']) ?>;
    --tag-glow-soft: <?= e($palette['glow_soft']) ?>;
    --tag-subtle: <?= e($palette['subtle']) ?>;
    --tag-border: <?= e($palette['border']) ?>;
" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box glass-modal-box">
        <div class="modal-header">
            <div class="modal-icon-ring">❓</div>
            <h3 id="modalTitle" class="modal-title">تأیید نهایی انتخاب تیم</h3>
        </div>
        <div class="modal-body">
            <p class="modal-question">
                آیا مطمئن هستید که تیم شما:
            </p>
            <div class="modal-team-highlight">
                <span id="modalTeamName" class="highlight-team-text"></span>
            </div>
            <div class="modal-alert-box">
                <span class="modal-alert-icon">⚠️</span>
                <span>توجه: پس از تأیید نهایی، این تگ به نام تیم شما ثبت شده و قابل تغییر نخواهد بود.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="btnConfirmYes" class="btn btn-tag-gradient btn-lg flex-1">
                بله، ثبت کن
            </button>
            <button type="button" id="btnConfirmCancel" class="btn btn-glass-secondary btn-lg">
                انصراف و تغییر تیم
            </button>
        </div>
    </div>
</div>
