import { useState, ReactNode } from 'react';
import Sidebar from './Sidebar';
import MobileHeader from './MobileHeader';

interface PageLayoutProps {
  children: ReactNode;
}

export default function PageLayout({ children }: PageLayoutProps) {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-background">
      <MobileHeader isMenuOpen={isMobileMenuOpen} onMenuToggle={setIsMobileMenuOpen} />
      <Sidebar isOpen={isMobileMenuOpen} onClose={() => setIsMobileMenuOpen(false)} />
      
      <div className="flex-1 md:ml-80 min-h-screen p-3 sm:p-4 md:p-6 lg:p-8 pt-20 md:pt-6 pb-24 md:pb-8 w-full">
        {children}
      </div>
    </div>
  );
}
