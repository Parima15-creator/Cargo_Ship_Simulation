// Shared data contracts used by the pure optimizer and dashboard.
export type Truck = { id: string; name: string; capacity: number };
export type CargoItem = { id: string; name: string; weight: number; value: number };
export type Allocation = { itemId: string; truckId: string; weightTaken: number; fraction: number; valueGained: number };
export type TruckSummary = { truckId: string; capacity: number; weightLoaded: number; valueLoaded: number; allocations: Allocation[] };
export type LeftoverItem = { itemId: string; remainingWeight: number; reason: string };
export type OptimizationResult = { allocations: Allocation[]; perTruck: TruckSummary[]; totals: { weightLoaded: number; valueLoaded: number; capacity: number }; leftoverItems: LeftoverItem[]; trace: string[]; stats: { n: number; m: number; sortComparisonsEstimate: number; elapsedMs: number } };
