import { motion } from 'framer-motion';
import { RefreshCw } from 'lucide-react';
import { ReactNode, useState, useRef, useEffect } from 'react';

interface PullToRefreshProps {
  onRefresh: () => Promise<void>;
  children: ReactNode;
}

export default function PullToRefresh({ onRefresh, children }: PullToRefreshProps) {
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [pullDistance, setPullDistance] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const startYRef = useRef(0);

  const handleTouchStart = (e: React.TouchEvent) => {
    if (containerRef.current?.scrollTop === 0) {
      startYRef.current = e.touches[0].clientY;
    }
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    if (containerRef.current?.scrollTop === 0 && !isRefreshing) {
      const currentY = e.touches[0].clientY;
      const distance = Math.max(0, currentY - startYRef.current);
      setPullDistance(Math.min(distance, 100));
    }
  };

  const handleTouchEnd = async () => {
    if (pullDistance > 60 && !isRefreshing) {
      setIsRefreshing(true);
      try {
        await onRefresh();
      } finally {
        setIsRefreshing(false);
      }
    }
    setPullDistance(0);
  };

  const refreshProgress = Math.min(pullDistance / 60, 1);

  return (
    <div
      ref={containerRef}
      onTouchStart={handleTouchStart}
      onTouchMove={handleTouchMove}
      onTouchEnd={handleTouchEnd}
      className="relative overflow-y-auto"
    >
      {/* Pull to Refresh Indicator */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{
          opacity: pullDistance > 0 ? 1 : 0,
          y: -20 + pullDistance * 0.5,
        }}
        className="flex justify-center items-center py-4"
      >
        <motion.div
          animate={{
            rotate: isRefreshing ? 360 : refreshProgress * 180,
          }}
          transition={{
            rotate: isRefreshing ? { repeat: Infinity, duration: 1 } : { duration: 0 },
          }}
          className="flex items-center justify-center"
        >
          <RefreshCw
            size={24}
            className={`${
              pullDistance > 60
                ? 'text-blue-400'
                : 'text-muted-foreground'
            } transition-colors`}
          />
        </motion.div>
      </motion.div>

      {/* Content */}
      <motion.div
        animate={{
          y: pullDistance * 0.5,
        }}
      >
        {children}
      </motion.div>

      {/* Refreshing Overlay */}
      {isRefreshing && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 pointer-events-none"
        >
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ repeat: Infinity, duration: 1 }}
            className="bg-white/10 p-4 rounded-full border border-white/20"
          >
            <RefreshCw size={24} className="text-blue-400" />
          </motion.div>
        </motion.div>
      )}
    </div>
  );
}
