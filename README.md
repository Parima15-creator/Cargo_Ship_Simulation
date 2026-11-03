# Atlas Cargo Load Optimizer — PHP + MySQL

This is a separate PHP 8.2+ implementation of the original Cargo Load Optimizer. The original React/TypeScript project remains in the parent directory and is not needed at runtime by this application. The PHP page reuses its MongoDB Atlas inspired dark interface, typography, spacing, responsive layout and palette.

## XAMPP setup

1. Install XAMPP for Windows from Apache Friends.
2. Copy the entire `php-version` folder to `C:\xampp\htdocs\cargo-optimizer`.
3. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
4. Open `http://localhost/phpmyadmin`, choose **Import**, select `database/cargo_optimizer.sql`, and import it. The script creates the `cargo_optimizer` database and its tables.
5. The default XAMPP connection is `127.0.0.1`, database `cargo_optimizer`, user `root`, and a blank password. To change credentials, set `CARGO_DB_HOST`, `CARGO_DB_NAME`, `CARGO_DB_USER`, and `CARGO_DB_PASS` in the Apache/PHP environment. Alternatively edit the server-only defaults in `config/database.php`. Do not put credentials in JavaScript or other public assets.
6. Visit `http://localhost/cargo-optimizer/`.

The server needs PHP PDO MySQL enabled (`pdo_mysql` in `php.ini`). Chart.js is loaded from jsDelivr for the result charts; core allocation, forms, tables, and exports use no frontend framework.

## Features

- Edit, add, and remove up to 20 trucks, with individually configured capacities.
- Persistent item add/edit/delete, sample cargo, bulk random cargo (up to 10,000), duplicate-name validation, and clear all.
- Fractional allocation over total fleet capacity in truck order, with partial items continuing into later trucks.
- Result KPIs, each truck's remaining capacity and allocations, ratio-ranked chart, value chart, remaining cargo, and generated execution trace.
- CSV and JSON downloads, light/dark toggle, reset, and the algorithm theory page.
- Optimization history and truck/item allocation rows stored in MySQL.
- Prepared statements for user data, server-side validation, output escaping, and CSRF token checks for state-changing API requests.

## Algorithm

`includes/knapsack.php` is independent of rendering and the database. It sorts all items by descending value/weight ratio (then descending value, then original order) and uses one pass to distribute them across trucks in configured order. If an item is split by one truck's remaining space, its remaining fraction continues into the following truck. Because items are divisible, this is equivalent to one knapsack with combined fleet capacity and is globally optimal. A zero-weight positive-value item is included at no capacity cost; zero-weight zero-value items are ignored.

For `n` items and `m` trucks, sorting takes `O(n log n)` and the allocation pass takes `O(n + m)`, for `O(n log n + m)` total time. The returned ordering, trace, allocations, and truck summaries use `O(n + m)` auxiliary space.

## Algorithm verification

With PHP on PATH, run:

```sh
php tests/knapsack_test.php
```

The script checks the textbook optimum of 240, combined fleet capacity, an item split over two trucks, all-fit and no-item behavior, deterministic equal-ratio ties, decimal weights, very large values, leftovers, different truck capacities, invalid negative weight, zero-weight valuable cargo, and a 10,000-item data set. It does not need a database connection.

## Database notes

`database/cargo_optimizer.sql` uses InnoDB foreign keys. `optimizations` stores run totals and `allocations` stores each truck/item portion. Truck and item CRUD state is stored in the corresponding tables. All values shown in a result are calculated from saved current inputs by the PHP algorithm; results are not hardcoded.
