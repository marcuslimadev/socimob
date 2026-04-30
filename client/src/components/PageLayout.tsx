import { ReactNode } from 'react';
import Sidebar from './Sidebar';

interface PageLayoutProps {
  children: ReactNode;
}

export default function PageLayout({ children }: PageLayoutProps) {
  return (
    <div className="flex min-h-screen bg-background">
      <Sidebar />

      <div className="page-shell">
        <div className="page-content">{children}</div>
      </div>
    </div>
  );
}
