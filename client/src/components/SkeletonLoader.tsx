import { cn } from '@/lib/utils';

interface SkeletonProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'text' | 'card' | 'avatar' | 'button';
  count?: number;
}

/**
 * Skeleton loader component for loading states
 * Provides visual feedback while data is being fetched
 */
export function SkeletonLoader({
  variant = 'text',
  count = 1,
  className,
  ...props
}: SkeletonProps) {
  const baseClass = 'animate-pulse bg-gradient-to-r from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 rounded';

  const variants = {
    text: 'h-4 w-full mb-2',
    card: 'h-40 w-full rounded-lg',
    avatar: 'h-10 w-10 rounded-full',
    button: 'h-10 w-24 rounded-md',
  };

  const skeletons = Array.from({ length: count }).map((_, i) => (
    <div
      key={i}
      className={cn(baseClass, variants[variant], className)}
      {...props}
    />
  ));

  return <>{skeletons}</>;
}

/**
 * Card skeleton - useful for loading lists
 */
export function CardSkeleton() {
  return (
    <div className="space-y-4 p-4 border rounded-lg">
      <SkeletonLoader variant="text" />
      <SkeletonLoader variant="text" count={2} />
      <div className="flex gap-2">
        <SkeletonLoader variant="button" />
        <SkeletonLoader variant="button" />
      </div>
    </div>
  );
}

/**
 * Table skeleton - useful for loading tables
 */
export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-2">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex gap-4">
          <SkeletonLoader variant="text" className="w-1/4" />
          <SkeletonLoader variant="text" className="w-1/4" />
          <SkeletonLoader variant="text" className="w-1/4" />
          <SkeletonLoader variant="text" className="w-1/4" />
        </div>
      ))}
    </div>
  );
}

export default SkeletonLoader;
