export function cents(value: string): number {
    const [whole, fraction = ""] = value.split(".");
    return Number(whole) * 100 + Number(fraction.padEnd(2, "0").slice(0, 2));
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
