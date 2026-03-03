import { useState } from 'react';
import { motion } from 'framer-motion';
import { useInfiniteQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { Loader2, RefreshCw } from 'lucide-react';
import TimelineItem, { type TimelineItemData } from './TimelineItem';
import { SkeletonLoader } from './SkeletonLoader';

type Category = 'all' | 'lead' | 'conversa' | 'interacao' | 'whatsapp' | 'vistoria' | 'assinatura' | 'sistema';

const categoryTabs: { key: Category; label: string }[] = [
  { key: 'all', label: 'Tudo' },
  { key: 'lead', label: 'Leads' },
  { key: 'conversa', label: 'Conversas' },
  { key: 'interacao', label: 'Interacoes' },
  { key: 'whatsapp', label: 'WhatsApp' },
  { key: 'vistoria', label: 'Vistorias' },
  { key: 'assinatura', label: 'Assinaturas' },
  { key: 'sistema', label: 'Sistema' },
];

interface TimelineResponse {
  success: boolean;
  data: TimelineItemData[];
  meta: {
    per_page: number;
    has_more: boolean;
    next_cursor: string | null;
  };
}

export default function TimelineFeed() {
  const [activeCategory, setActiveCategory] = useState<Category>('all');

  const {
    data,
    isLoading,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
    refetch,
    isRefetching,
  } = useInfiniteQuery<TimelineResponse>({
    queryKey: ['dashboard', 'timeline', activeCategory],
    queryFn: async ({ pageParam }) => {
      const params: Record<string, string> = {
        per_page: '20',
        category: activeCategory,
      };
      if (pageParam) {
        params.before = pageParam as string;
      }
      const res = await api.get('/dashboard/timeline', { params });
      return res.data;
    },
    getNextPageParam: (lastPage) => {
      if (lastPage?.meta?.has_more && lastPage?.meta?.next_cursor) {
        return lastPage.meta.next_cursor;
      }
      return undefined;
    },
    initialPageParam: undefined as string | undefined,
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
  });

  const allItems = data?.pages.flatMap((page) => page.data ?? []) ?? [];

  return (
    <div>
      {/* Filter tabs */}
      <div className="flex items-center gap-2 mb-4 overflow-x-auto pb-2 scrollbar-hide">
        {categoryTabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveCategory(tab.key)}
            className={`px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium whitespace-nowrap transition-all ${
              activeCategory === tab.key
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'bg-white/5 text-muted-foreground hover:bg-white/10 border border-white/10'
            }`}
          >
            {tab.label}
          </button>
        ))}

        {/* Refresh button */}
        <button
          onClick={() => refetch()}
          disabled={isRefetching}
          className="ml-auto flex-shrink-0 p-2 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 transition-colors text-muted-foreground"
          title="Atualizar"
        >
          <RefreshCw size={14} className={isRefetching ? 'animate-spin' : ''} />
        </button>
      </div>

      {/* Timeline content */}
      {isLoading ? (
        <div className="space-y-4">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="flex gap-4">
              <div className="w-[40px] h-[40px] rounded-full bg-white/5 animate-pulse flex-shrink-0" />
              <div className="flex-1">
                <SkeletonLoader variant="card" />
              </div>
            </div>
          ))}
        </div>
      ) : allItems.length === 0 ? (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="text-center py-12"
        >
          <p className="text-muted-foreground text-sm">Nenhuma atividade encontrada</p>
          <p className="text-muted-foreground text-xs mt-1">
            {activeCategory !== 'all' ? 'Tente selecionar outra categoria' : 'O sistema ainda nao registrou atividades'}
          </p>
        </motion.div>
      ) : (
        <div className="relative">
          {allItems.map((item, index) => (
            <TimelineItem
              key={item.id}
              item={item}
              index={index}
              isLast={index === allItems.length - 1}
            />
          ))}

          {/* Load more */}
          {hasNextPage && (
            <div className="flex justify-center pt-4">
              <button
                onClick={() => fetchNextPage()}
                disabled={isFetchingNextPage}
                className="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 transition-colors text-sm text-muted-foreground"
              >
                {isFetchingNextPage ? (
                  <>
                    <Loader2 size={14} className="animate-spin" />
                    Carregando...
                  </>
                ) : (
                  'Carregar mais'
                )}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
