/**
 * NFC Event Tag Scoring - Admin Dashboard Client Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // ۱. کپی کردن پیوند تگ در کلیپ‌بورد
    // ==========================================
    // پس از کپی لینک یک تگ، ردیف بعدی برای کپی/رایت آماده و برجسته می‌شود
    function focusNextTagRow(btn) {
        if (!btn.classList.contains('btn-copy-tag')) return;
        const row = btn.closest('tr');
        if (!row) return;
        row.classList.add('row-done');
        document.querySelectorAll('.tag-row.row-next').forEach(function (r) { r.classList.remove('row-next'); });
        const next = row.nextElementSibling;
        if (next && next.classList.contains('tag-row')) {
            next.classList.add('row-next');
            next.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const nextBtn = next.querySelector('.btn-copy-tag');
            if (nextBtn) setTimeout(function () { nextBtn.focus({ preventScroll: true }); }, 300);
        }
    }

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
                    focusNextTagRow(btn);
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
                    focusNextTagRow(btn);
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
    // ۳. به‌روزرسانی لیدربورد بدون بارگذاری مجدد صفحه (حفظ محل اسکرول)
    // ==========================================
    const autoRefreshCheckbox = document.getElementById('autoRefreshCheckbox');
    const btnManualRefresh = document.getElementById('btnManualRefresh');
    const leaderboardTbody = document.getElementById('leaderboardTbody');
    const claimsByTagBody = document.getElementById('claimsByTagBody');
    const leaderboardUpdated = document.getElementById('leaderboardUpdated');
    let refreshing = false;

    const refreshLeaderboard = function (manual) {
        if (!leaderboardTbody || refreshing) return;
        // هنگام باز بودن مودال جزئیات تیم یا پنهان بودن تب مرورگر، به‌روزرسانی خودکار انجام نمی‌شود
        const modal = document.getElementById('teamClaimsModal');
        if (!manual && ((modal && modal.style.display === 'flex') || document.hidden)) return;
        refreshing = true;
        if (btnManualRefresh) btnManualRefresh.disabled = true;

        fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (res.redirected || !res.ok) throw new Error('reload');
                return res.text();
            })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTbody = doc.getElementById('leaderboardTbody');
                const newClaims = doc.getElementById('claimsByTagBody');
                if (!newTbody) throw new Error('reload');
                leaderboardTbody.innerHTML = newTbody.innerHTML;
                if (claimsByTagBody && newClaims) claimsByTagBody.innerHTML = newClaims.innerHTML;
                if (leaderboardUpdated) {
                    leaderboardUpdated.textContent = '(آخرین به‌روزرسانی: ' + new Date().toLocaleTimeString('fa-IR') + ')';
                }
            })
            .catch(function () {
                // نشست منقضی شده یا خطای شبکه: در به‌روزرسانی دستی صفحه کامل بارگذاری می‌شود
                if (manual) window.location.reload();
            })
            .finally(function () {
                refreshing = false;
                if (btnManualRefresh) btnManualRefresh.disabled = false;
            });
    };

    if (btnManualRefresh) {
        btnManualRefresh.addEventListener('click', function () { refreshLeaderboard(true); });
    }

    if (autoRefreshCheckbox) {
        setInterval(function () {
            if (autoRefreshCheckbox.checked) refreshLeaderboard(false);
        }, 20000); // هر ۲۰ ثانیه
    }

    // ==========================================
    // ۴. مودال مشاهده ریز ثبت‌های یک تیم در رویداد
    // ==========================================
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

    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.btn-view-team-claims');
        if (!btn) return;
        (function () {
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
        })();
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

    // ==========================================
    // ۵. تأیید دکمه‌های حذف/آزادسازی داخل فرم‌های گروهی (data-confirm)
    // ==========================================
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('button[data-confirm]');
        if (!btn) return;
        let msg = btn.getAttribute('data-confirm');
        if (btn.form && btn.form.dataset.dirty === '1') {
            msg += '\n\n⚠️ توجه: تغییرات ذخیره‌نشده جدول از بین می‌رود.';
        }
        if (!window.confirm(msg)) {
            ev.preventDefault();
            return;
        }
        const form = btn.form;
        if (form) form.dataset.submitting = '1';
    });

    // ==========================================
    // ۶. فرم‌های ویرایش گروهی: نمایش تغییرات ذخیره‌نشده و هشدار هنگام خروج از صفحه
    // ==========================================
    document.querySelectorAll('.dirty-track-form').forEach(function (form) {
        const indicator = form.querySelector('.dirty-indicator');
        const saveBtn = form.querySelector('.btn-save-all');
        const fields = Array.prototype.slice.call(form.querySelectorAll('input[type=text], select'))
            .filter(function (f) { return !f.readOnly; });
        const initial = fields.map(function (f) { return f.value; });

        const update = function () {
            let changed = 0;
            fields.forEach(function (f, i) {
                const isChanged = f.value !== initial[i];
                f.classList.toggle('is-changed', isChanged);
                if (isChanged) changed++;
            });
            form.dataset.dirty = changed > 0 ? '1' : '';
            if (indicator) {
                indicator.textContent = changed > 0 ? ('⚠️ ' + changed.toLocaleString('fa-IR') + ' تغییر ذخیره‌نشده') : 'تغییری ذخیره‌نشده وجود ندارد.';
                indicator.classList.toggle('text-danger', changed > 0);
                indicator.classList.toggle('text-muted', changed === 0);
            }
            if (saveBtn) saveBtn.classList.toggle('btn-pulse', changed > 0);
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        form.addEventListener('submit', function () { form.dataset.submitting = '1'; });
    });

    window.addEventListener('beforeunload', function (ev) {
        const dirty = Array.prototype.some.call(document.querySelectorAll('.dirty-track-form'), function (f) {
            return f.dataset.dirty === '1' && f.dataset.submitting !== '1';
        });
        if (dirty) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    // ==========================================
    // ۷. رایت مستقیم لینک تگ روی NFC با Web NFC (Chrome اندروید، فقط روی HTTPS)
    // ==========================================
    if ('NDEFReader' in window) {
        document.querySelectorAll('.nfc-write-only').forEach(function (el) { el.style.display = ''; });

        const statusBox = document.getElementById('nfcWriteStatus');
        const rows = Array.prototype.slice.call(document.querySelectorAll('.tag-row'));
        let abortCtrl = null;

        const setStatus = function (html, type) {
            if (!statusBox) return;
            statusBox.style.display = html ? 'flex' : 'none';
            statusBox.className = 'nfc-write-status' + (type ? ' is-' + type : '');
            statusBox.innerHTML = html;
        };

        const cancelWrite = function () {
            if (abortCtrl) {
                abortCtrl.abort();
                abortCtrl = null;
            }
        };

        const writeRow = function (row) {
            cancelWrite();
            abortCtrl = new AbortController();
            const ndef = new NDEFReader();
            return ndef.write({ records: [{ recordType: 'url', data: row.getAttribute('data-url') }] }, { signal: abortCtrl.signal })
                .then(function () {
                    abortCtrl = null;
                    row.classList.add('row-done');
                    row.classList.remove('row-next');
                    if (navigator.vibrate) navigator.vibrate(120);
                });
        };

        const escapeText = function (t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        };

        // رایت تکی از دکمه هر ردیف
        rows.forEach(function (row) {
            const btn = row.querySelector('.btn-nfc-write');
            if (!btn) return;
            btn.addEventListener('click', function () {
                const name = escapeText(row.getAttribute('data-name'));
                setStatus('📲 گوشی را به تگ «<strong>' + name + '</strong>» نزدیک کنید... <button type="button" class="btn btn-sm btn-secondary" data-nfc-cancel>انصراف</button>', 'wait');
                writeRow(row).then(function () {
                    setStatus('✅ تگ «<strong>' + name + '</strong>» با موفقیت رایت شد.', 'ok');
                }).catch(function (err) {
                    if (err && err.name === 'AbortError') { setStatus('', ''); return; }
                    setStatus('✕ رایت ناموفق بود: ' + escapeText(err && err.message ? err.message : 'خطای نامشخص'), 'error');
                });
            });
        });

        // رایت پشت‌سرهم همه تگ‌ها به ترتیب جدول
        const btnSeq = document.getElementById('btnWriteSequence');
        if (btnSeq) {
            btnSeq.addEventListener('click', function () {
                let i = 0;
                const step = function () {
                    if (i >= rows.length) {
                        setStatus('🎉 رایت همه ' + rows.length.toLocaleString('fa-IR') + ' تگ به پایان رسید.', 'ok');
                        return;
                    }
                    const row = rows[i];
                    rows.forEach(function (r) { r.classList.remove('row-next'); });
                    row.classList.add('row-next');
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setStatus('📲 (' + (i + 1).toLocaleString('fa-IR') + ' از ' + rows.length.toLocaleString('fa-IR') + ') گوشی را به تگ «<strong>' +
                        escapeText(row.getAttribute('data-name')) + '</strong>» نزدیک کنید. ' +
                        '<button type="button" class="btn btn-sm btn-secondary" data-nfc-skip>رد کردن</button> ' +
                        '<button type="button" class="btn btn-sm btn-danger" data-nfc-cancel>توقف</button>', 'wait');
                    writeRow(row).then(function () {
                        i++;
                        setTimeout(step, 900); // فرصت دور کردن گوشی از تگ قبلی
                    }).catch(function (err) {
                        if (err && err.name === 'AbortError') {
                            if (statusBox && statusBox.dataset.skip === '1') {
                                statusBox.dataset.skip = '';
                                i++;
                                step();
                            } else {
                                setStatus('رایت پشت‌سرهم متوقف شد.', '');
                            }
                            return;
                        }
                        setStatus('✕ رایت تگ «' + escapeText(row.getAttribute('data-name')) + '» ناموفق بود: ' + escapeText(err && err.message ? err.message : '') +
                            ' <button type="button" class="btn btn-sm btn-primary" data-nfc-retry>تلاش دوباره</button>', 'error');
                        statusBox.onclick = function (e2) {
                            if (e2.target.closest('[data-nfc-retry]')) { statusBox.onclick = null; step(); }
                        };
                    });
                };
                step();
            });
        }

        if (statusBox) {
            statusBox.addEventListener('click', function (e) {
                if (e.target.closest('[data-nfc-skip]')) {
                    statusBox.dataset.skip = '1';
                    cancelWrite();
                } else if (e.target.closest('[data-nfc-cancel]')) {
                    cancelWrite();
                }
            });
        }
    }
});
