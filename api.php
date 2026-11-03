<?php

/**
 * JSON API for fleet management, cargo management, optimization, and exports.
 *
 * GET requests return the current database state. POST requests are protected
 * by the session CSRF token and dispatch to a small action selected through
 * the query string. Input is validated before writes, database transactions
 * keep related records consistent, and the optimizer remains isolated in
 * includes/knapsack.php. Every successful or failed branch ends with a JSON
 * response so the browser never has to interpret an HTML error page.
 */
declare(strict_types=1);
session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/validation.php';
require __DIR__ . '/includes/knapsack.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Serialize an API response, set its HTTP status, and stop further dispatch.
function reply(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
// Decode the request body and reject empty, malformed, or non-object JSON.
function body(): array
{
    $v = json_decode(file_get_contents('php://input'), true);
    if (!is_array($v)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    } return $v;
}
// Require the browser to send the token generated for the active PHP session.
function csrfCheck(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        throw new RuntimeException('Your session token expired. Reload the page and try again.');
    }
}
try {
    // Resolve the database connection and route the request to its action.
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'state';
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    if ($method === 'GET' && $action === 'state') {
        // Load editable fleet/cargo state and the most recent optimization id.
        $trucks = $pdo->query('SELECT id, truck_name AS name, capacity FROM trucks ORDER BY id')->fetchAll();
        $items = $pdo->query('SELECT id, item_name AS name, weight, value FROM items ORDER BY id')->fetchAll();
        foreach ($trucks as &$t) {
            $t['id'] = (int)$t['id'];
            $t['capacity'] = (float)$t['capacity'];
        } unset($t);
        foreach ($items as &$i) {
            $i['id'] = (int)$i['id'];
            $i['weight'] = (float)$i['weight'];
            $i['value'] = (float)$i['value'];
        } unset($i);
        $latest = $pdo->query('SELECT id FROM optimizations ORDER BY id DESC LIMIT 1')->fetchColumn();
        reply(['trucks' => $trucks,'items' => $items,'csrf' => $_SESSION['csrf'],'latestOptimization' => $latest ? (int)$latest : null]);
    }
    if ($method !== 'POST') {
        reply(['error' => 'Method not allowed.'], 405);
    }
    csrfCheck();
    $data = body();
    if ($action === 'save-trucks') {
        // Replace the fleet atomically after validating every submitted truck.
        $trucks = $data['trucks'] ?? [];
        if (!is_array($trucks)) {
            throw new InvalidArgumentException('Invalid truck list.');
        }
        $errors = validateFleet($trucks);
        if (count($trucks) > 20) {
            $errors[] = 'A fleet can contain at most 20 trucks.';
        } if ($errors) {
            reply(['error' => implode(' ', $errors)], 422);
        }
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM trucks');
        $stmt = $pdo->prepare('INSERT INTO trucks (truck_name,capacity) VALUES (?,?)');
        foreach ($trucks as $t) {
            $stmt->execute([trim($t['name']),(float)$t['capacity']]);
        } $pdo->commit();
        reply(['ok' => true]);
    }
    if ($action === 'save-item' || $action === 'update-item') {
        // Validate an item against the existing list before inserting or updating it.
        $item = $data['item'] ?? [];
        $items = $pdo->query('SELECT id,item_name AS name,weight,value FROM items')->fetchAll();
        if ($action === 'update-item') {
            $items = array_values(array_filter($items, fn ($x) => (int)$x['id'] !== (int)($item['id'] ?? 0)));
        }
        $candidate = ['name' => (string)($item['name'] ?? ''),'weight' => $item['weight'] ?? null,'value' => $item['value'] ?? null];
        $errors = validateItems(array_merge($items, [$candidate]));
        if ($errors) {
            reply(['error' => implode(' ', $errors)], 422);
        }
        if ($action === 'save-item') {
            $s = $pdo->prepare('INSERT INTO items(item_name,weight,value) VALUES(?,?,?)');
            $s->execute([trim($candidate['name']),(float)$candidate['weight'],(float)$candidate['value']]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $id = (int)($item['id'] ?? 0);
            $s = $pdo->prepare('UPDATE items SET item_name=?,weight=?,value=? WHERE id=?');
            $s->execute([trim($candidate['name']),(float)$candidate['weight'],(float)$candidate['value'],$id]);
            if (!$s->rowCount()) {
                $exists = $pdo->prepare('SELECT 1 FROM items WHERE id=?');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    throw new InvalidArgumentException('Item no longer exists.');
                }
            }
        }
        reply(['ok' => true,'id' => $id]);
    }
    // Remove one item or clear the complete cargo list.
    if ($action === 'delete-item') {
        $s = $pdo->prepare('DELETE FROM items WHERE id=?');
        $s->execute([(int)($data['id'] ?? 0)]);
        reply(['ok' => true]);
    }
    if ($action === 'clear-items') {
        $pdo->exec('DELETE FROM items');
        reply(['ok' => true]);
    }
    if ($action === 'optimize') {
        // Calculate allocations, persist a run snapshot, and return the full report.
        $trucks = $pdo->query('SELECT id,truck_name AS name,capacity FROM trucks ORDER BY id')->fetchAll();
        $items = $pdo->query('SELECT id,item_name AS name,weight,value FROM items ORDER BY id')->fetchAll();
        foreach ($trucks as &$t) {
            $t['id'] = (int)$t['id'];
            $t['capacity'] = (float)$t['capacity'];
        }unset($t);
        foreach ($items as &$i) {
            $i['id'] = (int)$i['id'];
            $i['weight'] = (float)$i['weight'];
            $i['value'] = (float)$i['value'];
        }unset($i);
        $result = optimizeLoad($items, $trucks);
        $pdo->beginTransaction();
        $s = $pdo->prepare('INSERT INTO optimizations(total_value,total_weight,total_capacity,utilization_percentage) VALUES(?,?,?,?)');
        $s->execute([$result['totals']['valueLoaded'],$result['totals']['weightLoaded'],$result['totals']['capacity'],$result['totals']['utilizationPercentage']]);
        $oid = (int)$pdo->lastInsertId();
        $a = $pdo->prepare('INSERT INTO allocations(truck_id,item_id,truck_name_snapshot,item_name_snapshot,weight_allocated,fraction_allocated,value_gained,optimization_id) VALUES(?,?,?,?,?,?,?,?)');
        foreach ($result['allocations'] as $x) {
            $a->execute([$x['truckId'],$x['itemId'],$x['truckName'],$x['itemName'],$x['weightTaken'],$x['fraction'],$x['valueGained'],$oid]);
        }$pdo->commit();
        $_SESSION['last_result'] = ['id' => $oid,'trucks' => $trucks,'items' => $items,'result' => $result];
        reply($_SESSION['last_result']);
    }
    // Delete historical runs and all current configuration in dependency order.
    if ($action === 'reset') {
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM allocations');
        $pdo->exec('DELETE FROM optimizations');
        $pdo->exec('DELETE FROM items');
        $pdo->exec('DELETE FROM trucks');
        $pdo->commit();
        unset($_SESSION['last_result']);
        reply(['ok' => true]);
    }
    // Return the session's last report; the browser formats it as CSV or JSON.
    if ($action === 'export') {
        $which = $data['type'] ?? '';
        $result = $_SESSION['last_result'] ?? null;
        if (!$result) {
            throw new InvalidArgumentException('Run an optimization before exporting.');
        }reply(['data' => $result,'type' => $which]);
    }
    // Generate bounded sample data for demonstrations and performance checks.
    if ($action === 'random-items') {
        $n = max(1, min(10000, (int)($data['count'] ?? 1)));
        $pdo->beginTransaction();
        $s = $pdo->prepare('INSERT INTO items(item_name,weight,value) VALUES(?,?,?)');
        $base = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        for ($i = 0;$i < $n;$i++) {
            $s->execute(['Generated Cargo '.bin2hex(random_bytes(8)).' '.($base + $i + 1),random_int(100, 5000) / 100,random_int(100, 999)]);
        }$pdo->commit();
        reply(['ok' => true,'count' => $n]);
    }
    if ($action === 'benchmark') {
        // Build deterministic synthetic inputs and report measured algorithm time.
        $rows = [];
        foreach ([100,1000,10000,100000] as $n) {
            $items = [];
            for ($i = 0;$i < $n;$i++) {
                $items[] = ['id' => $i + 1,'name' => 'Bench '.$i,'weight' => 1 + ($i % 100),'value' => 1 + (($i * 37) % 1000)];
            }
            $run = optimizeLoad($items, [['id' => 1,'name' => 'Benchmark','capacity' => $n * 0.5]]);
            $rows[] = ['n' => $n,'ms' => $run['stats']['elapsedMs']];
        }
        reply(['bench' => $rows]);
    }
    reply(['error' => 'Unknown action.'], 404);
    // Convert expected validation failures and unexpected server failures to JSON.
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }reply(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }error_log((string)$e);
    reply(['error' => str_contains($e->getMessage(), 'SQLSTATE') ? 'Database operation failed. Check that the schema is imported and the credentials are correct.' : $e->getMessage()], 500);
}
