import assert from "node:assert/strict";
import test from "node:test";
import { buildGatewayOptions } from "../gatewayOptions.js";

test("includes bank transfer when enabled and cart has items", () => {
    const options = buildGatewayOptions(
        {
            bank_transfer: { enabled: true, label: "Wire transfer" },
            cod: { enabled: false },
        },
        [{ id: 1 }]
    );

    const bank = options.find((option) => option.id === "bank_transfer");

    assert.ok(bank);
    assert.equal(bank.label, "Wire transfer");
});

test("omits offline gateways when cart is empty", () => {
    const options = buildGatewayOptions({
        bank_transfer: { enabled: true, label: "Wire transfer" },
        cod: { enabled: true, label: "COD" },
    });

    assert.ok(!options.some((option) => option.id === "bank_transfer"));
    assert.ok(!options.some((option) => option.id === "cod"));
});

test("returns hosted gateways only when configured", () => {
    const options = buildGatewayOptions({
        stripe: { publishable_key: "pk_test", secret_key: "sk_test" },
        paypal: { client_id: "", client_secret: "" },
    });

    assert.deepEqual(options.map((option) => option.id), ["stripe"]);
});
