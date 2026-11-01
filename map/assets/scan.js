/**
 * NFC Event Tag Scoring - Scanner Client Script
 * Confirmation modal popup & double-click prevention
 * Enforces explicit team selection by the user without pre-selecting any team
 */

document.addEventListener('DOMContentLoaded', function () {
    const claimForm = document.getElementById('claimForm');
    const teamSelect = document.getElementById('teamSelect');
    const btnSubmitClaim = document.getElementById('btnSubmitClaim');
    const confirmModal = document.getElementById('confirmModal');
    const modalTeamName = document.getElementById('modalTeamName');
    const btnConfirmYes = document.getElementById('btnConfirmYes');
    const btnConfirmCancel = document.getElementById('btnConfirmCancel');
    const teamFeedback = document.getElementById('teamSelectFeedback');

    if (!claimForm || !btnSubmitClaim || !confirmModal) {
        return;
    }

    // انتقال عنصر مودال به ریشه مستقیم body تا هیچ نگهدارنده یا اسکرولی مانع نمایش تمام‌صفحه پاپ‌آپ نشود
    if (confirmModal.parentElement !== document.body) {
        document.body.appendChild(confirmModal);
    }

    let isSubmitting = false;
    // در حالت «قفل گوشی روی تیم»، تیم از قبل مشخص است و منوی انتخاب تیم نمایش داده نمی‌شود
    const lockedTeamInput = document.getElementById('lockedTeamId');

    // توابع کمکی باز و بسته کردن پاپ‌آپ تایید
    function openConfirmPopup(teamName) {
        if (modalTeamName && teamName) {
            modalTeamName.textContent = teamName;
        }
        confirmModal.style.display = 'flex';
        document.body.classList.add('modal-open');
    }

    function closeConfirmPopup() {
        confirmModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    // اطمینان قطعی از اینکه در بدو ورود هیچ تیمی ناخواسته انتخاب نشده باشد
    if (teamSelect) {
        teamSelect.selectedIndex = 0;
        teamSelect.value = '';

        // باز شدن خودکار پاپ‌آپ تایید بلافاصله پس از انتخاب تیم از منوی کشویی
        teamSelect.addEventListener('change', function () {
            const selectedVal = teamSelect.value ? teamSelect.value.trim() : '';
            if (selectedVal && selectedVal !== '' && selectedVal !== '0') {
                teamSelect.classList.remove('is-invalid');
                if (teamFeedback) {
                    teamFeedback.style.display = 'none';
                }
                const selectedOption = teamSelect.options[teamSelect.selectedIndex];
                const teamName = selectedOption ? selectedOption.text : '';
                openConfirmPopup(teamName);
            }
        });
    }

    // لمس دکمه ثبت فرم
    btnSubmitClaim.addEventListener('click', function (e) {
        e.preventDefault();

        if (lockedTeamInput) {
            openConfirmPopup(lockedTeamInput.getAttribute('data-team-name') || '');
            return;
        }

        const selectedVal = teamSelect ? teamSelect.value.trim() : '';
        if (!selectedVal || selectedVal === '' || selectedVal === '0') {
            if (teamSelect) {
                teamSelect.classList.add('is-invalid', 'shake-animation');
                setTimeout(() => {
                    teamSelect.classList.remove('shake-animation');
                }, 600);
                teamSelect.focus();
            }
            if (teamFeedback) {
                teamFeedback.style.display = 'block';
            }
            return;
        }

        const selectedOption = teamSelect.options[teamSelect.selectedIndex];
        const teamName = selectedOption ? selectedOption.text : '';
        openConfirmPopup(teamName);
    });

    // انصراف از مودال و تغییر تیم
    btnConfirmCancel.addEventListener('click', function () {
        closeConfirmPopup();
        if (teamSelect) {
            teamSelect.focus();
        }
    });

    // بستن با لمس پس‌زمینه بیرونی مودال
    confirmModal.addEventListener('click', function (e) {
        if (e.target === confirmModal && !isSubmitting) {
            closeConfirmPopup();
        }
    });

    // تایید نهایی و ارسال قطعی فرم به سرور
    btnConfirmYes.addEventListener('click', function () {
        if (isSubmitting) {
            return;
        }
        isSubmitting = true;

        // غیرفعال‌سازی دکمه‌ها برای جلوگیری از ارسال تکراری و Race Condition
        btnConfirmYes.disabled = true;
        btnConfirmCancel.disabled = true;
        btnConfirmYes.innerHTML = '<span class="spinner-small"></span> در حال ثبت امتیاز...';

        claimForm.submit();
    });

    // بستن پاپ‌آپ با کلید Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && confirmModal.style.display === 'flex' && !isSubmitting) {
            closeConfirmPopup();
        }
    });
});
