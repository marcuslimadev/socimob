import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Info } from 'lucide-react';

const VersionBadge = () => {
  const [isExpanded, setIsExpanded] = useState(false);

  // Versão do package.json
  const version = '1.0.0';

  // Build timestamp - será gerado no momento do build
  const buildDate = new Date().toISOString();
  const buildHash = buildDate.slice(0, 16).replace(/[-:T]/g, '');

  const formatBuildDate = () => {
    const date = new Date(buildDate);
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 1 }}
      className="fixed bottom-4 right-4 z-50"
      onMouseEnter={() => setIsExpanded(true)}
      onMouseLeave={() => setIsExpanded(false)}
    >
      <motion.div
        animate={{ width: isExpanded ? 'auto' : '60px' }}
        className="glass-panel px-3 py-2 rounded-xl backdrop-blur-xl bg-white/5 border border-white/10 cursor-pointer"
      >
        <div className="flex items-center gap-2">
          <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />

          {!isExpanded ? (
            <span className="text-xs font-mono text-muted-foreground">
              v{version}
            </span>
          ) : (
            <div className="flex flex-col gap-1 min-w-[180px]">
              <div className="flex items-center gap-2">
                <Info size={12} className="text-blue-400" />
                <span className="text-xs font-semibold text-foreground">
                  SOCIMOB v{version}
                </span>
              </div>
              <div className="text-[10px] text-muted-foreground space-y-0.5">
                <div>Build: {buildHash}</div>
                <div>Data: {formatBuildDate()}</div>
              </div>
            </div>
          )}
        </div>
      </motion.div>
    </motion.div>
  );
};

export default VersionBadge;
