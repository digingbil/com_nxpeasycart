import { computed } from "vue";

/**
 * useVariants - Variant management composable.
 *
 * Handles variant CRUD operations, options management,
 * price formatting, and variant images.
 *
 * @since 0.3.2
 */
export function useVariants(formVariants, baseCurrency, isDigitalProduct, translate) {
    const __ = translate;

    /**
     * Convert UTC datetime string to local datetime-local input value.
     */
    const utcToLocal = (utcStr) => {
        if (!utcStr || utcStr === "") {
            return "";
        }

        try {
            const date = new Date(utcStr.replace(" ", "T") + "Z");

            if (isNaN(date.getTime())) {
                return "";
            }

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            const hours = String(date.getHours()).padStart(2, "0");
            const minutes = String(date.getMinutes()).padStart(2, "0");

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        } catch (e) {
            return "";
        }
    };

    /**
     * Convert local datetime-local value to UTC datetime string.
     */
    const localToUtc = (localStr) => {
        if (!localStr || localStr === "") {
            return null;
        }

        try {
            const date = new Date(localStr);

            if (isNaN(date.getTime())) {
                return null;
            }

            return date.toISOString().slice(0, 19).replace("T", " ");
        } catch (e) {
            return null;
        }
    };

    /**
     * Create a blank variant object.
     */
    const blankVariant = () => ({
        id: 0,
        sku: "",
        ean: "",
        price: "",
        sale_price: "",
        sale_start: null,
        sale_end: null,
        sale_start_local: "",
        sale_end_local: "",
        currency: baseCurrency.value,
        stock: 0,
        weight: "",
        active: true,
        options: [],
        images: [],
        is_digital: isDigitalProduct.value,
    });

    /**
     * Create a blank option object.
     */
    const blankOption = () => ({
        name: "",
        value: "",
    });

    /**
     * Normalize options array or object to consistent shape.
     */
    const normaliseOptions = (options) => {
        if (Array.isArray(options)) {
            return options.map((option) => ({
                name: String(option?.name ?? option?.key ?? "").trim(),
                value: String(option?.value ?? "").trim(),
            }));
        }

        if (options && typeof options === "object") {
            return Object.entries(options).map(([name, value]) => ({
                name: String(name ?? "").trim(),
                value: String(value ?? "").trim(),
            }));
        }

        return [];
    };

    /**
     * Normalize an array of variants.
     */
    const normaliseVariants = (variants) => {
        if (!Array.isArray(variants) || variants.length === 0) {
            return [blankVariant()];
        }

        return variants.map((variant) => {
            const salePrice =
                variant?.sale_price != null
                    ? String(variant.sale_price)
                    : Number.isFinite(variant?.sale_price_cents)
                      ? (variant.sale_price_cents / 100).toFixed(2)
                      : "";

            let variantImages = [];
            if (Array.isArray(variant?.images)) {
                variantImages = variant.images
                    .filter((img) => typeof img === "string" && img.trim() !== "")
                    .map((img) => img.trim());
            }

            return {
                id: Number.parseInt(variant?.id ?? 0, 10) || 0,
                sku: String(variant?.sku ?? "").trim(),
                ean: variant?.ean != null ? String(variant.ean).trim() : "",
                price:
                    variant?.price != null
                        ? String(variant.price)
                        : Number.isFinite(variant?.price_cents)
                          ? (variant.price_cents / 100).toFixed(2)
                          : "",
                sale_price: salePrice,
                sale_start: variant?.sale_start ?? null,
                sale_end: variant?.sale_end ?? null,
                sale_start_local: utcToLocal(variant?.sale_start),
                sale_end_local: utcToLocal(variant?.sale_end),
                currency: String(variant?.currency ?? baseCurrency.value).toUpperCase(),
                stock: Number.parseInt(variant?.stock ?? 0, 10) || 0,
                weight: variant?.weight != null ? String(variant.weight) : "",
                active: variant?.active !== undefined ? Boolean(variant.active) : true,
                options: normaliseOptions(variant?.options),
                images: variantImages,
                is_digital:
                    variant?.is_digital !== undefined
                        ? Boolean(variant.is_digital)
                        : isDigitalProduct.value,
            };
        });
    };

    /**
     * Apply digital defaults to all variants.
     */
    const applyVariantDigitalDefaults = () => {
        formVariants.value.forEach((variant) => {
            if (variant.is_digital === undefined || variant.is_digital === null) {
                variant.is_digital = isDigitalProduct.value;
            }

            if (isDigitalProduct.value) {
                variant.weight = "";
            }
        });
    };

    /**
     * Reset variants from normalized data.
     */
    const resetVariants = (variants) => {
        formVariants.value.splice(0, formVariants.value.length, ...variants);
        applyVariantDigitalDefaults();
    };

    /**
     * Computed variant options for display (dropdown labels).
     */
    const variantOptions = computed(() =>
        formVariants.value.map((variant, index) => {
            const sku = String(variant?.sku ?? "").trim();
            const optionLabel = Array.isArray(variant?.options)
                ? variant.options
                      .filter((option) => option?.name && option?.value)
                      .map(
                          (option) =>
                              `${String(option.name).trim()}: ${String(option.value).trim()}`
                      )
                      .join(", ")
                : "";
            const fallback = __(
                "COM_NXPEASYCART_FIELD_PRODUCT_VARIANT_HEADING",
                "Variant %s",
                [String(index + 1)]
            );

            return {
                id: Number.parseInt(variant?.id ?? 0, 10) || 0,
                label: sku || optionLabel || fallback,
            };
        })
    );

    /**
     * Add a new blank variant.
     */
    const addVariant = () => {
        formVariants.value.push(blankVariant());
    };

    /**
     * Remove a variant by index.
     */
    const removeVariant = (index) => {
        if (formVariants.value.length <= 1) {
            return;
        }

        formVariants.value.splice(index, 1);
    };

    /**
     * Duplicate a variant.
     */
    const duplicateVariant = (index) => {
        const original = formVariants.value[index];

        if (!original) {
            return;
        }

        const clone = JSON.parse(JSON.stringify(original));
        clone.id = 0;
        clone.sku = [clone.sku || "SKU", "COPY"].join("-").replace(/-+/g, "-");
        clone.active = true;

        formVariants.value.splice(index + 1, 0, clone);
    };

    /**
     * Add an option to a variant.
     */
    const addVariantOption = (variantIndex) => {
        const target = formVariants.value[variantIndex];

        if (!target) {
            return;
        }

        if (!Array.isArray(target.options)) {
            target.options = [];
        }

        target.options.push(blankOption());
    };

    /**
     * Remove an option from a variant.
     */
    const removeVariantOption = (variantIndex, optionIndex) => {
        const target = formVariants.value[variantIndex];

        if (!target || !Array.isArray(target.options)) {
            return;
        }

        target.options.splice(optionIndex, 1);
    };

    /**
     * Format variant price to 2 decimal places.
     */
    const formatVariantPrice = (index) => {
        const variant = formVariants.value[index];

        if (!variant) {
            return;
        }

        const numeric = Number.parseFloat(variant.price);

        if (Number.isNaN(numeric)) {
            return;
        }

        variant.price = numeric.toFixed(2);
    };

    /**
     * Format variant sale price to 2 decimal places.
     */
    const formatVariantSalePrice = (index) => {
        const variant = formVariants.value[index];

        if (!variant) {
            return;
        }

        if (
            variant.sale_price === "" ||
            variant.sale_price === null ||
            variant.sale_price === undefined
        ) {
            variant.sale_price = "";
            return;
        }

        const numeric = Number.parseFloat(variant.sale_price);

        if (Number.isNaN(numeric)) {
            variant.sale_price = "";
            return;
        }

        variant.sale_price = numeric.toFixed(2);
    };

    /**
     * Remove a variant image.
     */
    const removeVariantImage = (variantIndex, imgIndex) => {
        const variant = formVariants.value[variantIndex];

        if (!variant || !Array.isArray(variant.images)) {
            return;
        }

        variant.images.splice(imgIndex, 1);
    };

    /**
     * Build payload variants for submission.
     */
    const buildPayloadVariants = () => {
        return formVariants.value.map((variant) => {
            const options = Array.isArray(variant.options)
                ? variant.options
                      .map((option) => ({
                          name: String(option?.name ?? "").trim(),
                          value: String(option?.value ?? "").trim(),
                      }))
                      .filter((option) => option.name !== "" && option.value !== "")
                : [];

            const stock = Number.isFinite(Number(variant.stock))
                ? Math.max(0, parseInt(variant.stock, 10))
                : 0;

            const weight =
                variant.weight !== null && variant.weight !== ""
                    ? String(variant.weight).trim()
                    : null;

            const ean =
                variant.ean && String(variant.ean).trim() !== ""
                    ? String(variant.ean).trim()
                    : null;

            const salePrice =
                variant.sale_price !== null &&
                variant.sale_price !== undefined &&
                variant.sale_price !== ""
                    ? String(variant.sale_price).trim()
                    : null;

            const saleStart = localToUtc(variant.sale_start_local);
            const saleEnd = localToUtc(variant.sale_end_local);

            const variantImages = Array.isArray(variant.images)
                ? variant.images
                      .map((img) => (typeof img === "string" ? img.trim() : ""))
                      .filter((img) => img !== "")
                : [];

            return {
                id: variant.id || 0,
                sku: variant.sku.trim(),
                ean,
                price:
                    variant.price !== null && variant.price !== undefined
                        ? String(variant.price).trim()
                        : "",
                sale_price: salePrice,
                sale_start: saleStart,
                sale_end: saleEnd,
                currency: variant.currency
                    ? String(variant.currency).trim().toUpperCase()
                    : baseCurrency.value,
                stock,
                weight,
                active: Boolean(variant.active),
                is_digital: Boolean(variant.is_digital),
                options,
                images: variantImages.length > 0 ? variantImages : null,
            };
        });
    };

    return {
        blankVariant,
        normaliseVariants,
        applyVariantDigitalDefaults,
        resetVariants,
        variantOptions,
        addVariant,
        removeVariant,
        duplicateVariant,
        addVariantOption,
        removeVariantOption,
        formatVariantPrice,
        formatVariantSalePrice,
        removeVariantImage,
        buildPayloadVariants,
    };
}
