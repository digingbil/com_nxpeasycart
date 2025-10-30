import { createApp } from "vue";
import App from "./app/App.vue";
import "./admin-main.css";

const mount = document.getElementById("nxp-admin-app");

if (!mount) {
    console.warn("[NXP Easy Cart] Admin mount point not found.");
} else {
    const csrfToken = mount.getAttribute("data-csrf-token") ?? "";
    const dataset = Object.assign({}, mount.dataset);
    let config = {};

    if (dataset.config) {
        try {
            config = JSON.parse(dataset.config);
        } catch (error) {
            console.warn("[NXP Easy Cart] Failed to parse admin config payload", error);
        }
    }

    const productsEndpoints = config?.endpoints?.products ?? {
        list: dataset.productsEndpoint ?? "",
        create: dataset.productsEndpointCreate ?? "",
        update: dataset.productsEndpointUpdate ?? "",
        delete: dataset.productsEndpointDelete ?? "",
    };

    const ordersEndpoints = config?.endpoints?.orders ?? {
        list: dataset.ordersEndpoint ?? "",
        show: dataset.ordersEndpointShow ?? "",
        transition: dataset.ordersEndpointTransition ?? "",
        bulkTransition: dataset.ordersEndpointBulk ?? "",
        note: dataset.ordersEndpointNote ?? "",
    };

    const customersEndpoints = config?.endpoints?.customers ?? {
        list: dataset.customersEndpoint ?? "",
        show: dataset.customersEndpointShow ?? "",
    };

    const couponsEndpoints = config?.endpoints?.coupons ?? {
        list: dataset.couponsEndpoint ?? "",
        create: dataset.couponsEndpointCreate ?? "",
        update: dataset.couponsEndpointUpdate ?? "",
        delete: dataset.couponsEndpointDelete ?? "",
    };

    const taxEndpoints = config?.endpoints?.tax ?? {
        list: dataset.taxEndpoint ?? "",
        create: dataset.taxEndpointCreate ?? "",
        update: dataset.taxEndpointUpdate ?? "",
        delete: dataset.taxEndpointDelete ?? "",
    };

    const shippingEndpoints = config?.endpoints?.shipping ?? {
        list: dataset.shippingEndpoint ?? "",
        create: dataset.shippingEndpointCreate ?? "",
        update: dataset.shippingEndpointUpdate ?? "",
        delete: dataset.shippingEndpointDelete ?? "",
    };

    const settingsEndpoints = config?.endpoints?.settings ?? {
        show: dataset.settingsEndpointShow ?? "",
        update: dataset.settingsEndpointUpdate ?? "",
    };

    const logsEndpoints = config?.endpoints?.logs ?? {
        list: dataset.logsEndpoint ?? "",
    };

    createApp(App, {
        csrfToken,
        dataset,
        config,
        endpoints: {
            products: productsEndpoints,
            orders: ordersEndpoints,
            customers: customersEndpoints,
            coupons: couponsEndpoints,
            tax: taxEndpoints,
            shipping: shippingEndpoints,
            settings: settingsEndpoints,
            logs: logsEndpoints,
            dashboard: config?.endpoints?.dashboard ?? dataset.dashboardEndpoint ?? "",
        },
    }).mount(mount);
}
