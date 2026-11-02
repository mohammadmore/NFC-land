<?php
/**
 * NFC Event Tag Scoring - Public Live Leaderboard View
 * فقط در صورتی نمایش داده می‌شود که ادمین «نمایش عمومی لیدربورد» را فعال کرده باشد.
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$extra_scripts = ['/assets/board.js'];
$theme_palette = event_brand_palette($board_event);
$body_class = 'is-board-view';
?>

<div class="board-wrapper">
    <div class="card board-card">
        <div class="card-header board-header">
            <div>
                <h1 class="card-title board-title">🏆 جدول رده‌بندی</h1>
                <div class="board-event-name"><?= e($board_event['name']) ?></div>
            </div>
            <div class="board-live" aria-live="polite">
                <span class="board-live-dot"></span>
                <span>زنده</span>
                <span class="text-muted">· به‌روزرسانی: <span id="boardUpdated">—</span></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table board-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;" class="text-center">رتبه</th>
                            <th>تیم</th>
                            <th class="text-center">امتیاز</th>
                            <th class="text-center">تگ‌ها</th>
                        </tr>
                    </thead>
                    <tbody id="boardTbody" data-src="<?= e(path_url('/board/' . $board_event['board_token'] . '/data')) ?>">
                        <?php if (empty($board)): ?>
                            <tr><td colspan="4" class="text-center text-muted p-4">هنوز تیمی در این رویداد ثبت نشده است.</td></tr>
                        <?php else: ?>
                            <?php foreach ($board as $row): ?>
                                <tr class="<?= $row['rank'] <= 3 ? 'board-top board-top-' . (int)$row['rank'] : '' ?>">
                                    <td class="text-center font-bold board-rank"><?= $row['rank'] === 1 ? '🥇' : ($row['rank'] === 2 ? '🥈' : ($row['rank'] === 3 ? '🥉' : fa_num($row['rank']))) ?></td>
                                    <td class="font-bold"><?= e($row['team']) ?></td>
                                    <td class="text-center"><span class="points-badge"><?= fa_num($row['points']) ?></span></td>
                                    <td class="text-center"><?= fa_num($row['tags']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
