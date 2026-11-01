/**
 * NFC Event Tag Scoring - Public Leaderboard live refresh (AJAX, بدون بارگذاری مجدد صفحه)
 */
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('boardTbody');
    var updated = document.getElementById('boardUpdated');
    if (!tbody) return;
    var src = tbody.getAttribute('data-src');
    var INTERVAL = 15000;

    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function medal(rank, rankFa) {
        if (rank === 1) return '🥇';
        if (rank === 2) return '🥈';
        if (rank === 3) return '🥉';
        return esc(rankFa);
    }

    function render(rows) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted p-4">هنوز تیمی در این رویداد ثبت نشده است.</td></tr>';
            return;
        }
        var html = '';
        rows.forEach(function (r) {
            var cls = r.rank <= 3 ? 'board-top board-top-' + r.rank : '';
            html += '<tr class="' + cls + '">' +
                '<td class="text-center font-bold board-rank">' + medal(r.rank, r.rank_fa) + '</td>' +
                '<td class="font-bold">' + esc(r.team) + '</td>' +
                '<td class="text-center"><span class="points-badge">' + esc(r.points_fa) + '</span></td>' +
                '<td class="text-center">' + esc(r.tags_fa) + '</td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    function refresh() {
        if (document.hidden) return;
        fetch(src, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.success) return;
                render(data.rows || []);
                if (updated) updated.textContent = data.updated_fa || '';
            })
            .catch(function () { /* خطای موقت شبکه: تلاش بعدی */ });
    }

    refresh();
    setInterval(refresh, INTERVAL);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });
});
