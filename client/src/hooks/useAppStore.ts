import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface AppState {
  // UI State
  sidebarOpen: boolean;
  setSidebarOpen: (open: boolean) => void;
  
  // User State
  userId: string | null;
  setUserId: (id: string | null) => void;
  
  // Tenant State
  tenantId: string | null;
  setTenantId: (id: string | null) => void;
  
  // Notification State
  unreadNotifications: number;
  setUnreadNotifications: (count: number) => void;
  
  // Theme State
  theme: 'light' | 'dark';
  setTheme: (theme: 'light' | 'dark') => void;
  
  // Reset all state
  reset: () => void;
}

export const useAppStore = create<AppState>()(
  persist(
    (set) => ({
      // UI State
      sidebarOpen: true,
      setSidebarOpen: (open) => set({ sidebarOpen: open }),
      
      // User State
      userId: null,
      setUserId: (id) => set({ userId: id }),
      
      // Tenant State
      tenantId: null,
      setTenantId: (id) => set({ tenantId: id }),
      
      // Notification State
      unreadNotifications: 0,
      setUnreadNotifications: (count) => set({ unreadNotifications: count }),
      
      // Theme State
      theme: 'dark',
      setTheme: (theme) => set({ theme }),
      
      // Reset
      reset: () => set({
        sidebarOpen: true,
        userId: null,
        tenantId: null,
        unreadNotifications: 0,
        theme: 'dark',
      }),
    }),
    {
      name: 'app-store',
      version: 1,
    }
  )
);
