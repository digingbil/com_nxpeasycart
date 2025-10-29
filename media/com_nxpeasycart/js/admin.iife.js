(function(){"use strict";const n=document.getElementById("nxp-admin-app");if(!n)console.warn("[NXP Easy Cart] Admin mount point not found.");else{const t=n.getAttribute("data-csrf-token")??"",e=n.getAttribute("data-products-endpoint")??"";n.innerHTML=`
        <div class="nxp-admin-app__placeholder">
            <h1 class="nxp-admin-app__title">NXP Easy Cart</h1>
            <p class="nxp-admin-app__lead">
                Admin SPA booted with CSRF token: <code>${t}</code>
            </p>
            <p class="nxp-admin-app__lead">
                Products endpoint: <code>${e}</code>
            </p>
        </div>
    `,console.info("[NXP Easy Cart] Admin SPA placeholder initialised.")}})();
