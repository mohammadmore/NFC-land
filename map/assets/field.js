/**
 * NFC Event Tag Scoring - Field panel (نصب/تأیید/جمع‌آوری)
 * - انتخاب طبقه ← بخش ← نقطه، و افزودن سریع طبقه/بخش بدون از دست رفتن عکس‌های انتخاب‌شده
 * - فشرده‌سازی عکس‌ها در مرورگر (حداکثر ۱۶۰۰ پیکسل + تصویر کوچک ۴۰۰ پیکسل) پیش از ارسال
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function esc(t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    /* ------------------------------------------------------------------
       ۱. فشرده‌سازی عکس‌ها
       ------------------------------------------------------------------ */
    function loadImage(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () {
                return createImageBitmap(file);
            });
        }
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    }

    function resizeToBlob(src, maxSide, quality) {
        const w = src.width;
        const h = src.height;
        const scale = Math.min(1, maxSide / Math.max(w, h));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(src, 0, 0, canvas.width, canvas.height);
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (b) { b ? resolve(b) : reject(new Error('toBlob')); }, 'image/jpeg', quality);
        });
    }

    document.querySelectorAll('.js-photo-input').forEach(function (input) {
        const picker = input.closest('.field-photo-picker');
        const preview = picker ? picker.querySelector('.js-photo-preview') : null;
        input.addEventListener('change', function () {
            input._compressed = null;
            const file = input.files && input.files[0];
            if (!file) return;
            if (preview) {
                preview.classList.add('is-loading');
                preview.textContent = '⏳';
            }
            input._pending = loadImage(file)
                .then(function (img) {
                    return Promise.all([resizeToBlob(img, 1600, 0.82), resizeToBlob(img, 400, 0.75)]);
                })
                .then(function (blobs) {
                    input._compressed = { main: blobs[0], thumb: blobs[1] };
                    if (preview) {
                        preview.classList.remove('is-loading');
                        preview.innerHTML = '';
                        const im = document.createElement('img');
                        im.src = URL.createObjectURL(blobs[1]);
                        im.alt = '';
                        preview.appendChild(im);
                    }
                })
                .catch(function () {
                    // مرورگر قدیمی: فایل اصلی ارسال می‌شود و سرور تصویر کوچک را می‌سازد
                    input._compressed = null;
                    if (preview) {
                        preview.classList.remove('is-loading');
                        preview.textContent = '✅';
                    }
                });
        });
    });

    /* ------------------------------------------------------------------
       ۲. ارسال فرم‌های دارای عکس با fetch (عکس فشرده‌شده جایگزین فایل اصلی می‌شود)
       ------------------------------------------------------------------ */
    document.querySelectorAll('.js-photo-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.sending === '1') {
                e.preventDefault();
                return;
            }
            if (form.classList.contains('js-place-form') && !validatePlaceForm(form)) {
                e.preventDefault();
                return;
            }
            if (!window.fetch || !window.FormData) return; // ارسال عادی
            e.preventDefault();
            form.dataset.sending = '1';
            const btn = form.querySelector('button[type=submit]');
            const btnText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '⏳ در حال ارسال...';
            }
            const inputs = Array.prototype.slice.call(form.querySelectorAll('.js-photo-input'));
            Promise.all(inputs.map(function (i) { return i._pending || Promise.resolve(); }))
                .then(function () {
                    const fd = new FormData(form);
                    inputs.forEach(function (i) {
                        if (i._compressed) {
                            fd.delete(i.name);
                            fd.append(i.name, i._compressed.main, i.name + '.jpg');
                            fd.append(i.name + '_thumb', i._compressed.thumb, i.name + '_thumb.jpg');
                        } else if (!i.files || !i.files.length) {
                            fd.delete(i.name);
                        }
                    });
                    return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
                })
                .then(function (res) {
                    window.location.href = res.url || window.location.href;
                })
                .catch(function () {
                    form.dataset.sending = '';
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = btnText;
                    }
                    alert('ارسال انجام نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید (عکس‌ها هنوز انتخاب‌شده هستند).');
                });
        });
    });

    /* ------------------------------------------------------------------
       ۳. انتخاب طبقه ← بخش ← نقطه
       ------------------------------------------------------------------ */
    const placeForm = document.querySelector('.js-place-form');
    if (!placeForm) return;

    let data;
    try {
        data = JSON.parse(placeForm.getAttribute('data-structure') || '{}');
    } catch (err) {
        data = { floors: [], zones: [], spots: [] };
    }
    data.floors = data.floors || [];
    data.zones = data.zones || [];
    data.spots = data.spots || [];

    const floorSel = placeForm.querySelector('.js-floor');
    const zoneSel = placeForm.querySelector('.js-zone');
    const spotList = placeForm.querySelector('.js-spot-list');
    const newRadio = placeForm.querySelector('.js-spot-new-radio');
    const newFields = placeForm.querySelector('.js-new-spot-fields');
    const csrf = placeForm.querySelector('input[name=csrf_token]').value;

    function fillFloors(selectId) {
        floorSel.innerHTML = data.floors.length ? '' : '<option value="">— ابتدا یک طبقه اضافه کنید —</option>';
        data.floors.forEach(function (f) {
            const o = document.createElement('option');
            o.value = f.id;
            o.textContent = f.name;
            floorSel.appendChild(o);
        });
        if (selectId) floorSel.value = String(selectId);
    }

    function fillZones(selectId) {
        const fid = parseInt(floorSel.value, 10) || 0;
        const zones = data.zones.filter(function (z) { return z.floor_id === fid; });
        zoneSel.innerHTML = zones.length ? '' : '<option value="">— برای این طبقه یک بخش اضافه کنید —</option>';
        zones.forEach(function (z) {
            const o = document.createElement('option');
            o.value = z.id;
            o.textContent = z.name;
            zoneSel.appendChild(o);
        });
        if (selectId) zoneSel.value = String(selectId);
    }

    function renderSpots() {
        const zid = parseInt(zoneSel.value, 10) || 0;
        const spots = data.spots.filter(function (s) { return s.zone_id === zid; });
        let html = '';
        spots.forEach(function (s) {
            const disabled = !!s.occupied_by;
            html += '<label class="field-spot-card' + (disabled ? ' is-occupied' : '') + (s.is_current ? ' is-current' : '') + '">' +
                '<input type="radio" name="spot_id" value="' + s.id + '"' + (disabled ? ' disabled' : '') + (s.is_current ? ' checked' : '') + '>' +
                '<span class="field-spot-thumb">' + (s.thumb ? '<img src="' + esc(s.thumb) + '" alt="" loading="lazy">' : '📍') + '</span>' +
                '<span class="field-spot-info"><strong>' + esc(s.name) + '</strong>' +
                (s.desc ? '<small>' + esc(s.desc) + '</small>' : '') +
                (disabled ? '<small class="text-danger">تگ «' + esc(s.occupied_by) + '» اینجا نصب است</small>' : '') +
                (s.is_current ? '<small class="text-primary">محل فعلی همین تگ</small>' : '') +
                '</span></label>';
        });
        spotList.innerHTML = html || '<p class="text-muted" style="font-size: 0.85rem;">در این بخش هنوز نقطه‌ای ثبت نشده است.</p>';
        const anyFree = spots.some(function (s) { return !s.occupied_by; });
        if (!spots.length || !anyFree) {
            newRadio.checked = true;
        } else if (newRadio.checked && spots.some(function (s) { return s.is_current; })) {
            newRadio.checked = false;
        }
        toggleNewFields();
    }

    function toggleNewFields() {
        newFields.style.display = newRadio.checked ? '' : 'none';
    }

    placeForm.addEventListener('change', function (e) {
        if (e.target === floorSel) {
            fillZones();
            renderSpots();
        } else if (e.target === zoneSel) {
            renderSpots();
        } else if (e.target.name === 'spot_id') {
            toggleNewFields();
        }
    });

    function validatePlaceForm(form) {
        if (!zoneSel.value) {
            alert('طبقه و بخش محل نصب را انتخاب کنید.');
            return false;
        }
        if (!form.querySelector('input[name=spot_id]:checked')) {
            alert('یک نقطه موجود یا «نقطه جدید» را انتخاب کنید.');
            return false;
        }
        return true;
    }

    function postJson(url, fields) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    placeForm.querySelector('.js-add-floor').addEventListener('click', function () {
        const name = prompt('نام طبقه جدید (مثلاً: همکف، طبقه ۲، زیرزمین):');
        if (!name || !name.trim()) return;
        postJson(placeForm.getAttribute('data-floor-url'), { name: name.trim() }).then(function (res) {
            if (!res.success) { alert(res.message || 'خطا'); return; }
            data.floors.push({ id: res.id, name: res.name });
            fillFloors(res.id);
            fillZones();
            renderSpots();
        }).catch(function () { alert('خطا در ارتباط با سرور.'); });
    });

    placeForm.querySelector('.js-add-zone').addEventListener('click', function () {
        const fid = parseInt(floorSel.value, 10) || 0;
        if (!fid) { alert('ابتدا طبقه را انتخاب یا اضافه کنید.'); return; }
        const name = prompt('نام بخش جدید در این طبقه (مثلاً: لابی، رستوران، راهروی غربی):');
        if (!name || !name.trim()) return;
        postJson(placeForm.getAttribute('data-zone-url'), { name: name.trim(), floor_id: fid }).then(function (res) {
            if (!res.success) { alert(res.message || 'خطا'); return; }
            data.zones.push({ id: res.id, floor_id: fid, name: res.name });
            fillZones(res.id);
            renderSpots();
        }).catch(function () { alert('خطا در ارتباط با سرور.'); });
    });

    // مقداردهی اولیه: طبقه/بخش فعلی تگ، یا آخرین انتخاب این گوشی (برای نصب پشت‌سرهم در یک بخش)
    let initFloor = parseInt(placeForm.getAttribute('data-current-floor'), 10) || 0;
    let initZone = parseInt(placeForm.getAttribute('data-current-zone'), 10) || 0;
    if (!initZone) {
        try {
            const last = JSON.parse(localStorage.getItem('nfcFieldLastZone') || 'null');
            if (last && data.zones.some(function (z) { return z.id === last.zone; })) {
                initFloor = last.floor;
                initZone = last.zone;
            }
        } catch (err) { /* localStorage در دسترس نیست */ }
    }
    fillFloors(initFloor || (data.floors[0] && data.floors[0].id));
    fillZones(initZone);
    renderSpots();

    placeForm.addEventListener('submit', function () {
        try {
            localStorage.setItem('nfcFieldLastZone', JSON.stringify({ floor: parseInt(floorSel.value, 10) || 0, zone: parseInt(zoneSel.value, 10) || 0 }));
        } catch (err) { /* بی‌اهمیت */ }
    });
});
