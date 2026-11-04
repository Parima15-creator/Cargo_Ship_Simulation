/**
 * Client-side controller for the Cargo Load Optimizer.
 *
 * This framework-free module owns browser state, renders the fleet, cargo, and
 * result steps, calls api.php with the session CSRF token, and converts the
 * returned allocation report into tables, charts, traces, and downloads.
 */
// Shared DOM, escaping, formatting, currency, and chart-color helpers.
const $ = (s) => document.querySelector(s),
  esc = (s) =>
    String(s).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    ),
  fmt = (n, d = 2) =>
    new Intl.NumberFormat("en-IN", { maximumFractionDigits: d }).format(
      Number(n) || 0,
    ),
  money = (n) => "₹" + fmt(n),
  colors = [
    "#00ED64",
    "#016BF8",
    "#FFC010",
    "#B45AF2",
    "#DB3030",
    "#18A999",
    "#FF7A45",
  ];
// Single source of truth for editable data, navigation, results, and charts.
const state = {
  trucks: [],
  items: [],
  csrf: document.querySelector("meta[name=csrf-token]").content,
  step: 1,
  tab: "optimizer",
  result: null,
  edit: null,
  chart: [],
};
// Send an API request and turn non-success responses into thrown errors.
async function request(action, data) {
  const r = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
    method: data ? "POST" : "GET",
    headers: data
      ? { "Content-Type": "application/json", "X-CSRF-Token": state.csrf }
      : {},
    body: data ? JSON.stringify(data) : undefined,
  });
  let j = await r.json();
  if (!r.ok) throw Error(j.error || "Request failed");
  return j;
}
// Show a temporary success or error message in the page status region.
function notice(msg, error = true) {
  const n = $("#notice");
  n.className = msg ? (error ? "inline-error" : "saved-label") : "";
  n.textContent = msg || "";
  if (msg)
    setTimeout(() => {
      if (n.textContent === msg) n.textContent = "";
    }, 4500);
}
// Calculate the headline totals from the currently edited browser state.
function totals() {
  return {
    capacity: state.trucks.reduce((a, t) => a + Number(t.capacity), 0),
    weight: state.items.reduce((a, i) => a + Number(i.weight), 0),
    value: state.items.reduce((a, i) => a + Number(i.value), 0),
  };
}
// Change optimizer step and redraw the active panel.
function setStep(n) {
  state.step = n;
  render();
}
// Refresh shared headings, step indicators, and the active step markup.
function render() {
  const t = totals();
  $("#fleetCapacity").innerHTML = `${fmt(t.capacity)} <small>kg</small>`;
  $("#eyebrow").textContent =
    `DELIVERY OPERATIONS / ${state.step === 3 ? "RESULTS" : `STEP 0${state.step}`}`;
  $("#pageTitle").textContent =
    state.step === 3 ? "Load report" : "Cargo load optimizer";
  document.querySelectorAll(".step").forEach((b) => {
    const n = +b.dataset.step;
    b.className = `step ${n === state.step ? "current" : n < state.step ? "complete" : ""}`;
    b.querySelector(".step-num").textContent = n < state.step ? "✓" : n;
  });
  $("#stepContent").innerHTML =
    state.step === 1
      ? fleetHTML()
      : state.step === 2
        ? itemsHTML()
        : resultHTML();
  bind();
}
// Render the truck editor, controls, and combined capacity summary.
function fleetHTML() {
  return `<section class="panel"><div class="panel-title"><div><span class="section-index">01 / FLEET</span><h2>Configure your fleet</h2><p>Set capacity for each vehicle in your delivery fleet.</p></div><span class="badge">${state.trucks.length} TRUCK${state.trucks.length === 1 ? "" : "S"}</span></div><div class="truck-configs">${state.trucks.map((t, i) => `<div class="truck-row"><div class="truck-color" style="background:${colors[i % colors.length]}">${String(i + 1).padStart(2, "0")}</div><label class="field"><span>TRUCK NAME</span><input data-truck-name="${i}" value="${esc(t.name)}"></label><label class="field capacity-field"><span>CAPACITY <small>KG</small></span><div class="input-unit"><input type="number" min="0.000001" step="any" data-truck-cap="${i}" value="${esc(t.capacity)}"><b>kg</b></div></label><button class="icon-button" data-remove-truck="${i}" aria-label="Remove truck">×</button></div>`).join("")}</div><div class="panel-actions"><button class="button secondary" id="addTruck" ${state.trucks.length >= 20 ? "disabled" : ""}>＋ Add truck</button><div class="right-actions"><span class="summary-text">Combined capacity <b class="mono">${fmt(totals().capacity)} kg</b></span><button class="button primary" id="continue">Continue →</button></div></div></section>`;
}
// Render the paged item editor, cargo table, and optimization controls.
function itemsHTML() {
  const t = totals(),
    page = Number(state.page || 0),
    size = 100,
    shown = state.items.slice(page * size, (page + 1) * size);
  return `<div class="stats-strip">${[
    ["ITEMS", fmt(state.items.length, 0)],
    ["TOTAL WEIGHT", fmt(t.weight) + " kg"],
    ["TOTAL VALUE", money(t.value)],
    ["FLEET CAPACITY", fmt(t.capacity) + " kg"],
  ]
    .map(
      (x) =>
        `<div class="summary-chip"><span>${x[0]}</span><b class="mono">${x[1]}</b></div>`,
    )
    .join(
      "",
    )}</div>${t.weight > t.capacity ? '<div class="warning-banner"><span>↗</span><div><b>Not everything fits</b><span>The optimizer selects the highest value per kilogram.</span></div></div>' : ""}<section class="panel item-form-panel"><div class="panel-title"><div><span class="section-index">02 / CARGO</span><h2>${state.edit ? "Edit item" : "Add items to your load"}</h2><p>Enter each item’s weight and delivery value or priority.</p></div></div><form class="item-inputs" id="itemForm"><label class="field item-name"><span>ITEM NAME</span><input id="iname" placeholder="e.g. Medical supplies" required value="${esc(state.edit?.name || "")}"></label><label class="field"><span>WEIGHT <small>KG</small></span><input id="iweight" type="number" min="0" step="any" placeholder="0.00" required value="${state.edit ? state.edit.weight : ""}"></label><label class="field"><span>VALUE <small>INR</small></span><input id="ivalue" type="number" min="0" step="any" placeholder="0.00" required value="${state.edit ? state.edit.value : ""}"></label><button class="button primary add-button">${state.edit ? "Save item" : "＋ Add item"}</button></form><div class="table-wrap"><table><thead><tr><th>ITEM</th><th>WEIGHT</th><th>VALUE</th><th>VALUE / KG</th><th></th></tr></thead><tbody>${shown.map((x) => `<tr><td><span class="item-dot"></span>${esc(x.name)}</td><td class="mono">${fmt(x.weight)} kg</td><td class="mono">${money(x.value)}</td><td class="mono ratio">${x.weight ? fmt(x.value / x.weight, 3) : x.value ? "∞" : "—"}</td><td class="row-actions"><button data-edit="${x.id}">Edit</button><button data-delete="${x.id}">Delete</button></td></tr>`).join("")}</tbody></table>${!state.items.length ? '<div class="empty-state"><span>◇</span><b>Your cargo list is empty</b><p>Add an item using the form above.</p></div>' : ""}</div><div class="item-footer"><div class="random-control"><span>Generate</span><input id="randomN" type="number" min="1" max="10000" value="100"><span>random items</span><button class="button secondary small" id="generate">Generate</button></div>${state.items.length ? '<button class="text-button danger-text" id="clearItems">Clear all items</button>' : ""}</div></section><div class="bottom-nav"><button class="button secondary" id="backFleet">← Back to fleet</button><button class="button primary" id="optimize" ${!state.items.length || !state.trucks.length ? "disabled" : ""}>Optimize load →</button></div>`;
}
// Render KPIs, allocation cards, charts, traces, leftovers, and exports.
function resultHTML() {
  if (!state.result)
    return '<section class="panel"><div class="empty-state"><b>No optimization result yet</b><p>Return to items and run the optimizer.</p></div></section>';
  const { result, trucks, items } = state.result,
    full = new Set(result.fullyLoadedItems.map(Number)),
    partial = new Set(result.partiallyLoadedItems.map(Number)),
    loaded = new Set([...full, ...partial]),
    itemMap = new Map(items.map((x) => [+x.id, x])),
    cap = result.totals.capacity;
  return `<div class="results-heading"><div><div class="eyebrow">OPTIMIZATION COMPLETE · ${Number(result.stats.elapsedMs).toFixed(2)} MS</div><h2>Your fleet is ready to roll.</h2></div><div class="export-actions"><button class="button secondary small" data-export="csv">↓ CSV</button><button class="button secondary small" data-export="json">↓ JSON</button></div></div><div class="kpi-grid">${[
    ["TOTAL VALUE LOADED", money(result.totals.valueLoaded), "?"],
    ["TOTAL WEIGHT", fmt(result.totals.weightLoaded) + " kg", "⇅"],
    [
      "FLEET UTILIZATION",
      fmt(result.totals.utilizationPercentage, 1) + "%",
      "◉",
    ],
    ["ITEMS LOADED", `${full.size} full · ${partial.size} partial`, "✓"],
  ]
    .map(
      (x) =>
        `<div class="kpi-card"><div class="kpi-top"><span>${x[0]}</span><i>${x[2]}</i></div><b class="mono">${x[1]}</b>${x[0] === "ITEMS LOADED" ? `<small>${result.leftoverItems.length} left behind</small>` : ""}</div>`,
    )
    .join(
      "",
    )}</div><section class="panel result-panel"><div class="panel-title"><div><span class="section-index">03 / ALLOCATION</span><h2>Fleet allocation</h2><p>Value-dense cargo is distributed in ratio order.</p></div></div><div class="truck-result-grid">${result.perTruck.map((tr, i) => `<div class="truck-result"><div class="truck-result-head"><div class="truck-color" style="background:${colors[i % colors.length]}">${String(i + 1).padStart(2, "0")}</div><div><b>${esc(tr.truckName)}</b><span class="mono">${fmt(tr.weightLoaded)} / ${fmt(tr.capacity)} kg</span></div><strong class="mono">${money(tr.valueLoaded)}</strong></div><div class="progress-track">${tr.allocations.map((a) => `<i style="width:${tr.capacity ? (a.weightTaken / tr.capacity) * 100 : 0}%;background:${colors[items.findIndex((x) => +x.id === a.itemId) % colors.length]}" title="${esc(a.itemName)}"></i>`).join("")}</div><div class="usage-line"><span>CAPACITY USED</span><b class="mono">${fmt(tr.capacity ? (tr.weightLoaded / tr.capacity) * 100 : 0, 1)}%</b></div><div class="alloc-list">${tr.allocations.map((a) => `<div class="allocation-row"><span class="alloc-color" style="background:${colors[items.findIndex((x) => +x.id === a.itemId) % colors.length]}"></span><span class="alloc-name">${esc(a.itemName)}</span><span class="mono">${fmt(a.fraction * 100, 1)}%</span><span class="mono">${fmt(a.weightTaken)} kg</span><b class="mono">${money(a.valueGained)}</b></div>`).join("")}</div></div>`).join("")}</div></section><div class="chart-grid"><section class="panel chart-panel"><div class="chart-heading"><b>VALUE BY TRUCK</b><span>Contribution to total loaded value</span></div><canvas id="truckChart" height="180"></canvas></section><section class="panel chart-panel"><div class="chart-heading"><b>VALUE DENSITY ORDER</b><span>Ratio ranking · status of each cargo item</span></div><canvas id="itemChart" height="180"></canvas><div class="legend"><span><i class="green-dot"></i>Full</span><span><i class="yellow-dot"></i>Partial</span><span><i class="gray-dot"></i>Left behind</span></div></section></div>${result.leftoverItems.length ? `<section class="panel leftovers"><div class="panel-title"><div><span class="section-index">REMAINING CARGO</span><h2>Left behind</h2></div><span class="badge warning-badge">${result.leftoverItems.length} ITEMS</span></div><div class="leftover-list">${result.leftoverItems.map((x) => `<div><b>${esc(x.itemName)}</b><span class="mono">${fmt(x.remainingWeight)} kg remaining</span><small>${esc(x.reason)}</small></div>`).join("")}</div></section>` : ""}<section class="panel trace-panel"><details open><summary><span><span class="section-index">GREEDY TRACE</span><b>Step-by-step explanation</b><small>${result.trace.length} decisions · ratio order</small></span><i>⌄</i></summary><div class="trace-intro">Items are ranked by value per kilogram; the optimizer pours each item into available fleet capacity in that order.</div><ol>${result.trace.map((line, i) => `<li><span class="trace-number">${String(i + 1).padStart(2, "0")}</span>${esc(line)}</li>`).join("")}</ol></details></section><div class="bottom-nav results-bottom"><button class="button danger-button" id="reset">Reset all</button></div>`;
}
async function bind() {
  document.querySelectorAll("[data-step]").forEach(
    (x) =>
      (x.onclick = () => {
        let n = +x.dataset.step;
        if (n < 3) setStep(n);
        else if (state.result) setStep(3);
      }),
  );
  document.querySelectorAll("[data-truck-name]").forEach((x) => {
    x.oninput = () => (state.trucks[+x.dataset.truckName].name = x.value);
    x.onchange = async () => {
      try {
        await saveTrucks();
      } catch (e) {
        notice(e.message);
      }
    };
  });
  document.querySelectorAll("[data-truck-cap]").forEach((x) => {
    x.oninput = () =>
      (state.trucks[+x.dataset.truckCap].capacity = Number(x.value));
    x.onchange = async () => {
      try {
        await saveTrucks();
      } catch (e) {
        notice(e.message);
      }
    };
  });
  document.querySelectorAll("[data-remove-truck]").forEach(
    (x) =>
      (x.onclick = async () => {
        state.trucks.splice(+x.dataset.removeTruck, 1);
        try {
          await saveTrucks();
        } catch (e) {
          notice(e.message);
        }
      }),
  );
  $("#addTruck")?.addEventListener("click", async () => {
    state.trucks.push({
      name: `Truck ${state.trucks.length + 1}`,
      capacity: 80,
    });
    try {
      await saveTrucks();
    } catch (e) {
      notice(e.message);
    }
  });
  $("#continue")?.addEventListener("click", async () => {
    try {
      await saveTrucks();
      setStep(2);
    } catch (e) {
      notice(e.message);
    }
  });
  $("#itemForm")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const item = {
        ...(state.edit ? { id: state.edit.id } : {}),
        name: $("#iname").value,
        weight: $("#iweight").value,
        value: $("#ivalue").value,
      };
      await request(state.edit ? "update-item" : "save-item", { item });
      state.edit = null;
      await refresh();
    } catch (e) {
      notice(e.message);
    }
  });
  document.querySelectorAll("[data-edit]").forEach(
    (x) =>
      (x.onclick = () => {
        state.edit = state.items.find((i) => +i.id === +x.dataset.edit);
        render();
      }),
  );
  document.querySelectorAll("[data-delete]").forEach(
    (x) =>
      (x.onclick = async () => {
        try {
          await request("delete-item", { id: x.dataset.delete });
          await refresh();
        } catch (e) {
          notice(e.message);
        }
      }),
  );
  $("#clearItems")?.addEventListener("click", async () => {
    try {
      await request("clear-items", {});
      await refresh();
    } catch (e) {
      notice(e.message);
    }
  });
  $("#generate")?.addEventListener("click", async () => {
    try {
      await request("random-items", { count: $("#randomN").value });
      await refresh();
    } catch (e) {
      notice(e.message);
    }
  });
  $("#backFleet")?.addEventListener("click", () => setStep(1));
  $("#optimize")?.addEventListener("click", async () => {
    try {
      await saveTrucks();
      state.result = await request("optimize", {});
      setStep(3);
      drawCharts();
    } catch (e) {
      notice(e.message);
    }
  });
  $("#editItems,#editItems2")?.addEventListener("click", () => setStep(2));
  document
    .querySelectorAll("[data-export]")
    .forEach((x) => (x.onclick = () => exportResult(x.dataset.export)));
  $("#reset")?.addEventListener("click", async () => {
    if (confirm("Reset the fleet, items, and results?")) {
      try {
        await request("reset", {});
        state.items = [];
        state.trucks = [];
        state.result = null;
        setStep(1);
      } catch (e) {
        notice(e.message);
      }
    }
  });
  if (state.step === 3) drawCharts();
}
async function saveTrucks() {
  await request("save-trucks", { trucks: state.trucks });
  render();
}
async function refresh() {
  const d = await request("state");
  state.trucks = d.trucks;
  state.items = d.items;
  state.csrf = d.csrf;
  render();
}
function drawCharts() {
  state.chart.forEach((c) => c.destroy());
  state.chart = [];
  if (!state.result || typeof Chart === "undefined") return;
  const { result, items } = state.result;
  const opts = {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { backgroundColor: "#112F3D" },
    },
    scales: {
      x: { ticks: { color: "#889397" }, grid: { display: false } },
      y: { ticks: { color: "#889397" }, grid: { color: "#1C3A47" } },
    },
  };
  const a = $("#truckChart"),
    b = $("#itemChart");
  if (a)
    state.chart.push(
      new Chart(a, {
        type: "bar",
        data: {
          labels: result.perTruck.map((t) => t.truckName),
          datasets: [
            {
              data: result.perTruck.map((t) => t.valueLoaded),
              backgroundColor: result.perTruck.map(
                (_, i) => colors[i % colors.length],
              ),
              borderRadius: 5,
            },
          ],
        },
        options: opts,
      }),
    );
  if (b) {
    const rows = result.sortedOrder.slice(0, 30);
    state.chart.push(
      new Chart(b, {
        type: "bar",
        data: {
          labels: rows.map((x) => x.itemName),
          datasets: [
            {
              data: rows.map((x) => (x.ratio === "Infinity" ? 0 : x.ratio)),
              backgroundColor: rows.map((x) =>
                result.fullyLoadedItems.includes(x.itemId)
                  ? "#00ED64"
                  : result.partiallyLoadedItems.includes(x.itemId)
                    ? "#FFC010"
                    : "#4A5D65",
              ),
              borderRadius: 4,
            },
          ],
        },
        options: {
          ...opts,
          scales: {
            ...opts.scales,
            x: {
              ...opts.scales.x,
              ticks: {
                ...opts.scales.x.ticks,
                maxRotation: 45,
                minRotation: 25,
              },
            },
          },
        },
      }),
    );
  }
}
function exportResult(type) {
  const r = state.result;
  if (!r) return;
  let data, mime;
  if (type === "json") {
    data = JSON.stringify(r, null, 2);
    mime = "application/json";
  } else {
    const rows = [
      ["truck", "item", "weight_taken", "fraction", "value_gained"],
      ...r.result.allocations.map((a) => [
        a.truckName || r.trucks.find((t) => +t.id === a.truckId)?.name,
        a.itemName,
        a.weightTaken,
        a.fraction,
        a.valueGained,
      ]),
    ];
    data = rows
      .map((row) =>
        row
          .map((v) => '"' + String(v ?? "").replaceAll('"', '""') + '"')
          .join(","),
      )
      .join("\r\n");
    mime = "text/csv";
  }
  const blob = new Blob([data], { type: mime }),
    url = URL.createObjectURL(blob),
    a = document.createElement("a");
  a.href = url;
  a.download = `cargo-results.${type}`;
  a.click();
  URL.revokeObjectURL(url);
}
document.querySelectorAll("[data-tab]").forEach(
  (x) =>
    (x.onclick = () => {
      state.tab = x.dataset.tab;
      $("#optimizerView").hidden = state.tab !== "optimizer";
      $("#theoryView").hidden = state.tab !== "theory";
      $("#fleetCapacity").parentElement.hidden = state.tab === "theory";
      document
        .querySelectorAll("[data-tab]")
        .forEach((b) => b.classList.toggle("active", b === x));
      $("#eyebrow").textContent =
        `DELIVERY OPERATIONS / ${state.tab === "theory" ? "THEORY" : `STEP 0${state.step}`}`;
      $("#pageTitle").textContent =
        state.tab === "theory"
          ? "Greedy by design."
          : state.step === 3
            ? "Load report"
            : "Cargo load optimizer";
      $("#pageSubtitle").textContent =
        state.tab === "theory"
          ? "Understand the strategy behind every allocation."
          : "Make every kilogram count across your delivery fleet.";
    }),
);
request("state")
  .then((d) => {
    state.trucks = d.trucks;
    state.items = d.items;
    state.csrf = d.csrf;
    render();
  })
  .catch((e) => {
    notice(e.message);
    $("#stepContent").innerHTML =
      `<section class="panel"><div class="inline-error">${esc(e.message)} Import database/cargo_optimizer.sql and check config/database.php.</div></section>`;
  });
