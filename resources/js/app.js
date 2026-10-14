import '../css/app.css';
import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

/**
 * Dynamic helper untuk memuat Chart.js hanya pada halaman yang membutuhkan (on-demand)
 */
window.initChart = async function(canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) return null;
    const { default: Chart } = await import('chart.js/auto');
    return new Chart(el, config);
};
