// Pure fractional multi-truck greedy optimizer and its human-readable trace.
import type { CargoItem, Truck, OptimizationResult, Allocation } from './types';
import { validateFleet, validateItems } from './validation';

/**
 * Fills trucks sequentially by descending value/weight ratio.
 * Sorting dominates at O(n log n); allocation and fleet scans take O(n + m),
 * and sorted items/allocations/trace use O(n + m) space (O(n) for fixed fleet).
 */
export function optimizeLoad(items: CargoItem[], trucks: Truck[]): OptimizationResult {
  const started = performance.now();
  const issues = [...validateFleet(trucks), ...validateItems(items)];
  if (issues.length) throw new Error(issues.map(x => x.message).join(' '));
  let comparisons = 0;
  const sorted = items.map((item, index) => ({ item, index, ratio: item.weight === 0 ? (item.value > 0 ? Infinity : -Infinity) : item.value / item.weight }))
    .sort((a, b) => { comparisons++; return b.ratio - a.ratio || b.item.value - a.item.value || a.index - b.index; });
  const perTruck = trucks.map(t => ({ truckId: t.id, capacity: t.capacity, weightLoaded: 0, valueLoaded: 0, allocations: [] as Allocation[] }));
  const allocations: Allocation[] = [], leftoverItems = [], trace: string[] = [];
  let truckIndex = 0;
  for (const entry of sorted) {
    const { item, ratio } = entry;
    if (item.weight === 0) {
      if (item.value > 0 && perTruck.length) { const allocation = { itemId: item.id, truckId: perTruck[0].truckId, weightTaken: 0, fraction: 1, valueGained: item.value }; allocations.push(allocation); perTruck[0].allocations.push(allocation); perTruck[0].valueLoaded += item.value; trace.push(`Took 100% of ${item.name} (zero weight, value ${item.value}).`); }
      else trace.push(`${item.name} has zero weight and value; ignored.`);
      continue;
    }
    let remaining = item.weight, gained = 0;
    while (remaining > 1e-9 && truckIndex < perTruck.length) {
      const truck = perTruck[truckIndex], space = Math.max(0, truck.capacity - truck.weightLoaded);
      if (space <= 1e-9) { truckIndex++; continue; }
      const taken = Math.min(remaining, space), value = item.value * (taken / item.weight);
      const allocation = { itemId: item.id, truckId: truck.truckId, weightTaken: taken, fraction: taken / item.weight, valueGained: value };
      allocations.push(allocation); truck.allocations.push(allocation); truck.weightLoaded += taken; truck.valueLoaded += value; remaining -= taken; gained += value;
      trace.push(`Took ${(allocation.fraction * 100).toFixed(1)}% of ${item.name} (ratio ${Number.isFinite(ratio) ? ratio.toPrecision(4) : '∞'}) → ${trucks[truckIndex].name} remaining ${Math.max(0, trucks[truckIndex].capacity - truck.weightLoaded).toPrecision(4)} kg.`);
      if (truck.capacity - truck.weightLoaded <= 1e-9) truckIndex++;
    }
    if (remaining > 1e-9) { leftoverItems.push({ itemId: item.id, remainingWeight: remaining, reason: 'Fleet capacity is full.' }); trace.push(`Left ${(remaining / item.weight * 100).toFixed(1)}% of ${item.name} behind: fleet capacity is full.`); }
  }
  const capacity = trucks.reduce((sum, t) => sum + t.capacity, 0), weightLoaded = perTruck.reduce((sum, t) => sum + t.weightLoaded, 0), valueLoaded = perTruck.reduce((sum, t) => sum + t.valueLoaded, 0);
  return { allocations, perTruck, totals: { weightLoaded, valueLoaded, capacity }, leftoverItems, trace, stats: { n: items.length, m: trucks.length, sortComparisonsEstimate: comparisons, elapsedMs: performance.now() - started } };
}
