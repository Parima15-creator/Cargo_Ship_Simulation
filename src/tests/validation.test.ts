// Input validation tests for fleet and cargo forms.
import { describe, expect, it } from 'vitest';
import { validateFleet, validateItems } from '../core/validation';
describe('input validation', () => {
  it('rejects nonpositive capacities', () => { expect(validateFleet([{ id: 't', name: 'T', capacity: 0 }])).toHaveLength(1); });
  it('rejects duplicate names case insensitively', () => { expect(validateItems([{ id: '1', name: 'Box', weight: 1, value: 1 }, { id: '2', name: 'box', weight: 1, value: 2 }]).some(x => x.message.includes('unique'))).toBe(true); });
  it('rejects invalid item numbers', () => { expect(validateItems([{ id: 'x', name: 'x', weight: -1, value: Infinity }])).toHaveLength(2); });
});
