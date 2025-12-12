/**
 * Build available payment gateway options based on configuration and cart contents.
 */
export function buildGatewayOptions(payments = {}, cartItems = []) {
    const options = [];
    const hasItems = Array.isArray(cartItems) ? cartItems.length > 0 : false;

    // Check if cart is digital-only (no physical items)
    const hasPhysicalItems = Array.isArray(cartItems)
        ? cartItems.some((item) => !item.is_digital)
        : false;
    const isDigitalOnly = hasItems && !hasPhysicalItems;

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

    // COD is only available for orders with physical items (not digital-only)
    // Digital products can't be "cash on delivery" since there's nothing to deliver physically
    if ((payments.cod?.enabled ?? true) && hasItems && !isDigitalOnly) {
        options.push({
            id: "cod",
            label: payments.cod?.label || "Cash on delivery",
        });
    }

    // Bank Transfer is available for all order types including digital-only
    if ((payments.bank_transfer?.enabled ?? false) && hasItems) {
        options.push({
            id: "bank_transfer",
            label: payments.bank_transfer?.label || "Bank transfer",
        });
    }

    return options;
}

export default buildGatewayOptions;
