import { describe, expect, it } from "vitest";
import { cents, lineTotal } from "./saleMath";
describe("Sale preview", () => {
    it("uses minor units and rounds tax per line", () => {
        expect(cents("10.05")).toBe(1005);
        expect(lineTotal("10.05", 2, 1900)).toEqual({
            subtotal: 2010,
            tax: 382,
            total: 2392,
        });
    });
    it("supports exempt products and zero prices", () => {
        expect(lineTotal("0.00", 5, 1900).total).toBe(0);
        expect(lineTotal("15.75", 3, 0).total).toBe(4725);
    });
    it("rounds half a cent up", () => {
        expect(lineTotal("0.05", 1, 1000)).toEqual({
            subtotal: 5,
            tax: 1,
            total: 6,
        });
    });
});
