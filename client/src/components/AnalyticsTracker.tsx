import { useEffect } from 'react';
import { useLocation } from 'wouter';
import { initializeAnalytics, trackPageView } from '@/lib/analytics';

export default function AnalyticsTracker() {
  const [location] = useLocation();

  useEffect(() => {
    initializeAnalytics();
  }, []);

  useEffect(() => {
    trackPageView();
  }, [location]);

  return null;
}
