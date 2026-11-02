<?php
/**
 * NFC Event Tag Scoring - Printable floor sheets (برگه چاپی طبقات)
 * برای هر طبقه: پلان با پین‌های شماره‌دار (در صورت وجود) و جدول نقطه‌ها با عکس و ستون تیک نصب/جمع‌آوری.
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$zonesByFloor = [];
foreach ($structure['zones'] as $z) {
    $zonesByFloor[(int)$z['floor_id']][] = $z;
}
$spotsByZone = [];
foreach ($structure['spots'] as $s) {
    $spotsByZone[(int)$s['zone_id']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
</head>
<body class="print-page">
    <div class="print-toolbar no-print">
        <div><strong><?= e($event['name']) ?></strong> <span class="text-muted">— <?= e($event['venue_name']) ?></span><?= $assignment ? ' <span class="badge badge-info">محدوده من</span>' : '' ?></div>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ چاپ</button>
        <a href="<?= e(path_url('/admin/field/event/' . (int)$event['id'])) ?>" class="btn btn-secondary">بازگشت</a>
    </div>

    <?php $num = 0; foreach ($structure['floors'] as $f):
        $fid = (int)$f['id'];
        if ($floor_filter && $floor_filter !== $fid) continue;
        $rows = [];
        foreach (isset($zonesByFloor[$fid]) ? $zonesByFloor[$fid] : [] as $z) {
            if ($assignment && !field_in_assignment($assignment, $fid, $z['id'])) continue;
            foreach (isset($spotsByZone[(int)$z['id']]) ? $spotsByZone[(int)$z['id']] : [] as $s) {
                $rows[] = ['zone' => $z, 'spot' => $s, 'tag' => isset($tag_by_spot[$s['id']]) ? $tag_by_spot[$s['id']] : null, 'n' => ++$num];
            }
        }
        if (empty($rows)) continue;
        $planUrl = floor_plan_url($f);
        $hasSketch = !$planUrl && !empty($f['sketch_json']);
    ?>
        <section class="print-floor">
            <h2>🏢 <?= e($f['name']) ?> <small class="text-muted">(<?= fa_num(count($rows)) ?> نقطه)</small></h2>

            <?php if ($planUrl || $hasSketch): ?>
                <div class="plan-box plan-print">
                    <?php if ($planUrl): ?>
                        <img src="<?= e($planUrl) ?>" alt="پلان <?= e($f['name']) ?>" class="plan-bg">
                    <?php else: ?>
                        <?= render_sketch_svg($f['sketch_json']) ?>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): if ($r['spot']['plan_x'] === null || $r['spot']['plan_x'] === '') continue; ?>
                        <span class="plan-pin plan-pin-num" style="right: auto; left: <?= (float)$r['spot']['plan_x'] ?>%; top: <?= (float)$r['spot']['plan_y'] ?>%;"><?= fa_num($r['n']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <table class="print-table">
                <thead>
                    <tr><th>#</th><th>بخش</th><th>نقطه و توضیح</th><th>عکس</th><th>تگ</th><th>نصب</th><th>جمع‌آوری</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): $s = $r['spot']; $thumb = spot_photo_url($s, 'near', true) ?: spot_photo_url($s, 'wide', true); ?>
                        <tr>
                            <td class="font-bold"><?= fa_num($r['n']) ?></td>
                            <td><?= e($r['zone']['name']) ?></td>
                            <td><strong><?= e($s['name']) ?></strong><?= !empty($s['description']) ? '<br><small>' . e($s['description']) . '</small>' : '' ?></td>
                            <td class="print-thumb"><?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt=""><?php endif; ?></td>
                            <td><?= $r['tag'] ? e($r['tag']['name']) . '<br><small>' . e(tag_status_label($r['tag']['status'])) . '</small>' : '<small class="text-muted">—</small>' ?></td>
                            <td class="print-check">☐</td>
                            <td class="print-check">☐</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
    <?php if ($num === 0): ?>
        <p class="text-center text-muted p-4">نقطه‌ای برای چاپ وجود ندارد.</p>
    <?php endif; ?>
</body>
</html>
