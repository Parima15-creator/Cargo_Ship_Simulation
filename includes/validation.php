<?php

/**
 * Shared server-side validation for fleet and cargo records.
 *
 * These functions return all discovered messages instead of throwing on the
 * first field, allowing the API to show the user a complete correction list.
 * They also enforce the same numeric and naming rules used by the optimizer.
 */
declare(strict_types=1);

// Check required truck names and strictly positive finite capacities.
function validateFleet(array $trucks): array
{
    $errors = [];
    if (!$trucks) {
        $errors[] = 'Add at least one truck before optimizing.';
    }
    foreach ($trucks as $i => $truck) {
        if (trim((string)($truck['name'] ?? '')) === '') {
            $errors[] = 'Truck ' . ($i + 1) . ' needs a name.';
        }
        $capacity = filter_var($truck['capacity'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($capacity === false || !is_finite((float)$capacity) || $capacity <= 0) {
            $errors[] = 'Truck ' . ($i + 1) . ' capacity must be a finite number greater than zero.';
        }
    }
    return $errors;
}

// Check cargo names, case-insensitive uniqueness, weights, and values.
function validateItems(array $items): array
{
    $errors = [];
    $names = [];
    if (!$items) {
        $errors[] = 'Add at least one item before optimizing.';
    }
    foreach ($items as $i => $item) {
        $name = trim((string)($item['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Item ' . ($i + 1) . ' needs a name.';
        }
        $key = mb_strtolower($name);
        if ($name !== '' && isset($names[$key])) {
            $errors[] = 'Item names must be unique (case-insensitive).';
        }
        if ($name !== '') {
            $names[$key] = true;
        }
        foreach (['weight', 'value'] as $field) {
            $number = filter_var($item[$field] ?? null, FILTER_VALIDATE_FLOAT);
            if ($number === false || !is_finite((float)$number) || $number < 0 || ($field === 'weight' && $number == 0 && ($item['value'] ?? 0) < 0)) {
                $errors[] = ucfirst($field) . ' for item ' . ($i + 1) . ' must be a finite number at least zero.';
            }
        }
    }
    return $errors;
}
