/**
 * NFC Event Tag Scoring - Admin Dashboard Client Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // ۱. کپی کردن پیوند تگ در کلیپ‌بورد
    // ==========================================
    const copyButtons = document.querySelectorAll('.btn-copy');
    copyButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const textToCopy = btn.getAttribute('data-text');
            if (!textToCopy) return;

            const originalText = btn.textContent;

            const fallbackCopy = function () {
                const tempInput = document.createElement('textarea');
                tempInput.value = textToCopy;
                tempInput.style.position = 'fixed';
                tempInput.style.opacity = '0';
                document.body.appendChild(tempInput);
                tempInput.focus();
                tempInput.select();
                try {
                    document.execCommand('copy');
                    btn.textContent = 'کپی شد!';
                    btn.classList.add('btn-success');
                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('btn-success');
                    }, 2000);
                } catch (err) {
                    alert('خطا در کپی پیوند.');
                }
                document.body.removeChild(tempInput);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function () {
                    btn.textContent = 'کپی شد!';
                    btn.classList.add('btn-success');
                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('btn-success');
                    }, 2000);
                }).catch(function () {
                    fallbackCopy();
                });
            } else {
                fallbackCopy();
            }
        });
    });

    // ==========================================
    // ۲. تأیید حذف رویداد با تایپ نام رویداد
    // ==========================================
    const deleteButtons = document.querySelectorAll('.btn-delete-event');
    const deleteEventModal = document.getElementById('deleteEventModal');
    const deleteEventForm = document.getElementById('deleteEventForm');
    const deleteEventTargetName = document.getElementById('deleteEventTargetName');
    const confirmDeleteNameInput = document.getElementById('confirmDeleteNameInput');
    const btnConfirmDeleteEvent = document.getElementById('btnConfirmDeleteEvent');
    const btnCancelDeleteEvent = document.getElementById('btnCancelDeleteEvent');

    if (deleteEventModal && deleteEventForm && confirmDeleteNameInput && btnConfirmDeleteEvent) {
        let expectedName = '';

        deleteButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const eventId = btn.getAttribute('data-id');
                expectedName = btn.getAttribute('data-name');

                deleteEventTargetName.textContent = expectedName;
                deleteEventForm.action = window.location.pathname.replace(/\/$/, '') + '/' + eventId + '/delete';
                confirmDeleteNameInput.value = '';
                btnConfirmDeleteEvent.disabled = true;

                deleteEventModal.style.display = 'flex';
                confirmDeleteNameInput.focus();
            });
        });

        confirmDeleteNameInput.addEventListener('input', function () {
            btnConfirmDeleteEvent.disabled = (confirmDeleteNameInput.value.trim() !== expectedName.trim());
        });

        if (btnCancelDeleteEvent) {
            btnCancelDeleteEvent.addEventListener('click', function () {
                deleteEventModal.style.display = 'none';
            });
        }
    }

    // ==========================================
    // ۳. به‌روزرسانی لیدربورد و رفرش خودکار
    // ==========================================
    const autoRefreshCheckbox = document.getElementById('autoRefreshCheckbox');
    const btnManualRefresh = document.getElementById('btnManualRefresh');

    if (btnManualRefresh) {
        btnManualRefresh.addEventListener('click', function () {
            window.location.reload();
        });
    }

    if (autoRefreshCheckbox) {
        let refreshInterval = null;

        const startAutoRefresh = function () {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(function () {
                if (autoRefreshCheckbox.checked) {
                    window.location.reload();
                }
            }, 20000); // هر ۲۰ ثانیه
        };

        if (autoRefreshCheckbox.checked) {
            startAutoRefresh();
        }

        autoRefreshCheckbox.addEventListener('change', function () {
            if (autoRefreshCheckbox.checked) {
                startAutoRefresh();
            } else if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    }

    // ==========================================
    // ۴. مودال مشاهده ریز ثبت‌های یک تیم در رویداد
    // ==========================================
    const viewTeamButtons = document.querySelectorAll('.btn-view-team-claims');
    const teamClaimsModal = document.getElementById('teamClaimsModal');
    const teamModalTitle = document.getElementById('teamModalTitle');
    const teamClaimsLoading = document.getElementById('teamClaimsLoading');
    const teamClaimsContent = document.getElementById('teamClaimsContent');
    const teamClaimsTbody = document.getElementById('teamClaimsTbody');
    const btnCloseTeamModal = document.getElementById('btnCloseTeamModal');
    const btnDismissTeamModal = document.getElementById('btnDismissTeamModal');

    const closeTeamModal = function () {
        if (teamClaimsModal) teamClaimsModal.style.display = 'none';
    };

    if (btnCloseTeamModal) btnCloseTeamModal.addEventListener('click', closeTeamModal);
    if (btnDismissTeamModal) btnDismissTeamModal.addEventListener('click', closeTeamModal);

    viewTeamButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const teamId = btn.getAttribute('data-team-id');
            const teamName = btn.getAttribute('data-team-name');

            if (!teamClaimsModal) return;

            teamModalTitle.textContent = teamName;
            teamClaimsLoading.style.display = 'block';
            teamClaimsContent.style.display = 'none';
            teamClaimsTbody.innerHTML = '';
            teamClaimsModal.style.display = 'flex';

            // درخواست AJAX برای دریافت ثبت‌ها
            const fetchUrl = window.location.pathname.replace(/\/$/, '') + '/teams/' + teamId + '/claims-json';

            fetch(fetchUrl)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    teamClaimsLoading.style.display = 'none';
                    teamClaimsContent.style.display = 'block';

                    if (!data || !data.claims || data.claims.length === 0) {
                        teamClaimsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">این تیم هنوز تگی ثبت نکرده است.</td></tr>';
                        return;
                    }

                    let rowsHtml = '';
                    data.claims.forEach(function (c) {
                        const colorHex = c.color_hex || '#94a3b8';
                        const colorName = c.color_name || 'بدون رنگ';
                        rowsHtml += '<tr>' +
                            '<td><strong>' + escapeHtml(c.tag_name) + '</strong></td>' +
                            '<td><span class="color-dot" style="background-color: ' + escapeHtml(colorHex) + '"></span> ' + escapeHtml(colorName) + '</td>' +
                            '<td>' + escapeHtml(c.claim_rank_fa) + '</td>' +
                            '<td><span class="points-badge">' + escapeHtml(c.points_fa) + '</span></td>' +
                            '<td>' + escapeHtml(c.claim_time_fa) + '</td>' +
                            '</tr>';
                    });
                    teamClaimsTbody.innerHTML = rowsHtml;
                })
                .catch(function () {
                    teamClaimsLoading.style.display = 'none';
                    teamClaimsContent.style.display = 'block';
                    teamClaimsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">خطا در دریافت اطلاعات ثبت‌های تیم.</td></tr>';
                });
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }
});
