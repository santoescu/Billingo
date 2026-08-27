import ClipboardJS from 'clipboard';
window.ClipboardJS = ClipboardJS;
import clipboardHelper from 'preline/helpers/clipboard';
window.hsClipboardHelper = clipboardHelper;

import "preline";
import '@preline/select'
import '@preline/overlay';
import '@preline/dropdown';
import '@preline/accordion';
import '@preline/tabs';
import '@preline/input-number';

import _ from 'lodash';
window._ = _;
import Dropzone from 'dropzone';
window.Dropzone = Dropzone;
import '@preline/file-upload';

import $ from 'jquery';

window.$ = window.jQuery = $;

import '@preline/copy-markup';
import 'datatables.net-dt';

import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

let zoomPreviewEl = null;
const ZOOM_SIZE = 260;

function showZoomPreview(thumb) {
    if (! zoomPreviewEl) {
        zoomPreviewEl = document.createElement('img');
        zoomPreviewEl.className = 'fixed pointer-events-none rounded-lg shadow-2xl ring-2 ring-white object-cover dark:ring-neutral-700';
        zoomPreviewEl.style.zIndex = '9999';
        zoomPreviewEl.style.width = `${ZOOM_SIZE}px`;
        zoomPreviewEl.style.height = `${ZOOM_SIZE}px`;
        document.body.appendChild(zoomPreviewEl);
    }

    const rect = thumb.getBoundingClientRect();
    zoomPreviewEl.src = thumb.src;

    let left = rect.right + 12;
    if (left + ZOOM_SIZE > window.innerWidth) {
        left = rect.left - ZOOM_SIZE - 12;
    }
    left = Math.max(8, left);

    let top = rect.top + rect.height / 2 - ZOOM_SIZE / 2;
    top = Math.max(8, Math.min(top, window.innerHeight - ZOOM_SIZE - 8));

    zoomPreviewEl.style.left = `${left}px`;
    zoomPreviewEl.style.top = `${top}px`;
    zoomPreviewEl.style.display = 'block';
}

function hideZoomPreview() {
    if (zoomPreviewEl) zoomPreviewEl.style.display = 'none';
}

document.addEventListener('mouseover', (event) => {
    const thumb = event.target.closest('.zoomable-thumb');
    if (thumb) showZoomPreview(thumb);
});

document.addEventListener('mouseout', (event) => {
    const thumb = event.target.closest('.zoomable-thumb');
    if (thumb) hideZoomPreview();
});

// En capture phase: se adelanta al listener de "click" de la tarjeta (POS,
// cotizaciones, catálogo público -- todas agregan el producto al carrito al
// hacer click en cualquier parte de la tarjeta). Sin esto, en celular (donde
// no existe hover) tocar la imagen para verla en grande termina agregando el
// producto por accidente, porque el toque simplemente hace click.
document.addEventListener('click', (event) => {
    const thumb = event.target.closest('.zoomable-thumb');
    if (! thumb) return;
    event.stopPropagation();
    showZoomPreview(thumb);
}, true);

document.addEventListener('click', (event) => {
    if (! event.target.closest('.zoomable-thumb')) hideZoomPreview();
});
