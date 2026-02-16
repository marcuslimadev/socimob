import { ReactNode, useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useGesture } from '@use-gesture/react';

interface FlipboardContainerProps {
  children: ReactNode[];
  onPageChange?: (index: number) => void;
  autoPlay?: boolean;
  autoPlayInterval?: number;
  primary?: string;
}

export function FlipboardContainer({
  children,
  onPageChange,
  autoPlay = false,
  autoPlayInterval = 5000,
  primary = '#001775',
}: FlipboardContainerProps) {
  const [currentPage, setCurrentPage] = useState(0);
  const [direction, setDirection] = useState<'next' | 'prev'>('next');
  const containerRef = useRef<HTMLDivElement>(null);
  const autoPlayRef = useRef<NodeJS.Timeout | null>(null);

  const totalPages = Array.isArray(children) ? children.length : 1;

  // Auto-play effect
  useEffect(() => {
    if (!autoPlay) return;

    autoPlayRef.current = setInterval(() => {
      setCurrentPage((prev) => (prev + 1) % totalPages);
      setDirection('next');
    }, autoPlayInterval);

    return () => {
      if (autoPlayRef.current) clearInterval(autoPlayRef.current);
    };
  }, [autoPlay, autoPlayInterval, totalPages]);

  // Handle page change
  useEffect(() => {
    onPageChange?.(currentPage);
  }, [currentPage, onPageChange]);

  // Gesture handling for swipe
  const bind = useGesture({
    onSwipe: ({ direction: [dx] }) => {
      if (Math.abs(dx) > 0.5) {
        if (dx > 0) {
          // Swipe right = previous page
          setCurrentPage((prev) => (prev - 1 + totalPages) % totalPages);
          setDirection('prev');
        } else {
          // Swipe left = next page
          setCurrentPage((prev) => (prev + 1) % totalPages);
          setDirection('next');
        }
      }
    },
    onWheel: ({ direction: [, dy] }) => {
      if (dy > 0) {
        // Scroll down = next page
        setCurrentPage((prev) => (prev + 1) % totalPages);
        setDirection('next');
      } else if (dy < 0) {
        // Scroll up = previous page
        setCurrentPage((prev) => (prev - 1 + totalPages) % totalPages);
        setDirection('prev');
      }
    },
  });

  const pageVariants = {
    enter: (direction: 'next' | 'prev') => ({
      rotateY: direction === 'next' ? 90 : -90,
      opacity: 0,
      z: -100,
    }),
    center: {
      rotateY: 0,
      opacity: 1,
      z: 0,
    },
    exit: (direction: 'next' | 'prev') => ({
      rotateY: direction === 'next' ? -90 : 90,
      opacity: 0,
      z: -100,
    }),
  };

  const pageTransition = {
    type: 'spring',
    stiffness: 300,
    damping: 30,
    mass: 1,
  };

  const childrenArray = Array.isArray(children) ? children : [children];

  return (
    <div
      ref={containerRef}
      className="relative w-full h-screen overflow-hidden bg-white"
      style={{
        perspective: '1200px',
      }}
      {...bind()}
    >
      {/* Perspective container */}
      <motion.div
        className="relative w-full h-full"
        style={{
          transformStyle: 'preserve-3d',
        }}
      >
        <AnimatePresence mode="wait" custom={direction}>
          <motion.div
            key={currentPage}
            custom={direction}
            variants={pageVariants}
            initial="enter"
            animate="center"
            exit="exit"
            transition={pageTransition}
            className="absolute inset-0 w-full h-full"
            style={{
              transformStyle: 'preserve-3d',
              backfaceVisibility: 'hidden',
            }}
          >
            {childrenArray[currentPage]}
          </motion.div>
        </AnimatePresence>
      </motion.div>

      {/* Navigation dots */}
      <div className="absolute bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2">
        {childrenArray.map((_, index) => (
          <motion.button
            key={index}
            onClick={() => {
              setDirection(index > currentPage ? 'next' : 'prev');
              setCurrentPage(index);
            }}
            className="rounded-full transition-all"
            style={{
              width: currentPage === index ? 32 : 12,
              height: 12,
              backgroundColor: currentPage === index ? primary : `${primary}40`,
            }}
            whileHover={{ scale: 1.1 }}
            whileTap={{ scale: 0.95 }}
          />
        ))}
      </div>

      {/* Page counter */}
      <div
        className="absolute top-6 right-6 z-50 px-4 py-2 rounded-full text-sm font-semibold text-white"
        style={{ backgroundColor: primary }}
      >
        {currentPage + 1} / {totalPages}
      </div>

      {/* Hint text for gestures */}
      {totalPages > 1 && (
        <div className="absolute top-6 left-6 z-50 text-xs text-gray-500">
          Deslize ou role para navegar
        </div>
      )}
    </div>
  );
}
