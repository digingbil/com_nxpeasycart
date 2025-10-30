import { createApp } from "vue";
import App from "./app/App.vue";
import "./admin-main.css";

const mount = document.getElementById("nxp-admin-app");

if (!mount) {
    console.warn("[NXP Easy Cart] Admin mount point not found.");
} else {
    const csrfToken = mount.getAttribute("data-csrf-token") ?? "";
    const dataset = Object.assign({}, mount.dataset);

    const productsEndpoints = {
        list: dataset.productsEndpoint ?? "",
        create: dataset.productsEndpointCreate ?? "",
        update: dataset.productsEndpointUpdate ?? "",
        delete: dataset.productsEndpointDelete ?? "",
    };

    //console.info('[NXP Easy Cart] Booting admin SPA', { dataset, productsEndpoints });

    createApp(App, {
        csrfToken,
        productsEndpoints,
        dataset,
    }).mount(mount);
}
