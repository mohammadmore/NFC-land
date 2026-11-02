/**
 * NFC Event Tag Scoring - Floor plan editor
 * - پین نقطه‌ها: انتخاب نقطه از فهرست و زدن روی پلان، یا کشیدن پین‌های موجود
 * - کروکی ساده: کشیدن مستطیل‌های نام‌دار برای بخش‌ها در فضای ۱۰۰۰×۷۰۰
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function pct(e, box) {
        const r = box.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100)),
            y: Math.max(0, Math.min(100, (e.clientY - r.top) / r.height * 100))
        };
    }

    /* ------------------------------------------------------------------
       ۱. ویرایشگر پین‌ها
       ------------------------------------------------------------------ */
    const editor = document.querySelector('.js-pin-editor');
    if (editor) {
        const box = editor.querySelector('.js-pin-box');
        const status = editor.querySelector('.js-pin-status');
        const pinUrl = editor.getAttribute('data-pin-url');
        const csrf = editor.getAttribute('data-csrf');
        let selected = null;

        const items = {};
        editor.querySelectorAll('.js-spot-item').forEach(function (it) { items[it.getAttribute('data-spot')] = it; });

        const say = function (t) { if (status) status.textContent = t; };

        const findPin = function (id) { return box.querySelector('.js-pin[data-spot="' + id + '"]'); };

        const select = function (id) {
            selected = id;
            Object.keys(items).forEach(function (k) { items[k].classList.toggle('is-selected', k === id); });
            box.querySelectorAll('.js-pin').forEach(function (p) { p.classList.toggle('is-selected', p.getAttribute('data-spot') === id); });
            if (id && items[id]) say('«' + items[id].getAttribute('data-name') + '» انتخاب شد؛ روی پلان بزنید.');
        };

        const save = function (id, x, y) {
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            if (x !== null) {
                fd.append('x', x.toFixed(3));
                fd.append('y', y.toFixed(3));
            }
            return fetch(pinUrl + '/' + id + '/pin', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) throw new Error(res.message || 'error');
                    const it = items[id];
                    if (it) {
                        it.classList.toggle('has-pin', x !== null);
                        it.querySelector('.plan-spot-flag').textContent = x !== null ? '📍' : '○';
                        it.querySelector('.js-pin-clear').style.display = x !== null ? '' : 'none';
                    }
                    say(x !== null ? '✅ ذخیره شد.' : 'پین حذف شد.');
                })
                .catch(function () { say('✕ ذخیره نشد؛ اتصال را بررسی کنید.'); });
        };

        const placePin = function (id, x, y) {
            let pin = findPin(id);
            if (!pin) {
                pin = document.createElement('span');
                pin.className = 'plan-pin plan-pin-edit js-pin';
                pin.setAttribute('data-spot', id);
                pin.title = items[id] ? items[id].getAttribute('data-name') : '';
                box.appendChild(pin);
                bindPin(pin);
            }
            pin.style.left = x + '%';
            pin.style.top = y + '%';
        };

        function bindPin(pin) {
            pin.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const id = pin.getAttribute('data-spot');
                select(id);
                let moved = false;
                pin.setPointerCapture(e.pointerId);
                const move = function (ev) {
                    moved = true;
                    const p = pct(ev, box);
                    pin.style.left = p.x + '%';
                    pin.style.top = p.y + '%';
                };
                const up = function (ev) {
                    pin.removeEventListener('pointermove', move);
                    pin.removeEventListener('pointerup', up);
                    if (moved) {
                        const p = pct(ev, box);
                        save(id, p.x, p.y);
                    }
                };
                pin.addEventListener('pointermove', move);
                pin.addEventListener('pointerup', up);
            });
        }
        box.querySelectorAll('.js-pin').forEach(bindPin);

        box.addEventListener('click', function (e) {
            if (e.target.closest('.js-pin')) return;
            if (!selected) {
                say('ابتدا یک نقطه را از فهرست انتخاب کنید.');
                return;
            }
            const p = pct(e, box);
            placePin(selected, p.x, p.y);
            save(selected, p.x, p.y);
        });

        Object.keys(items).forEach(function (id) {
            const it = items[id];
            it.addEventListener('click', function (e) {
                if (e.target.closest('.js-pin-clear')) return;
                select(id);
            });
            it.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(id); }
            });
            it.querySelector('.js-pin-clear').addEventListener('click', function () {
                const pin = findPin(id);
                if (pin) pin.remove();
                save(id, null, null);
            });
        });
    }

    /* ------------------------------------------------------------------
       ۲. ویرایشگر کروکی
       ------------------------------------------------------------------ */
    const svg = document.querySelector('.js-sketch-svg');
    if (svg) {
        const NS = 'http://www.w3.org/2000/svg';
        const form = document.querySelector('.js-sketch-form');
        const input = document.querySelector('.js-sketch-input');
        const countEl = document.querySelector('.js-sketch-count');
        let shapes = [];
        let dirty = false;
        try {
            shapes = (JSON.parse(svg.getAttribute('data-sketch') || '{}').shapes) || [];
        } catch (err) { shapes = []; }

        const toSvg = function (e) {
            const r = svg.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(1000, (e.clientX - r.left) / r.width * 1000)),
                y: Math.max(0, Math.min(700, (e.clientY - r.top) / r.height * 700))
            };
        };

        const el = function (name, attrs) {
            const n = document.createElementNS(NS, name);
            Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
            return n;
        };

        function render() {
            while (svg.firstChild) svg.removeChild(svg.firstChild);
            svg.appendChild(el('rect', { x: 0, y: 0, width: 1000, height: 700, fill: '#f8fafc' }));
            // خطوط راهنما
            for (let gx = 100; gx < 1000; gx += 100) svg.appendChild(el('line', { x1: gx, y1: 0, x2: gx, y2: 700, stroke: '#eef2f7', 'stroke-width': 2 }));
            for (let gy = 100; gy < 700; gy += 100) svg.appendChild(el('line', { x1: 0, y1: gy, x2: 1000, y2: gy, stroke: '#eef2f7', 'stroke-width': 2 }));
            shapes.forEach(function (s, i) {
                const g = el('g', { 'data-i': i, class: 'sketch-shape' });
                g.appendChild(el('rect', { x: s.x, y: s.y, width: s.w, height: s.h, fill: '#e2e8f0', stroke: '#64748b', 'stroke-width': 3, rx: 6 }));
                if (s.label) {
                    const t = el('text', { x: s.x + s.w / 2, y: s.y + s.h / 2, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': 26, fill: '#334155', 'font-family': 'Vazirmatn, Tahoma, sans-serif' });
                    t.textContent = s.label;
                    g.appendChild(t);
                }
                svg.appendChild(g);
            });
            if (countEl) countEl.textContent = shapes.length.toLocaleString('fa-IR') + ' بخش' + (dirty ? ' · ذخیره‌نشده' : '');
        }

        let start = null;
        let temp = null;
        svg.addEventListener('pointerdown', function (e) {
            const g = e.target.closest('.sketch-shape');
            if (g) {
                const i = parseInt(g.getAttribute('data-i'), 10);
                const name = prompt('نام این بخش (برای حذف، متن را خالی کنید):', shapes[i].label || '');
                if (name === null) return;
                if (name.trim() === '') {
                    if (confirm('این مستطیل حذف شود؟')) shapes.splice(i, 1);
                } else {
                    shapes[i].label = name.trim().slice(0, 60);
                }
                dirty = true;
                render();
                return;
            }
            e.preventDefault();
            start = toSvg(e);
            svg.setPointerCapture(e.pointerId);
            temp = el('rect', { x: start.x, y: start.y, width: 0, height: 0, fill: 'rgba(37,99,235,0.15)', stroke: '#2563eb', 'stroke-width': 3, 'stroke-dasharray': '8 6' });
            svg.appendChild(temp);
        });
        svg.addEventListener('pointermove', function (e) {
            if (!start || !temp) return;
            const p = toSvg(e);
            temp.setAttribute('x', Math.min(start.x, p.x));
            temp.setAttribute('y', Math.min(start.y, p.y));
            temp.setAttribute('width', Math.abs(p.x - start.x));
            temp.setAttribute('height', Math.abs(p.y - start.y));
        });
        svg.addEventListener('pointerup', function (e) {
            if (!start) return;
            const p = toSvg(e);
            const r = { x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) };
            start = null;
            if (temp) { temp.remove(); temp = null; }
            if (r.w < 15 || r.h < 15) return;
            const name = prompt('نام این بخش (مثلاً لابی، رستوران، آسانسور):', '');
            if (name === null) return;
            r.label = name.trim().slice(0, 60);
            ['x', 'y', 'w', 'h'].forEach(function (k) { r[k] = Math.round(r[k] * 10) / 10; });
            shapes.push(r);
            dirty = true;
            render();
        });

        const undo = document.querySelector('.js-sketch-undo');
        if (undo) undo.addEventListener('click', function () { shapes.pop(); dirty = true; render(); });
        const clear = document.querySelector('.js-sketch-clear');
        if (clear) clear.addEventListener('click', function () {
            if (shapes.length && confirm('همه مستطیل‌ها پاک شوند؟')) { shapes = []; dirty = true; render(); }
        });
        if (form) form.addEventListener('submit', function () {
            input.value = JSON.stringify({ shapes: shapes });
            dirty = false;
        });
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });
        render();
    }
});
