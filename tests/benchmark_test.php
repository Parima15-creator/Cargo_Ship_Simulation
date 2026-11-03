<?php

/**
 * Standalone performance probe for the allocation engine.
 *
 * It runs deterministic item sets from 100 through 100,000 entries, records
 * the optimizer's measured milliseconds and trace size, and prints JSON for
 * easy comparison between PHP/runtime changes. It requires no database.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/knapsack.php';
$rows = [];
// Measure increasing input sizes with a single half-capacity benchmark truck.
foreach ([100,1000,10000,100000] as $n) {
    $items = [];
    for ($i = 0;$i < $n;$i++) {
        $items[] = ['id' => $i + 1,'name' => 'Bench '.$i,'weight' => 1 + ($i % 100),'value' => 1 + (($i * 37) % 1000)];
    }
    $r = optimizeLoad($items, [['id' => 1,'name' => 'Benchmark','capacity' => $n * 0.5]]);
    $rows[] = ['n' => $n,'ms' => round($r['stats']['elapsedMs'], 2),'trace' => count($r['trace'])];
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;
