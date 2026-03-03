import { describe, it, expect, vi, beforeEach } from 'vitest';
import { measureAsync, measureSync, clearMetrics, getMetrics } from '@/lib/performance';

describe('Performance Utilities', () => {
  beforeEach(() => {
    clearMetrics();
  });

  describe('measureAsync', () => {
    it('should measure async function execution time', async () => {
      const result = await measureAsync('test-async', async () => {
        await new Promise(resolve => setTimeout(resolve, 10));
        return 'test-result';
      });

      expect(result).toBe('test-result');
      const metrics = getMetrics();
      expect(metrics.length).toBe(1);
      expect(metrics[0].name).toBe('test-async');
      expect(metrics[0].duration).toBeGreaterThan(0);
    });

    it('should handle errors in async functions', async () => {
      await expect(async () => {
        await measureAsync('test-error', async () => {
          throw new Error('Test error');
        });
      }).rejects.toThrow('Test error');

      const metrics = getMetrics();
      expect(metrics.length).toBe(1);
    });
  });

  describe('measureSync', () => {
    it('should measure sync function execution time', () => {
      const result = measureSync('test-sync', () => {
        let sum = 0;
        for (let i = 0; i < 1000; i++) {
          sum += i;
        }
        return sum;
      });

      expect(result).toBe(499500);
      const metrics = getMetrics();
      expect(metrics.length).toBe(1);
      expect(metrics[0].name).toBe('test-sync');
      expect(metrics[0].duration).toBeGreaterThanOrEqual(0);
    });

    it('should handle errors in sync functions', () => {
      expect(() => {
        measureSync('test-error', () => {
          throw new Error('Test error');
        });
      }).toThrow('Test error');

      const metrics = getMetrics();
      expect(metrics.length).toBe(1);
    });
  });

  describe('metrics management', () => {
    it('should collect multiple metrics', () => {
      measureSync('metric-1', () => 1);
      measureSync('metric-2', () => 2);
      measureSync('metric-3', () => 3);

      const metrics = getMetrics();
      expect(metrics.length).toBe(3);
      expect(metrics[0].name).toBe('metric-1');
      expect(metrics[1].name).toBe('metric-2');
      expect(metrics[2].name).toBe('metric-3');
    });

    it('should clear all metrics', () => {
      measureSync('test', () => 1);
      expect(getMetrics().length).toBe(1);

      clearMetrics();
      expect(getMetrics().length).toBe(0);
    });

    it('should include timestamp in metrics', () => {
      const beforeTime = Date.now();
      measureSync('test', () => 1);
      const afterTime = Date.now();

      const metrics = getMetrics();
      expect(metrics[0].timestamp).toBeGreaterThanOrEqual(beforeTime);
      expect(metrics[0].timestamp).toBeLessThanOrEqual(afterTime);
    });
  });
});
