<?php
/**
 * یک ردیف تگ در نقشه بخشی رویداد ($t، $claims_by_tag، $stale، $is_admin از قالب والد)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$rowThumb = !empty($t['spot_id']) ? (spot_photo_url(['id' => $t['spot_id'], 'photo_near' => $t['photo_near'], 'photo_wide' => $t['photo_wide']], 'near', true)
    ?: spot_photo_url(['id' => $t['spot_id'], 'photo_near' => $t['photo_near'], 'photo_wide' => $t['photo_wide']], 'wide', true)) : null;
$rowClaims = isset($claims_by_tag[$t['id']]) ? $claims_by_tag[$t['id']] : null;
$rowStale = isset($stale[$t['id']]);
?>
<a href="<?= e(path_url('/admin/field/tag/' . (int)$t['id'])) ?>" class="field-tag-row js-search-item<?= $rowStale ? ' is-stale' : '' ?>"
   data-search="<?= e($t['name'] . ' ' . $t['spot_name'] . ' ' . $t['spot_desc']) ?>">
    <span class="field-tag-row-thumb"><?php if ($rowThumb): ?><img src="<?= e($rowThumb) ?>" alt="" loading="lazy"><?php else: ?>🏷️<?php endif; ?></span>
    <span class="field-tag-row-info">
        <strong><span class="color-dot" style="background-color: <?= e(normalize_hex($t['color_hex'], '#94a3b8')) ?>;"></span> <?= e($t['name']) ?></strong>
        <?php if (!empty($t['spot_name'])): ?><small><?= e($t['spot_name']) ?><?= !empty($t['spot_desc']) ? ' — ' . e($t['spot_desc']) : '' ?></small><?php endif; ?>
        <?php if ($is_admin): ?>
            <small class="text-muted">
                <?= $rowClaims ? fa_num($rowClaims['count']) . ' ثبت · آخرین: ' . e(format_jalali($rowClaims['last'])) : 'بدون ثبت' ?>
                <?php if (!empty($t['placed_by_name'])): ?> · نصاب: <?= e($t['placed_by_display'] ?: $t['placed_by_name']) ?><?php endif; ?>
            </small>
        <?php endif; ?>
        <?php if ($rowStale): ?><small class="text-danger">⚠️ مدتی اسکن نشده در حالی که تگ‌های اطرافش ثبت شده‌اند؛ سالم بودنش را بررسی کنید.</small><?php endif; ?>
    </span>
    <span class="badge <?= e(tag_status_class($t['status'])) ?>"><?= e(tag_status_label($t['status'])) ?></span>
</a>
