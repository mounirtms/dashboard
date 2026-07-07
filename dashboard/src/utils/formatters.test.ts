import { describe, it, expect } from 'vitest';
import { formatBytes, formatNumber } from './formatters';

describe('formatters', () => {
  it('should format bytes correctly', () => {
    expect(formatBytes(100)).toBe('100 B');
    expect(formatBytes(1024)).toBe('1.0 KB');
    expect(formatBytes(1048576)).toBe('1.0 MB');
    expect(formatBytes(1073741824)).toBe('1.0 GB');
  });

  it('should format numbers with locales', () => {
    expect(formatNumber(1000)).toBe('1,000');
    expect(formatNumber(0)).toBe('0');
  });
});
