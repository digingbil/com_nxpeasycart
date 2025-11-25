/**
 * Build available payment gateway options based on configuration and cart contents.
 */
export function buildGatewayOptions(payments = {}, cartItems = []) {
    const options = [];
    const hasItems = Array.isArray(cartItems) ? cartItems.length > 0 : false;

    const isConfigured = (config, keys = []) =>
        keys.every((key) => {
            const value = config?.[key] ?? "";
            return String(value).trim() !== "";
        });

    if (
        isConfigured(payments.stripe ?? {}, [
            "publishable_key",
            "secret_key",
        ])
    ) {
        options.push({
            id: "stripe",
            label: "Card (Stripe)",
        });
    }

    if (
        isConfigured(payments.paypal ?? {}, [
            "client_id",
            "client_secret",
        ])
    ) {
        options.push({
            id: "paypal",
            label: "PayPal",
        });
    }

    if ((payments.cod?.enabled ?? true) && hasItems) {
        options.push({
            id: "cod",
            label: payments.cod?.label || "Cash on delivery",
        });
    }

    if ((payments.bank_transfer?.enabled ?? false) && hasItems) {
        options.push({
            id: "bank_transfer",
            label: payments.bank_transfer?.label || "Bank transfer",
        });
    }

    return options;
}

export default buildGatewayOptions;
