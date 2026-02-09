/**
 * Performance monitoring utilities
 * Tracks and logs performance metrics
 */

interface PerformanceMetrics {
  name: string;
  duration: number;
  timestamp: number;
}

const metrics: PerformanceMetrics[] = [];

/**
 * Start measuring performance
 */
export function startMeasure(name: string) {
  performance.mark(`${name}-start`);
}

/**
 * End measuring performance and log result
 */
export function endMeasure(name: string) {
  try {
    performance.mark(`${name}-end`);
    performance.measure(name, `${name}-start`, `${name}-end`);
    
    const measure = performance.getEntriesByName(name)[0];
    if (measure) {
      const metric: PerformanceMetrics = {
        name,
        duration: measure.duration,
        timestamp: Date.now(),
      };
      
      metrics.push(metric);
      
      // Log in development
      if (process.env.NODE_ENV === 'development') {
        console.log(`⏱️  ${name}: ${measure.duration.toFixed(2)}ms`);
      }
      
      return metric;
    }
  } catch (error) {
    console.error(`Error measuring performance for ${name}:`, error);
  }
}

/**
 * Get all collected metrics
 */
export function getMetrics() {
  return metrics;
}

/**
 * Clear all metrics
 */
export function clearMetrics() {
  metrics.length = 0;
  performance.clearMarks();
  performance.clearMeasures();
}

/**
 * Get Core Web Vitals
 */
export function getCoreWebVitals() {
  const vitals = {
    FCP: 0, // First Contentful Paint
    LCP: 0, // Largest Contentful Paint
    FID: 0, // First Input Delay
    CLS: 0, // Cumulative Layout Shift
  };

  // Get FCP
  const fcp = performance.getEntriesByName('first-contentful-paint')[0];
  if (fcp) vitals.FCP = fcp.startTime;

  // Get LCP
  const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
  if (lcpEntries.length > 0) {
    vitals.LCP = lcpEntries[lcpEntries.length - 1].startTime;
  }

  return vitals;
}

/**
 * Report metrics to analytics
 */
export function reportMetrics() {
  const vitals = getCoreWebVitals();
  
  if (process.env.NODE_ENV === 'development') {
    console.table({
      'Core Web Vitals': vitals,
      'Total Metrics': metrics.length,
    });
  }

  // Send to analytics service if available
  if (window.gtag) {
    Object.entries(vitals).forEach(([name, value]) => {
      window.gtag('event', name, {
        value: Math.round(value),
        event_category: 'performance',
      });
    });
  }
}

/**
 * Measure function execution time
 */
export async function measureAsync<T>(
  name: string,
  fn: () => Promise<T>
): Promise<T> {
  startMeasure(name);
  try {
    const result = await fn();
    endMeasure(name);
    return result;
  } catch (error) {
    endMeasure(name);
    throw error;
  }
}

/**
 * Measure function execution time (sync)
 */
export function measureSync<T>(
  name: string,
  fn: () => T
): T {
  startMeasure(name);
  try {
    const result = fn();
    endMeasure(name);
    return result;
  } catch (error) {
    endMeasure(name);
    throw error;
  }
}
