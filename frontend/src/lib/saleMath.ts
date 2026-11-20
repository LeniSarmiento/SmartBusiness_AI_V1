export function cents(value: string): number {
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(value)) {
        throw new Error("Invalid money");
    }
    const [whole, fraction = ""] = value.split(".");
    return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
}
export function lineTotal(
    price: string,
    quantity: number,
    basisPoints: number,
) {
    const subtotal = BigInt(cents(price)) * BigInt(quantity);
    const tax = (subtotal * BigInt(basisPoints) + 5000n) / 10000n;
    return {
        subtotal: Number(subtotal),
        tax: Number(tax),
        total: Number(subtotal + tax),
    };
}
