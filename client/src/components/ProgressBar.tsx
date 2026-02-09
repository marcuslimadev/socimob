import { useEffect } from 'react';
import { useLocation } from 'wouter';
import NProgress from 'nprogress';
import 'nprogress/nprogress.css';

// Customize NProgress
NProgress.configure({
  minimum: 0.3,
  easing: 'ease',
  speed: 800,
  showSpinner: false,
  trickleSpeed: 200,
});

/**
 * Global progress bar component
 * Shows progress when navigating between routes
 */
export function ProgressBar() {
  const [location] = useLocation();

  useEffect(() => {
    // Start progress on route change
    NProgress.start();

    // Complete progress after a delay
    const timer = setTimeout(() => {
      NProgress.done();
    }, 500);

    return () => {
      clearTimeout(timer);
      NProgress.done();
    };
  }, [location]);

  return null;
}

export default ProgressBar;
