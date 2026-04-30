import { motion } from 'framer-motion';
import { Link } from 'wouter';

interface QuickAccessCardProps {
  title: string;
  description: string;
  href: string;
  icon: React.ReactNode;
  badge?: string;
  gradient?: string;
  delay?: number;
}

const QuickAccessCard = ({
  title,
  description,
  href,
  icon,
  badge,
  gradient = 'from-blue-500 to-purple-600',
  delay = 0,
}: QuickAccessCardProps) => {
  return (
    <Link to={href}>
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay, type: 'spring', stiffness: 200 }}
        whileHover={{ y: -4, scale: 1.02 }}
        className="system-panel p-5 rounded-2xl group cursor-pointer h-full"
      >
        <div className="flex items-start justify-between gap-4 mb-4">
          <div className="flex-1">
            <p className="text-lg font-semibold text-foreground mb-1">{title}</p>
            <p className="text-sm text-muted-foreground">{description}</p>
          </div>
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ delay: delay + 0.1, type: 'spring', stiffness: 200 }}
            className={`system-icon-box w-11 h-11 rounded-xl ${gradient} flex items-center justify-center transition-all`}
          >
            {icon}
          </motion.div>
        </div>
        {badge && (
          <div className="system-chip inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
            {badge}
          </div>
        )}
        <div
          className="absolute inset-0 rounded-2xl opacity-0 pointer-events-none"
        />
      </motion.div>
    </Link>
  );
};

export default QuickAccessCard;
