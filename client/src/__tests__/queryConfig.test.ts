import { describe, it, expect } from 'vitest';
import { queryClient, useQueryConfig } from '@/hooks/useQueryConfig';

describe('useQueryConfig', () => {
  it('should create a query client', () => {
    expect(queryClient).toBeDefined();
    expect(queryClient.getDefaultOptions()).toBeDefined();
  });

  it('should have correct query defaults', () => {
    const options = queryClient.getDefaultOptions();
    
    expect(options.queries?.retry).toBe(3);
    expect(options.queries?.staleTime).toBe(5 * 60 * 1000); // 5 minutes
    expect(options.queries?.gcTime).toBe(10 * 60 * 1000); // 10 minutes
    expect(options.queries?.refetchOnWindowFocus).toBe(true);
    expect(options.queries?.refetchOnReconnect).toBe(true);
    expect(options.queries?.refetchOnMount).toBe(true);
  });

  it('should have correct mutation defaults', () => {
    const options = queryClient.getDefaultOptions();
    
    expect(options.mutations?.retry).toBe(1);
    expect(options.mutations?.retryDelay).toBe(1000);
  });

  it('should return config from useQueryConfig hook', () => {
    const config = useQueryConfig();
    
    expect(config).toBeDefined();
    expect(config.queries).toBeDefined();
    expect(config.mutations).toBeDefined();
  });
});
