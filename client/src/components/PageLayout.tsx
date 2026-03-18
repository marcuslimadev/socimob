import { ReactNode } from 'react';
import Sidebar from './Sidebar';

interface PageLayoutProps {
  children: ReactNode;
}

export default function PageLayout({ children }: PageLayoutProps) {
  return (
    <div className="flex min-h-screen bg-background">
      <Sidebar />

      <div className="page-shell w-full px-3 pt-20 sm:px-4 md:px-6 lg:px-8">
        {children}
      </div>
    </div>
  );
}