document.querySelectorAll("[data-tab]").forEach((x) =>
  x.addEventListener("click", () => {
    $("#theoryBenchmark").hidden = x.dataset.tab !== "theory";
  }),
);
$("#runBench").addEventListener("click", async () => {
  const button = $("#runBench"),
    out = $("#benchmarkOutput");
  button.disabled = true;
  button.textContent = "Measuring…";
  out.innerHTML = "<p>Running PHP optimizer on four input sizes…</p>";
  try {
    const r = await request("benchmark", {});
    out.className = "bench-values";
    out.innerHTML = r.bench
      .map(
        (x) =>
          `<span><b class="mono">${fmt(x.n, 0)}</b> items <strong class="mono">${Number(x.ms).toFixed(2)} ms</strong></span>`,
      )
      .join("");
  } catch (e) {
    out.className = "inline-error";
    out.textContent = e.message;
  } finally {
    button.disabled = false;
    button.textContent = "▶ Run benchmark";
  }
});
function knapsack01(items, capacity) {
  const limit = Math.floor(capacity);
  if (items.length > 100 || limit > 5000)
    throw Error("Comparison limit exceeded");
  const dp = new Float64Array(limit + 1);
  for (const item of items) {
    const w = Math.ceil(Number(item.weight));
    if (w <= 0 || w > limit) continue;
    for (let c = limit; c >= w; c--)
      dp[c] = Math.max(dp[c], dp[c - w] + Number(item.value));
  }
  return dp[limit];
}
function comparisonHTML() {
  const data = state.result,
    items = data.items,
    r = data.result,
    enabled = items.length <= 100 && r.totals.capacity <= 5000,
    one = enabled ? knapsack01(items, r.totals.capacity) : 0;
  return `<section class="panel compare-panel"><div><span class="section-index">ALGORITHM COMPARISON</span><h3>Fractional vs. 0/1 knapsack</h3><p>See how divisibility changes the optimal value.</p></div><button class="button secondary small" id="compare01">${state.show01 ? "Hide comparison" : "Compare with 0/1"}</button>${!enabled ? '<small class="muted">Available for up to 100 items and 5,000 kg.</small>' : ""}${state.show01 && enabled ? `<div class="comparison-numbers"><div><span>FRACTIONAL OPTIMUM</span><b class="mono">${money(r.totals.valueLoaded)}</b></div><div><span>0/1 OPTIMUM</span><b class="mono">${money(one)}</b></div><div><span>VALUE DIFFERENCE</span><b class="mono green-text">${money(r.totals.valueLoaded - one)}</b></div></div>` : ""}</section>`;
}
new MutationObserver(() => {
  const trace = $("#stepContent .trace-panel");
  if (state.step === 3 && state.result && trace && !$("#compare01"))
    trace.insertAdjacentHTML("beforebegin", comparisonHTML());
}).observe($("#stepContent"), { childList: true, subtree: true });
document.addEventListener("click", (e) => {
  if (e.target.id === "compare01") {
    state.show01 = !state.show01;
    render();
  }
});
new MutationObserver(() => {
  const table = $("#stepContent .table-wrap");
  if (
    state.step === 2 &&
    table &&
    state.items.length > 100 &&
    !$("#itemPagination")
  ) {
    const pages = Math.ceil(state.items.length / 100),
      page = state.page || 0;
    table.insertAdjacentHTML(
      "beforeend",
      `<div class="pagination" id="itemPagination"><span>Showing ${page * 100 + 1}–${Math.min((page + 1) * 100, state.items.length)} of ${state.items.length}</span><div><button class="button secondary small" data-page="${Math.max(0, page - 1)}" ${page === 0 ? "disabled" : ""}>Previous</button><button class="button secondary small" data-page="${Math.min(pages - 1, page + 1)}" ${page === pages - 1 ? "disabled" : ""}>Next</button></div></div>`,
    );
  }
}).observe($("#stepContent"), { childList: true, subtree: true });
document.addEventListener("click", (e) => {
  if (e.target.hasAttribute("data-page")) {
    state.page = Number(e.target.dataset.page);
    render();
  }
});
