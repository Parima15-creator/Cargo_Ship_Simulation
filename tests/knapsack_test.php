<?php

/**
 * Behavioral checks for the pure fractional-knapsack implementation.
 *
 * The script uses no database. It covers the textbook result, multi-truck
 * splitting, validation failures, deterministic ordering, decimal and extreme
 * numeric values, leftovers, zero-weight cargo, and a larger data set.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/knapsack.php';
// Compare floating-point results with both absolute and relative tolerances.
function assertNear(float $got, float $expected, string $label): void
{
    if (abs($got - $expected) > max(1e-8, abs($expected) * 1e-10)) {
        throw new RuntimeException("{$label}: expected {$expected}, got {$got}");
    }echo "PASS {$label}\n";
}
// Build compact test fixtures with the same shape used by production code.
function item(int $id, float $w, float $v, string $name = 'Item'): array
{
    return ['id' => $id,'name' => $name.' '.$id,'weight' => $w,'value' => $v];
}
function truck(int $id, float $c): array
{
    return ['id' => $id,'name' => 'Truck '.$id,'capacity' => $c];
}
$r = optimizeLoad([item(1, 10, 60),item(2, 20, 100),item(3, 30, 120)], [truck(1, 50)]);
assertNear($r['totals']['valueLoaded'], 240, 'classic example');
$r = optimizeLoad([item(1, 10, 100)], [truck(1, 4),truck(2, 6)]);
assertNear(array_sum(array_column($r['allocations'], 'fraction')), 1, 'item split across multiple trucks');
if (count($r['allocations']) !== 2) {
    throw new RuntimeException('expected split over two trucks');
}echo "PASS multiple truck combined capacity\n";
$r = optimizeLoad([item(1, 2, 4),item(2, 3, 9)], [truck(1, 10)]);
assertNear($r['totals']['weightLoaded'], 5, 'all items fit');
try {
    optimizeLoad([], [truck(1, 1)]);
    throw new RuntimeException('empty cargo was accepted');
} catch (InvalidArgumentException) {
    echo "PASS empty items rejected\n";
}
try {
    optimizeLoad([item(1, 1, 1)], []);
    throw new RuntimeException('empty fleet was accepted');
} catch (InvalidArgumentException) {
    echo "PASS empty fleet rejected\n";
}
$r = optimizeLoad([item(1, 1, 10),item(2, 1, 10)], [truck(1, 1)]);
if ($r['sortedOrder'][0]['itemId'] !== 1) {
    throw new RuntimeException('equal ratio tie was not deterministic');
}assertNear($r['totals']['valueLoaded'], 10, 'equal ratios deterministic');
$r = optimizeLoad([item(1, 0.0003, 0.9)], [truck(1, 0.0002)]);
assertNear($r['totals']['weightLoaded'], 0.0002, 'decimal weights');
assertNear($r['totals']['valueLoaded'], 0.6, 'decimal proportional value');
$r = optimizeLoad([item(1, 1.0e-10, 2.0)], [truck(1, 5.0e-11)]);
assertNear($r['totals']['weightLoaded'], 5.0e-11, 'very small decimal weights');
assertNear($r['totals']['valueLoaded'], 1.0, 'very small proportional value');
$r = optimizeLoad([item(1, 1, 1.0e20)], [truck(1, 0.5)]);
assertNear($r['totals']['valueLoaded'], 5.0e19, 'very large values');
$r = optimizeLoad([item(1, 1, 1),item(2, 1, 2)], [truck(1, 0.5)]);
if (count($r['leftoverItems']) !== 2) {
    throw new RuntimeException('overweight items were not reported');
}echo "PASS items beyond total capacity reported\n";
$r = optimizeLoad([item(1, 4, 8),item(2, 5, 20),item(3, 2, 4)], [truck(1, 3),truck(2, 6)]);
if ($r['totals']['weightLoaded'] > 9 + 1e-9 || count($r['perTruck']) !== 2) {
    throw new RuntimeException('different truck capacities failed');
}echo "PASS multiple trucks with different capacities\n";
try {
    optimizeLoad([item(1, -1, 2)], [truck(1, 1)]);
    throw new RuntimeException('negative weight was accepted');
} catch (InvalidArgumentException) {
    echo "PASS invalid input rejected\n";
}
$r = optimizeLoad([item(1, 0, 5)], [truck(1, 1)]);
assertNear($r['totals']['valueLoaded'], 5, 'zero weight positive value');
$items = [];
for ($i = 1;$i <= 10000;$i++) {
    $items[] = item($i,1,($i % 17) + 1,'Bulk');
}$r = optimizeLoad($items,[truck(1,5000)]);
assertNear($r['totals']['weightLoaded'],5000,'10,000 item dataset capacity');
echo "All algorithm checks passed.\n";
