<?php
/**
 * Main browser entry point for the Cargo Load Optimizer.
 *
 * This file starts the visitor session, creates the session-scoped CSRF token
 * used by state-changing API requests, and renders the complete application
 * shell. The shell contains the navigation, optimizer step containers,
 * algorithm-theory content, accessibility attributes, and static asset links.
 * The JavaScript application fills the step containers with the current
 * trucks, cargo items, and optimization results returned by api.php.
 */
declare(strict_types=1);
session_start();
// Create the token once per session so JavaScript can authenticate write requests.
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="csrf-token" content="<?=htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8')?>">
        <title>Cargo Load Optimizer</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body>
        <div class="app" id="app">
        <header class="topbar"><div class="brand"><div class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 17 17 6M8 6h9v9"/></svg></div><span>Cargo <b>Load Optimizer</b></span></div><div class="header-actions"><span class="status-dot"></span></div></header>
        <main class="layout">
            <aside class="rail">
                <div class="rail-label">WORKSPACE</div>
                <button class="rail-link active" data-tab="optimizer">
                    <span>▦</span> Load optimizer</button>
                    <button class="rail-link" data-tab="theory">
                        <span>⌘</span> Algorithm theory</button>
                        <div class="rail-foot">CARGO SYSTEMS<br>
                            <span>DAA LAB · PHP EDITION</span>
                        </div>
            </aside>
<section class="content">
    <div class="page-heading"><div>
        <div class="eyebrow" id="eyebrow">DELIVERY OPERATIONS / STEP 01</div>
        <h1 id="pageTitle">Cargo load optimizer</h1>
        <p id="pageSubtitle">Make every kilogram count across your delivery fleet.</p>
    </div>
    <div class="headline-stat">
        <span>FLEET CAPACITY</span>
        <strong class="mono" id="fleetCapacity">0 <small>kg</small></strong>
    </div>
</div>
<div id="notice" role="status"></div>
<div id="optimizerView"><div class="stepper">
    <button class="step current" data-step="1">
        <span class="step-num">1</span><span>Trucks</span><i></i></button>
        <button class="step" data-step="2">
            <span class="step-num">2</span><span>Items</span><i></i></button>
            <button class="step" data-step="3">
                <span class="step-num">3</span>
                <span>Results</span></button></div>
                <div id="stepContent">
                </div>
            </div>
<div id="theoryView" hidden>
    <div class="theory-callout">
        <div class="theory-symbol">↗</div>
        <div><span class="section-index">THE GREEDY PROPERTY</span>
        <h2>More value per kilogram.<br>Every single time.</h2>
        <p>Fractional knapsack is a classic optimization problem where the greedy choice is globally optimal.</p>
    </div>
</div>
<div class="theory-grid">
    <article class="panel theory-card">
        <span class="section-index">01 / PROBLEM</span>
        <h3>What are we solving?</h3><p>Given weighted items and a fleet with limited capacity, choose how much of each item to load to maximize value. Items may be split.</p>
        <div class="equation mono">maximize Σ valueᵢ · xᵢ<br>
        <small>subject to Σ weightᵢ · xᵢ ≤ capacity, 0 ≤ xᵢ ≤ 1</small>
    </div>
</article>
<article class="panel theory-card">
    <span class="section-index">02 / WHY GREEDY WORKS</span>
    <h3>The exchange argument</h3>
    <p>If a lower-ratio item is loaded while a higher-ratio item remains, replacing equal weight increases value. Repeating this exchange gives the descending ratio order and an optimal solution.</p>
</article>
<article class="panel theory-card">
    <span class="section-index">03 / GREEDY STRATEGY</span>
    <h3>How the allocation works</h3>
    <p>Calculate value divided by weight, sort once in descending order with stable deterministic ties, then pour each item through trucks in configured order. An item can continue into the next truck.</p>
    <div class="equation mono">ratio = value / weight<br>
    <small>allocated value = item value × allocated fraction</small>
</div>
</article>
<article class="panel theory-card">
    <span class="section-index">04 / WORKED EXAMPLE</span>
    <h3>Capacity 50 kg</h3><p>Items: (10 kg, ?60), (20 kg, ?100), (30 kg, ?120). Ratios are 6, 5 and 4. Load the first two completely and 20 kg (⅔) of the third.</p>
    <div class="equation mono">?60 + ?100 + (⅔ × ?120) = ?240</div>
</article>
</div>
<section class="panel complexity-panel">
    <div class="panel-title"><div>
        <span class="section-index">05 / COMPLEXITY</span>
        <h2>Fast by construction.</h2><p>Sorting does the heavy lifting. The greedy pass is linear.</p>
    </div>
</div>
<div class="complexity-stats"><div>
    <span>TIME · BEST</span>
    <b class="mono">O(n log n + m)</b>
    <small>Sorting and the linear fleet pass.</small>
</div>
<div>
    <span>TIME · AVERAGE</span>
    <b class="mono">O(n log n + m)</b>
    <small>Comparison sorting dominates the work.</small>
