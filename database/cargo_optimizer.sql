-- Database schema and starter data for the Cargo Load Optimizer.
-- The tables keep editable fleet/cargo state separate from immutable run
-- summaries and allocation snapshots, while foreign keys preserve history.
CREATE DATABASE IF NOT EXISTS cargo_optimizer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cargo_optimizer;

-- Store each configurable delivery vehicle and its positive capacity.
CREATE TABLE IF NOT EXISTS trucks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  truck_name VARCHAR(120) NOT NULL,
  capacity DECIMAL(30,12) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_truck_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

-- Store divisible cargo and enforce non-negative numeric inputs.
CREATE TABLE IF NOT EXISTS items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(190) NOT NULL,
  weight DECIMAL(30,12) NOT NULL,
  value DECIMAL(30,8) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_item_weight CHECK (weight >= 0),
  CONSTRAINT chk_item_value CHECK (value >= 0),
  UNIQUE KEY uq_item_name (item_name)
) ENGINE=InnoDB;

-- Store totals for every completed optimization run.
CREATE TABLE IF NOT EXISTS optimizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  total_value DECIMAL(30,8) NOT NULL DEFAULT 0,
  total_weight DECIMAL(30,12) NOT NULL DEFAULT 0,
  total_capacity DECIMAL(30,12) NOT NULL DEFAULT 0,
  utilization_percentage DECIMAL(9,4) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Store each item portion assigned to a truck, including display snapshots.
CREATE TABLE IF NOT EXISTS allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  truck_id BIGINT UNSIGNED NULL,
  item_id BIGINT UNSIGNED NULL,
  truck_name_snapshot VARCHAR(120) NOT NULL,
  item_name_snapshot VARCHAR(190) NOT NULL,
  weight_allocated DECIMAL(30,12) NOT NULL DEFAULT 0,
  fraction_allocated DECIMAL(18,12) NOT NULL DEFAULT 0,
  value_gained DECIMAL(30,8) NOT NULL DEFAULT 0,
  optimization_id BIGINT UNSIGNED NOT NULL,
  INDEX idx_alloc_optimization (optimization_id),
  CONSTRAINT fk_alloc_truck FOREIGN KEY (truck_id) REFERENCES trucks(id) ON DELETE SET NULL,
  CONSTRAINT fk_alloc_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
  CONSTRAINT fk_alloc_optimization FOREIGN KEY (optimization_id) REFERENCES optimizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Provide a usable two-truck starting configuration on a fresh database.
INSERT INTO trucks (truck_name, capacity)
SELECT 'Truck 1', 100 WHERE NOT EXISTS (SELECT 1 FROM trucks);
INSERT INTO trucks (truck_name, capacity)
SELECT 'Truck 2', 80 WHERE (SELECT COUNT(*) FROM trucks) = 1;
