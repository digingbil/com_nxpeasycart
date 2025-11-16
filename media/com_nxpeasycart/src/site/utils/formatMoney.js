const ZERO_DECIMAL_CURRENCIES = new Set([
    "BIF",
    "CLP",
    "DJF",
    "GNF",
    "ISK",
    "JPY",
    "KMF",
    "KRW",
    "PYG",
    "RWF",
    "UGX",
    "VND",
    "VUV",
    "XAF",
    "XOF",
    "XPF",
]);

const getDecimals = (currency) => {
    const code = (currency || "").toUpperCase().replace(/[^A-Z]/g, "");
    return ZERO_DECIMAL_CURRENCIES.has(code) ? 0 : 2;
};

const formatMoney = (cents, currency, locale) => {
    const code = (currency || "").toUpperCase().replace(/[^A-Z]/g, "") || "USD";
    const decimals = getDecimals(code);
    const divisor = decimals > 0 ? 10 ** decimals : 1;
    const amount = (cents || 0) / divisor;

    try {
        return new Intl.NumberFormat(locale, {
            style: "currency",
            currency: code,
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(amount);
    } catch (error) {
        const formatted = amount.toLocaleString(locale || undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });

        return `${code} ${formatted}`;
    }
};

export default formatMoney;
