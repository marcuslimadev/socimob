import { describe, it, expect, beforeEach } from 'vitest';
import { useAppStore } from '@/hooks/useAppStore';

describe('useAppStore', () => {
  beforeEach(() => {
    // Reset store before each test
    useAppStore.getState().reset();
  });

  it('should initialize with default values', () => {
    const state = useAppStore.getState();
    expect(state.sidebarOpen).toBe(true);
    expect(state.userId).toBeNull();
    expect(state.tenantId).toBeNull();
    expect(state.unreadNotifications).toBe(0);
    expect(state.theme).toBe('light');
  });

  it('should update sidebar state', () => {
    const { setSidebarOpen } = useAppStore.getState();
    setSidebarOpen(false);
    
    expect(useAppStore.getState().sidebarOpen).toBe(false);
    
    setSidebarOpen(true);
    expect(useAppStore.getState().sidebarOpen).toBe(true);
  });

  it('should update user id', () => {
    const { setUserId } = useAppStore.getState();
    setUserId('user-123');
    
    expect(useAppStore.getState().userId).toBe('user-123');
  });

  it('should update tenant id', () => {
    const { setTenantId } = useAppStore.getState();
    setTenantId('tenant-456');
    
    expect(useAppStore.getState().tenantId).toBe('tenant-456');
  });

  it('should update notification count', () => {
    const { setUnreadNotifications } = useAppStore.getState();
    setUnreadNotifications(5);
    
    expect(useAppStore.getState().unreadNotifications).toBe(5);
  });

  it('should update theme', () => {
    const { setTheme } = useAppStore.getState();
    setTheme('dark');
    
    expect(useAppStore.getState().theme).toBe('dark');
  });

  it('should reset all state', () => {
    const state = useAppStore.getState();
    state.setSidebarOpen(false);
    state.setUserId('user-123');
    state.setTenantId('tenant-456');
    state.setUnreadNotifications(5);
    state.setTheme('dark');
    
    state.reset();
    
    const resetState = useAppStore.getState();
    expect(resetState.sidebarOpen).toBe(true);
    expect(resetState.userId).toBeNull();
    expect(resetState.tenantId).toBeNull();
    expect(resetState.unreadNotifications).toBe(0);
    expect(resetState.theme).toBe('light');
  });
});
