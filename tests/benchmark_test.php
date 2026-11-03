<?php
declare(strict_types=1);
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/knapsack.php';
$rows=[];
foreach([100,1000,10000,100000] as $n){
    $items=[];
    for($i=0;$i<$n;$i++)$items[]=['id'=>$i+1,'name'=>'Bench '.$i,'weight'=>1+($i%100),'value'=>1+(($i*37)%1000)];
    $r=optimizeLoad($items,[['id'=>1,'name'=>'Benchmark','capacity'=>$n*0.5]]);
    $rows[]=['n'=>$n,'ms'=>round($r['stats']['elapsedMs'],2),'trace'=>count($r['trace'])];
}
echo json_encode($rows,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
