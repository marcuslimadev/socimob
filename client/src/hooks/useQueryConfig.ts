import { QueryClient, DefaultOptions } from '@tanstack/react-query';

const queryConfig: DefaultOptions = {
  queries: {
    // Retry failed requests up to 3 times
    retry: 3,
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000),
    
    // Cache data for 5 minutes
    staleTime: 5 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
    
    // Refetch on window focus
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
    refetchOnMount: true,
  },
  mutations: {
    // Retry mutations up to 1 time
    retry: 1,
    retryDelay: 1000,
  },
};

export const queryClient = new QueryClient({
  defaultOptions: queryConfig,
});

/**
 * Hook para obter configuração otimizada de queries
 * Uso: const { data } = useQuery({ ...useQueryConfig(), queryKey: [...], queryFn: ... })
 */
export const useQueryConfig = () => queryConfig;
