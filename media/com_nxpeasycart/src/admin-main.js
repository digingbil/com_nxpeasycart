import { createApp } from 'vue';
import App from './app/App.vue';
import './admin-main.css';

const mount = document.getElementById('nxp-admin-app');

if (!mount) {
    console.warn('[NXP Easy Cart] Admin mount point not found.');
} else {
    const csrfToken = mount.getAttribute('data-csrf-token') ?? '';
    const productsEndpoint = mount.getAttribute('data-products-endpoint') ?? '';
    const dataset = Object.assign({}, mount.dataset);

    createApp(App, {
        csrfToken,
        productsEndpoint,
        dataset,
    }).mount(mount);
}
