/**
 * NFC Event Tag Scoring - Scanner Client Script
 * انتخاب تیم با لمس (لیست دکمه‌ای + جستجو)، پاپ‌آپ تأیید نهایی و جلوگیری از ارسال تکراری
 */

document.addEventListener('DOMContentLoaded', function () {
    var claimForm = document.getElementById('claimForm');
    var teamIdInput = document.getElementById('teamIdInput');
    var confirmModal = document.getElementById('confirmModal');
    var modalTeamName = document.getElementById('modalTeamName');
    var btnConfirmYes = document.getElementById('btnConfirmYes');
    var btnConfirmCancel = document.getElementById('btnConfirmCancel');
    var teamSearch = document.getElementById('teamSearch');
    var teamSearchEmpty = document.getElementById('teamSearchEmpty');

    if (!claimForm || !teamIdInput || !confirmModal || !btnConfirmYes || !btnConfirmCancel) {
        return;
    }

    // انتقال مودال به ریشه body تا هیچ نگهدارنده یا اسکرولی مانع نمایش تمام‌صفحه آن نشود
    if (confirmModal.parentElement !== document.body) {
        document.body.appendChild(confirmModal);
    }

    var isSubmitting = false;
    var lastTrigger = null;

    function openConfirm(teamId, teamName, trigger) {
        teamIdInput.value = teamId;
        if (modalTeamName) {
            modalTeamName.textContent = teamName;
        }
        lastTrigger = trigger || null;
        confirmModal.style.display = 'flex';
        document.body.classList.add('modal-open');
        btnConfirmYes.focus();
    }

    function closeConfirm() {
        if (isSubmitting) return;
        confirmModal.style.display = 'none';
        document.body.classList.remove('modal-open');
        if (!claimForm.querySelector('.team-pick-locked')) {
            teamIdInput.value = '';
        }
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    // لمس نام تیم (یا دکمه تیم قفل‌شده) پاپ‌آپ تأیید را باز می‌کند
    claimForm.addEventListener('click', function (e) {
        var btn = e.target.closest('.team-pick, .team-pick-locked');
        if (!btn || isSubmitting) return;
        e.preventDefault();
        openConfirm(btn.getAttribute('data-team-id'), btn.getAttribute('data-team-name') || '', btn);
    });

    // جلوگیری از ارسال فرم با Enter در کادر جستجو
    claimForm.addEventListener('submit', function (e) {
        if (!isSubmitting) e.preventDefault();
    });

    // جستجوی نام تیم (با یکسان‌سازی ی/ک عربی و فارسی)
    if (teamSearch) {
        var normalize = function (str) {
            return String(str || '')
                .replace(/ي/g, 'ی').replace(/ى/g, 'ی').replace(/ك/g, 'ک')
                .replace(/‌/g, ' ').replace(/\s+/g, ' ')
                .trim().toLowerCase();
        };
        var picks = Array.prototype.slice.call(claimForm.querySelectorAll('.team-pick'));
        teamSearch.addEventListener('input', function () {
            var q = normalize(teamSearch.value);
            var visible = 0;
            picks.forEach(function (p) {
                var show = q === '' || normalize(p.getAttribute('data-team-name')).indexOf(q) !== -1;
                p.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (teamSearchEmpty) {
                teamSearchEmpty.style.display = visible === 0 ? 'block' : 'none';
            }
        });
        teamSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var firstVisible = picks.filter(function (p) { return p.style.display !== 'none'; });
                if (firstVisible.length === 1) {
                    firstVisible[0].click();
                }
            }
        });
    }

    btnConfirmCancel.addEventListener('click', closeConfirm);

    confirmModal.addEventListener('click', function (e) {
        if (e.target === confirmModal) closeConfirm();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && confirmModal.style.display === 'flex') closeConfirm();
    });

    // تأیید نهایی و ارسال قطعی فرم
    btnConfirmYes.addEventListener('click', function () {
        if (isSubmitting || !teamIdInput.value) return;
        isSubmitting = true;
        btnConfirmYes.disabled = true;
        btnConfirmCancel.disabled = true;
        btnConfirmYes.innerHTML = '<span class="spinner-small"></span> در حال ثبت...';
        claimForm.submit();
    });

    // بازگشت با دکمه Back مرورگر (bfcache): بازنشانی وضعیت دکمه‌ها
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            isSubmitting = false;
            btnConfirmYes.disabled = false;
            btnConfirmCancel.disabled = false;
            btnConfirmYes.textContent = 'بله، ثبت کن';
            closeConfirm();
        }
    });
});
