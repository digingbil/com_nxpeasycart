import './admin-main.css';

const mount = document.getElementById('nxp-admin-app');

if (!mount) {
    console.warn('[NXP Easy Cart] Admin mount point not found.');
} else {
    const csrfToken = mount.getAttribute('data-csrf-token') ?? '';
    const productsEndpoint = mount.getAttribute('data-products-endpoint') ?? '';

    mount.innerHTML = `
        <div class="nxp-admin-app__placeholder">
            <h1 class="nxp-admin-app__title">NXP Easy Cart</h1>
            <p class="nxp-admin-app__lead">
                Admin SPA booted with CSRF token: <code>${csrfToken}</code>
            </p>
            <p class="nxp-admin-app__lead">
                Products endpoint: <code>${productsEndpoint}</code>
            </p>
        </div>
    `;

    console.info('[NXP Easy Cart] Admin SPA placeholder initialised.');
}
