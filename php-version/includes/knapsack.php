<?php
declare(strict_types=1);

/** Pure fleet-wide fractional knapsack. Items are sorted once, then poured through trucks in order. */
function optimizeLoad(array $items, array $trucks): array
{
    if (!$trucks) throw new InvalidArgumentException('Add at least one truck before optimizing.');
    $issues = array_merge(validateFleet($trucks), validateItems($items));
    if ($issues) throw new InvalidArgumentException(implode(' ', $issues));
    $started = hrtime(true);
    $sorted = [];
    foreach ($items as $i => $item) {
        $weight = (float)$item['weight']; $value = (float)$item['value'];
        $sorted[] = ['item' => $item, 'index' => $i, 'ratio' => $weight == 0 ? ($value > 0 ? INF : -INF) : $value / $weight];
    }
    usort($sorted, static function(array $a, array $b): int {
        $cmp = $b['ratio'] <=> $a['ratio'];
        if ($cmp !== 0) return $cmp;
        $cmp = (float)$b['item']['value'] <=> (float)$a['item']['value'];
        return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
    });
    $perTruck = [];
    foreach ($trucks as $truck) $perTruck[] = ['truckId'=>(int)$truck['id'], 'truckName'=>$truck['name'], 'capacity'=>(float)$truck['capacity'], 'weightLoaded'=>0.0, 'valueLoaded'=>0.0, 'allocations'=>[]];
    $allocations = []; $leftovers = []; $trace = []; $truckIndex = 0; $epsilon = 1e-12;
    foreach ($sorted as $entry) {
        $item = $entry['item']; $weight = (float)$item['weight']; $value = (float)$item['value']; $ratio = $entry['ratio'];
        $trace[] = 'Ranked ' . $item['name'] . ' at value/weight ratio ' . (is_finite($ratio) ? sprintf('%.6g', $ratio) : '∞') . '.';
        if ($weight <= $epsilon) {
            if ($value > 0 && $perTruck) {
                $a = ['itemId'=>(int)$item['id'], 'itemName'=>$item['name'], 'truckId'=>$perTruck[0]['truckId'], 'truckName'=>$perTruck[0]['truckName'], 'weightTaken'=>0.0, 'fraction'=>1.0, 'valueGained'=>$value];
                $allocations[]=$a; $perTruck[0]['allocations'][]=$a; $perTruck[0]['valueLoaded'] += $value;
                $trace[] = 'Took 100% of ' . $item['name'] . ' (zero weight, value ' . $value . ').';
            } else $trace[] = $item['name'] . ' has zero weight and value; ignored.';
            continue;
        }
        $remaining = $weight;
        while ($remaining > $epsilon && $truckIndex < count($perTruck)) {
            $truck = &$perTruck[$truckIndex]; $space = max(0.0, $truck['capacity'] - $truck['weightLoaded']);
            if ($space <= $epsilon) { unset($truck); $truckIndex++; continue; }
            $taken = min($remaining, $space); $fraction = $taken / $weight; $gained = $value * $fraction;
            $a = ['itemId'=>(int)$item['id'], 'itemName'=>$item['name'], 'truckId'=>$truck['truckId'], 'truckName'=>$truck['truckName'], 'weightTaken'=>$taken, 'fraction'=>$fraction, 'valueGained'=>$gained];
            $allocations[]=$a; $truck['allocations'][]=$a; $truck['weightLoaded'] += $taken; $truck['valueLoaded'] += $gained; $remaining -= $taken;
            $trace[] = sprintf('Allocated %.1f%% of %s (%.6g kg) to %s; gained %.6g value.', $fraction * 100, $item['name'], $taken, $truck['truckName'], $gained);
            $isFull = $truck['capacity'] - $truck['weightLoaded'] <= $epsilon;
            unset($truck);
            if ($isFull) $truckIndex++;
        }
        if ($remaining > $epsilon) { $leftovers[]=['itemId'=>(int)$item['id'], 'itemName'=>$item['name'], 'remainingWeight'=>$remaining, 'reason'=>'Fleet capacity is full.']; $trace[]=sprintf('Left %.1f%% of %s (%.6g kg) behind: fleet capacity is full.', $remaining / $weight * 100, $item['name'], $remaining); }
    }
    $capacity = array_sum(array_map(static fn($t)=>(float)$t['capacity'], $trucks));
    $loaded = array_sum(array_column($perTruck, 'weightLoaded')); $totalValue = array_sum(array_column($perTruck, 'valueLoaded'));
    $sortedOrder = array_map(static fn($e)=>['itemId'=>(int)$e['item']['id'], 'itemName'=>$e['item']['name'], 'ratio'=>is_finite($e['ratio'])?$e['ratio']:'Infinity'], $sorted);
    $fractionByItem=[];
    foreach ($allocations as $allocation) $fractionByItem[$allocation['itemId']] = ($fractionByItem[$allocation['itemId']] ?? 0.0) + $allocation['fraction'];
    $full=[]; $partial=[];
    foreach ($items as $item) {
        $fraction = $fractionByItem[(int)$item['id']] ?? 0.0;
        if ($fraction >= 1 - $epsilon) $full[]=$item['id']; elseif ($fraction > $epsilon) $partial[]=$item['id'];
    }
    foreach ($perTruck as &$truck) $truck['remainingCapacity'] = max(0, $truck['capacity'] - $truck['weightLoaded']);
    unset($truck);
    return ['allocations'=>$allocations, 'perTruck'=>$perTruck, 'totals'=>['weightLoaded'=>$loaded, 'valueLoaded'=>$totalValue, 'capacity'=>$capacity, 'utilizationPercentage'=>$capacity > 0 ? $loaded / $capacity * 100 : 0], 'leftoverItems'=>$leftovers, 'fullyLoadedItems'=>$full, 'partiallyLoadedItems'=>$partial, 'sortedOrder'=>$sortedOrder, 'trace'=>$trace, 'stats'=>['n'=>count($items), 'm'=>count($trucks), 'elapsedMs'=>(hrtime(true)-$started)/1e6]];
}
