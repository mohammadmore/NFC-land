<?php
/**
 * NFC Event Tag Scoring - Scanner Page View
 * Ultra-Modern Glassmorphism UI with Dynamic Tag Color Gradients & Motivational Quotes
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

$colorHex = normalize_hex(isset($tag['color_hex']) ? $tag['color_hex'] : '', '#eab308');
$colorName = !empty($tag['color_name']) ? $tag['color_name'] : 'طلایی ویژه';
$palette = !empty($palette) ? $palette : get_tag_color_palette($colorHex);
$logoPath = !empty($tag['logo_path']) ? $tag['logo_path'] : null;
$logoTintMode = isset($tag['logo_tint_mode']) ? (int)$tag['logo_tint_mode'] : 1;
$hasCustomLogo = !empty($logoPath) && strpos($logoPath, 'assets/uploads/') === 0 && file_exists(__DIR__ . '/../' . $logoPath);

// پیام انگیزشی فقط پس از ثبت موفق نمایش داده می‌شود
$activeQuote = (!empty($just_claimed) && !empty($flash_status['motivation_quote'])) ? $flash_status['motivation_quote'] : '';
$showResult = (int)$tag['show_result_to_scanner'] === 1;

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
                        <div class="scan-logo-overlay-wrapper<?= $logoTintMode === 1 ? '' : ' no-tint' ?>" style="--logo-url: url('<?= e($logoFullUrl) ?>');">
                            <!-- تصویر پایه لوگو -->
                            <img src="<?= e($logoFullUrl) ?>" alt="لوگوی سازمان" class="scan-org-logo-img">
                            <!-- لایه Color Overlay دقیق به رنگ تگ اسکن‌شده -->
                            <?php if ($logoTintMode === 1): ?>
                                <div class="scan-logo-color-overlay"></div>
                            <?php endif; ?>
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

            <div class="scan-card-body">
                <?php if (!empty($flash_status) && empty($just_claimed)): ?>
                    <?php
                        $fType = isset($flash_status['type']) && in_array($flash_status['type'], ['error', 'warning', 'info'], true) ? $flash_status['type'] : 'info';
                        $fMsg = isset($flash_status['message']) ? $flash_status['message'] : '';
                    ?>
                    <div class="status-box status-<?= e($fType) ?> glass-status-box" role="alert">
                        <div class="status-icon"><?= $fType === 'error' ? '✕' : ($fType === 'warning' ? '⚠' : 'ℹ') ?></div>
                        <div class="status-text"><?= e($fMsg) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($is_inactive)): ?>
                    <!-- رویداد غیرفعال است -->
                    <div class="status-box status-warning glass-status-box">
                        <div class="status-icon">🔒</div>
                        <div class="status-text"><?= e($status_message) ?></div>
                    </div>

                <?php elseif (!empty($just_claimed)): ?>
                    <!-- نتیجه ثبت موفق (بدون نمایش دوباره فرم) -->
                    <div class="scan-result scan-result-success" role="status">
                        <div class="scan-result-icon">🎉</div>
                        <div class="scan-result-text"><?= e($flash_status['message']) ?></div>
                    </div>
                    <?php if ($activeQuote !== ''): ?>
                        <div class="glass-motivation-card">
                            <div class="motivation-sparkle-icon">✨</div>
                            <div class="motivation-body">
                                <p class="motivation-quote-text"><?= e($activeQuote) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <p class="scan-next-hint">می‌توانید این صفحه را ببندید و سراغ تگ بعدی بروید.</p>

                <?php elseif (!empty($my_claim)): ?>
                    <!-- تیم این گوشی قبلاً همین تگ را ثبت کرده است -->
                    <div class="scan-result scan-result-done" role="status">
                        <div class="scan-result-icon">✅</div>
                        <div class="scan-result-text">
                            «<?= e($my_claim['team_name']) ?>» این تگ را قبلاً ثبت کرده است<?php if ($showResult): ?> (<?= e(rank_to_words_fa($my_claim['claim_rank'])) ?> تیم، <?= fa_num($my_claim['points']) ?> امتیاز)<?php endif; ?>.
                        </div>
                    </div>
                    <p class="scan-next-hint">نیازی به ثبت دوباره نیست؛ سراغ تگ بعدی بروید.</p>

                <?php elseif (!empty($is_full)): ?>
                    <!-- ظرفیت تگ تکمیل است -->
                    <div class="status-box status-warning glass-status-box">
                        <div class="status-icon">🛑</div>
                        <div class="status-text">
                            این تگ قبلاً توسط <strong><?= fa_num($claims_count) ?></strong> تیم اسکن شده و ظرفیت امتیازگیری آن تکمیل شده است.
                        </div>
                    </div>

                <?php elseif (!empty($can_claim)): ?>
                    <div class="claim-form-section">
                        <form id="claimForm" method="POST" action="<?= e(path_url('/' . $tag['code'])) ?>" class="claim-form">
                            <?= csrf_field() ?>

                            <?php if (!empty($locked_team)): ?>
                                <input type="hidden" name="team_id" id="teamIdInput" value="<?= (int)$locked_team['team_id'] ?>">
                                <p class="claim-instruction">این گوشی برای «<strong><?= e($locked_team['team_name']) ?></strong>» ثبت شده است.</p>
                                <button type="button" class="btn btn-tag-gradient btn-block btn-lg team-pick-locked" data-team-id="<?= (int)$locked_team['team_id'] ?>" data-team-name="<?= e($locked_team['team_name']) ?>">
                                    <span class="btn-shine"></span>
                                    <span class="btn-text">ثبت این تگ برای «<?= e($locked_team['team_name']) ?>»</span>
                                </button>
                            <?php elseif (empty($teams)): ?>
                                <div class="status-box status-info glass-status-box">
                                    <div class="status-icon">ℹ</div>
                                    <div class="status-text">هنوز تیمی برای این رویداد تعریف نشده است. لطفاً به برگزارکننده اطلاع دهید.</div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="team_id" id="teamIdInput" value="">
                                <p class="claim-instruction">تیم خود را انتخاب کنید:</p>
                                <?php if (!empty($device_lock_enabled)): ?>
                                    <p class="claim-note">⚠️ این گوشی پس از اولین ثبت، فقط برای همان تیم می‌تواند ثبت کند.</p>
                                <?php endif; ?>
                                <?php if (count($teams) > 6): ?>
                                    <input type="search" id="teamSearch" class="form-control glass-input" placeholder="🔍 جستجوی نام تیم..." autocomplete="off" aria-label="جستجوی نام تیم">
                                <?php endif; ?>
                                <div class="team-pick-list" id="teamPickList" role="list">
                                    <?php foreach ($teams as $t): ?>
                                        <button type="button" class="team-pick" role="listitem" data-team-id="<?= (int)$t['id'] ?>" data-team-name="<?= e($t['name']) ?>">
                                            <span class="team-pick-name"><?= e($t['name']) ?></span>
                                            <span class="team-pick-arrow">←</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <p id="teamSearchEmpty" class="claim-note" style="display: none;">تیمی با این نام پیدا نشد.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (!empty($board_url)): ?>
                    <div class="scan-board-link">
                        <a href="<?= e($board_url) ?>" class="btn btn-glass-secondary btn-block">🏆 مشاهده جدول رده‌بندی زنده</a>
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
                این تگ برای این تیم ثبت شود؟
            </p>
            <div class="modal-team-highlight">
                <span id="modalTeamName" class="highlight-team-text"></span>
            </div>
            <div class="modal-alert-box">
                <span class="modal-alert-icon">⚠️</span>
                <span>توجه: پس از تأیید، این تگ به نام این تیم ثبت شده و قابل تغییر نخواهد بود.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="btnConfirmYes" class="btn btn-tag-gradient btn-lg flex-1">
                بله، ثبت کن
            </button>
            <button type="button" id="btnConfirmCancel" class="btn btn-glass-secondary btn-lg btn-nowrap">
                انصراف
            </button>
        </div>
    </div>
</div>
