// Browser-only runtime measurement helpers used by the theory page.
import { optimizeLoad } from './knapsack';
import type { CargoItem, Truck } from './types';
export function measureOptimizer(size: number): number {
  const items: CargoItem[] = Array.from({ length: size }, (_, i) => ({ id: `b${i}`, name: `Item ${i}`, weight: 1 + (i % 100), value: 1 + ((i * 37) % 1000) }));
  const trucks: Truck[] = [{ id: 'bench', name: 'Benchmark', capacity: size * 0.5 }];
  return optimizeLoad(items, trucks).stats.elapsedMs;
}
