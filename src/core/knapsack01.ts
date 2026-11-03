// Small-input 0/1 dynamic programming comparator for the results dashboard.
import type { CargoItem } from './types';
export function optimize01(items: CargoItem[], capacity: number): number {
  const unit = 1, limit = Math.floor(capacity * unit);
  if (items.length > 100 || limit > 5_000) throw new Error('Comparison is limited to 100 items and 5,000 capacity units.');
  const dp = new Float64Array(limit + 1);
  for (const item of items) {
    const w = Math.ceil(item.weight * unit);
    if (w <= 0 || w > limit) continue;
    for (let c = limit; c >= w; c--) dp[c] = Math.max(dp[c], dp[c - w] + item.value);
  }
  return dp[limit];
}
