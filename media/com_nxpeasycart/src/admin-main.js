import { createApp } from "vue";
import App from "./app/App.vue";
import "./admin-main.css";

const mount = document.getElementById("nxp-ec-admin-app");

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
            console.warn(
                "[NXP Easy Cart] Failed to parse admin config payload",
                error
            );
        }
    }

    const productsEndpoints = config?.endpoints?.products ?? {
        list: dataset.productsEndpoint ?? "",
        create: dataset.productsEndpointCreate ?? "",
        update: dataset.productsEndpointUpdate ?? "",
        delete: dataset.productsEndpointDelete ?? "",
    };

    const categoriesEndpoints = config?.endpoints?.categories ?? {
        list: dataset.categoriesEndpoint ?? "",
        create: dataset.categoriesEndpointCreate ?? "",
        update: dataset.categoriesEndpointUpdate ?? "",
        delete: dataset.categoriesEndpointDelete ?? "",
    };

    const ordersEndpoints = config?.endpoints?.orders ?? {
        list: dataset.ordersEndpoint ?? "",
        show: dataset.ordersEndpointShow ?? "",
        transition: dataset.ordersEndpointTransition ?? "",
        bulkTransition: dataset.ordersEndpointBulk ?? "",
        note: dataset.ordersEndpointNote ?? "",
        tracking: dataset.ordersEndpointTracking ?? "",
        invoice: dataset.ordersEndpointInvoice ?? "",
    };

    const customersEndpoints = config?.endpoints?.customers ?? {
        list: dataset.customersEndpoint ?? "",
        show: dataset.customersEndpointShow ?? "",
    };

    const gdprEndpoints = config?.endpoints?.gdpr ?? {
        export: dataset.gdprEndpointExport ?? "",
        anonymise: dataset.gdprEndpointAnonymise ?? "",
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

    const digitalFileEndpoints = config?.endpoints?.digitalfiles ?? {
        list: dataset.digitalfilesEndpointList ?? "",
        upload: dataset.digitalfilesEndpointUpload ?? "",
        delete: dataset.digitalfilesEndpointDelete ?? "",
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
            categories: categoriesEndpoints,
            orders: ordersEndpoints,
            customers: customersEndpoints,
            gdpr: gdprEndpoints,
            coupons: couponsEndpoints,
            tax: taxEndpoints,
            shipping: shippingEndpoints,
            digitalfiles: digitalFileEndpoints,
            settings: settingsEndpoints,
            logs: logsEndpoints,
            dashboard:
                config?.endpoints?.dashboard ?? dataset.dashboardEndpoint ?? "",
        },
    }).mount(mount);
}
