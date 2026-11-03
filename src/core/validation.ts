// Pure input validation shared by the UI and algorithm boundary.
import type { CargoItem, Truck } from './types';
export type ValidationIssue = { field: string; message: string };
export function validateFleet(trucks: Truck[]): ValidationIssue[] {
  const issues: ValidationIssue[] = [];
  trucks.forEach((t, i) => { if (!t.name.trim()) issues.push({ field: `truck.${i}.name`, message: 'Enter a truck name.' }); if (!Number.isFinite(t.capacity) || t.capacity <= 0) issues.push({ field: `truck.${i}.capacity`, message: 'Capacity must be a finite number greater than 0.' }); });
  return issues;
}
export function validateItems(items: CargoItem[]): ValidationIssue[] {
  const issues: ValidationIssue[] = [], names = new Set<string>();
  items.forEach((item, i) => {
    const name = item.name.trim().toLocaleLowerCase();
    if (!item.name.trim()) issues.push({ field: `item.${i}.name`, message: 'Enter an item name.' });
    else if (names.has(name)) issues.push({ field: `item.${i}.name`, message: 'Item names must be unique (case-insensitive).' });
    names.add(name);
    if (!Number.isFinite(item.weight) || item.weight < 0) issues.push({ field: `item.${i}.weight`, message: 'Weight must be a finite number at least 0.' });
    if (!Number.isFinite(item.value) || item.value < 0) issues.push({ field: `item.${i}.value`, message: 'Value must be a finite number at least 0.' });
  });
  return issues;
}
