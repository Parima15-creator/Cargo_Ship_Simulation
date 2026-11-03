<!-- Overview, setup, algorithm notes, and team responsibility placeholders. -->
# Atlas Cargo Load Optimizer

A browser-only delivery load planning dashboard built with Vite, React, and TypeScript. Configure a fleet, add cargo, then maximize delivered value with the fractional knapsack greedy algorithm. Fleet and cargo data persist in local storage.

## Run locally

```sh
npm install
npm run dev
npm test
```

## Algorithm

The optimizer computes each item's value-to-weight ratio, sorts descending, and fills trucks sequentially. Since cargo is divisible, a partially loaded item can continue into the next truck. A fleet is equivalent to one knapsack with the sum of its capacities, so this greedy ordering maximizes combined value. Positive-value zero-weight items are accepted at no capacity cost; zero-weight, zero-value items are skipped.

Sorting takes **O(n log n)**. The allocation pass takes **O(n + m)** for n items and m trucks. Auxiliary storage is **O(n)** for a fixed fleet (O(n + m) including per-truck summaries and the generated trace).

The Theory tab includes a short exchange proof, a comparison to 0/1 knapsack, a browser runtime benchmark, and the results page can compute a small 0/1 dynamic-programming comparison.

## Screenshots

_Screenshot placeholder — add dashboard and results screenshots here._

## Team responsibilities

| Responsibility | Team member |
| --- | --- |
| Algorithm core | Name TBD |
| Validation and tests | Name TBD |
| UI components | Name TBD |
| Charts and theory page | Name TBD |
| Documentation | Name TBD |
