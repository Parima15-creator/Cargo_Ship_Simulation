// Core behavior tests for fractional and multi-truck allocation.
import { describe, expect, it } from 'vitest';
import { optimizeLoad } from '../core/knapsack';
import { optimize01 } from '../core/knapsack01';
import type { CargoItem, Truck } from '../core/types';
const item = (id: string, weight: number, value: number): CargoItem => ({ id, name: id, weight, value });
const truck = (id: string, capacity: number): Truck => ({ id, name: id, capacity });
describe('fractional knapsack', () => {
  it('solves the textbook example', () => { const r = optimizeLoad([item('a', 10, 60), item('b', 20, 100), item('c', 30, 120)], [truck('t', 50)]); expect(r.totals.valueLoaded).toBeCloseTo(240); });
  it('splits one item across trucks and preserves total fraction', () => { const r = optimizeLoad([item('a', 10, 100)], [truck('a', 4), truck('b', 6)]); expect(r.allocations).toHaveLength(2); expect(r.allocations.reduce((s, a) => s + a.fraction, 0)).toBeCloseTo(1); });
  it('loads all items when capacity is ample', () => { const r = optimizeLoad([item('a', 2, 4), item('b', 3, 9)], [truck('t', 10)]); expect(r.totals.weightLoaded).toBe(5); expect(r.leftoverItems).toHaveLength(0); });
  it('handles zero weight positive value and deterministic tied ratios', () => { const r = optimizeLoad([item('a', 2, 4), item('b', 1, 2), item('free', 0, 5)], [truck('t', 2)]); expect(r.totals.valueLoaded).toBeCloseTo(9); expect(r.allocations[0].itemId).toBe('free'); });
  it('rejects zero truck capacity', () => { expect(() => optimizeLoad([item('a', 1, 1)], [truck('t', 0)])).toThrow(); });
  it('handles small floating point weights', () => { const r = optimizeLoad([item('a', 0.0003, 0.9)], [truck('t', 0.0002)]); expect(r.totals.weightLoaded).toBeCloseTo(0.0002); expect(r.totals.valueLoaded).toBeCloseTo(0.6); });
  it('matches or beats the 0/1 answer and respects capacities for generated cases', () => {
    for (let seed = 1; seed <= 40; seed++) { let x = seed; const rand = () => ((x = (x * 16807) % 2147483647) / 2147483647); const items = Array.from({ length: 8 }, (_, i) => item(`i${i}`, 1 + Math.floor(rand() * 8), 1 + Math.floor(rand() * 30))); const trucks = [truck('t1', 5), truck('t2', 6)]; const r = optimizeLoad(items, trucks); expect(r.totals.valueLoaded + 1e-8).toBeGreaterThanOrEqual(optimize01(items, 11)); expect(r.totals.weightLoaded).toBeLessThanOrEqual(11 + 1e-8); r.perTruck.forEach(t => expect(t.weightLoaded).toBeLessThanOrEqual(t.capacity + 1e-8)); }
  });
});
