/**
 * NFC Event Tag Scoring - Tag label printing (QR rendering + options)
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof qrcode === 'function') {
        document.querySelectorAll('.print-qr').forEach(function (el) {
            try {
                var qr = qrcode(0, 'M');
                qr.addData(el.getAttribute('data-url'));
                qr.make();
                el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
            } catch (e) {
                el.textContent = 'خطا در ساخت QR';
            }
        });
    }

    var grid = document.getElementById('printGrid');
    var optUrl = document.getElementById('optShowUrl');
    var optLarge = document.getElementById('optLarge');
    if (grid && optUrl) {
        optUrl.addEventListener('change', function () { grid.classList.toggle('hide-url', !optUrl.checked); });
    }
    if (grid && optLarge) {
        optLarge.addEventListener('change', function () { grid.classList.toggle('large', optLarge.checked); });
    }
    var btn = document.getElementById('btnPrint');
    if (btn) {
        btn.addEventListener('click', function () { window.print(); });
    }
});