</div>
<div>
    <span>TIME · WORST</span>
    <b class="mono">O(n log n + m)</b>
    <small>Sorting plus allocation across all trucks.</small>
</div>
<div>
    <span>SPACE · BEST</span>
    <b class="mono">O(n + m)</b>
    <small>Sorted entries, allocations, and truck summaries.</small>
</div>
<div>
    <span>SPACE · AVERAGE</span>
    <b class="mono">O(n + m)</b>
    <small>The same report structures are retained.</small>
</div>
<div>
    <span>SPACE · WORST</span>
    <b class="mono">O(n + m)</b>
    <small>All items and trucks may appear in the report.</small>
</div>
</div>
</section>
<section class="panel contrast-panel"><div>
    <span class="section-index">06 / FRACTIONAL VS 0/1</span>
    <h2>Why divisibility matters</h2>
    <p>For 0/1 knapsack, each item must be taken whole. Greedy ratio ordering can miss the best combination; dynamic programming takes pseudo-polynomial O(nW) time. Fractional knapsack is solved optimally by greedy sorting.</p>
</div>
<div class="contrast-example">
    <span class="section-index">COUNTEREXAMPLE · CAPACITY 10</span>
    <div class="contrast-items"><span>A <b class="mono">6 kg · ?12</b></span><span>B <b class="mono">5 kg · ?10</b></span><span>C <b class="mono">5 kg · ?10</b></span></div><small>Ratio greedy picks A (?12); 0/1 optimum picks B and C (?20). Fractional items can be split, so ratio ordering remains optimal.</small></div></section>
<section class="panel info-panel">
    <div class="panel-title"><div>
        <span class="section-index">07 / ADVANTAGES</span>
        <h2>Advantages of Fractional Knapsack</h2>
        <p>Why the greedy strategy is useful for divisible resources.</p>
    </div></div>
    <ol class="explanation-list">
        <li><b>Optimal solution:</b> It maximizes total value by selecting the highest value-to-weight ratios first.</li>
        <li><b>Simple greedy approach:</b> Each choice is easy to explain because the best available ratio is selected first.</li>
        <li><b>Fractional selection:</b> An item can be divided when only part of its weight fits.</li>
        <li><b>Efficient execution:</b> Sorting gives an overall time complexity of O(n log n + m).</li>
        <li><b>Easy implementation:</b> The solution uses sorting followed by one allocation pass.</li>
        <li><b>Better resource utilization:</b> Remaining capacity can be filled with portions of other items.</li>
        <li><b>Flexible allocation:</b> It supports different item weights, values, and fleet capacities.</li>
    </ol>
</section>
<section class="panel info-panel">
    <div class="panel-title"><div>
        <span class="section-index">08 / DISADVANTAGES</span>
        <h2>Disadvantages of Fractional Knapsack</h2>
        <p>Conditions and limitations to consider before applying it.</p>
    </div></div>
    <ol class="explanation-list">
        <li><b>Items must be divisible:</b> It is not suitable for indivisible goods such as laptops, furniture, or machines.</li>
        <li><b>Limited problem scope:</b> It cannot directly solve problems where items must be accepted whole or rejected whole.</li>
        <li><b>Requires accurate data:</b> Every item needs a reliable weight and value for its ratio.</li>
        <li><b>Greedy limitation:</b> Ratio ordering is not always optimal for the 0/1 knapsack problem.</li>
        <li><b>Basic constraints only:</b> The model does not include dimensions, fragility, compatibility, or delivery priority.</li>
        <li><b>Real-world handling:</b> Packaging or transportation rules may prevent an item from being split.</li>
        <li><b>No route optimization:</b> It maximizes cargo value but does not calculate delivery routes.</li>
    </ol>
</section>
<section class="panel info-panel">
    <div class="panel-title"><div>
        <span class="section-index">09 / APPLICATIONS</span>
        <h2>Applications of Fractional Knapsack</h2>
        <p>Examples of divisible-resource allocation in practice.</p>
    </div></div>
    <ol class="explanation-list">
        <li><b>Cargo and transportation:</b> Load trucks, ships, and aircraft to maximize cargo value.</li>
        <li><b>Resource allocation:</b> Distribute raw materials, storage, or computing capacity by importance.</li>
        <li><b>Budget and investment planning:</b> Allocate funds among projects or investments when partial funding is allowed.</li>
        <li><b>Warehouse management:</b> Prioritize goods when storage weight or space is limited.</li>
        <li><b>Food and supply distribution:</b> Divide grains, fuel, water, and other essential supplies.</li>
        <li><b>Cloud computing:</b> Distribute processing time, bandwidth, or capacity among prioritized tasks.</li>
        <li><b>Manufacturing and agriculture:</b> Allocate raw materials, fertilizer, seeds, or storage capacity.</li>
        <li><b>Time and emergency management:</b> Assign limited time or divisible emergency supplies to maximize benefit.</li>
    </ol>
</section></div>

<footer class="footer">
    <span>CARGO SYSTEMS</span>
    <span>Built for efficient deliveries</span>
</footer></section></main></div><script src="assets/js/main.js">

</script>
</body>
</html>
