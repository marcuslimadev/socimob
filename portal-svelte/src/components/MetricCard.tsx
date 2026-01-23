import { motion } from 'framer-motion';
import { LineChart, Line, ResponsiveContainer, Tooltip } from 'recharts';
import { TrendingUp, TrendingDown } from 'lucide-react';

interface MetricCardProps {
  title: string;
  value: string | number;
  unit?: string;
  trend?: number;
  data?: Array<{ value: number }>;
  icon?: React.ReactNode;
  gradient?: string;
  delay?: number;
}

const MetricCard = ({
  title,
  value,
  unit,
  trend,
  data = [],
  icon,
  gradient = 'from-blue-500 to-purple-600',
  delay = 0,
}: MetricCardProps) => {
  const isPositive = trend && trend > 0;
  const sparklineData = data.length > 0 ? data : Array.from({ length: 7 }, () => ({ value: Math.random() * 100 }));

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay, type: 'spring', stiffness: 200 }}
      whileHover={{ y: -4, scale: 1.02 }}
      className="glass-panel p-6 rounded-2xl group cursor-pointer"
    >
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <p className="text-sm text-muted-foreground font-medium mb-2">{title}</p>
          <div className="flex items-baseline gap-2">
            <h3 className="text-3xl font-bold gradient-text">{value}</h3>
            {unit && <span className="text-sm text-muted-foreground">{unit}</span>}
          </div>
        </div>

        {icon && (
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ delay: delay + 0.2, type: 'spring', stiffness: 200 }}
            className={`w-12 h-12 rounded-xl bg-gradient-to-br ${gradient} flex items-center justify-center text-white glow-md group-hover:glow-lg transition-all`}
          >
            {icon}
          </motion.div>
        )}
      </div>

      {trend !== undefined && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: delay + 0.3 }}
          className={`flex items-center gap-1 mb-4 text-sm font-semibold ${
            isPositive ? 'text-green-400' : 'text-red-400'
          }`}
        >
          {isPositive ? <TrendingUp size={16} /> : <TrendingDown size={16} />}
          <span>{Math.abs(trend)}%</span>
          <span className="text-muted-foreground">vs. mês anterior</span>
        </motion.div>
      )}

      {sparklineData.length > 0 && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: delay + 0.4 }}
          className="h-12 mt-4"
        >
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={sparklineData}>
              <Tooltip
                contentStyle={{
                  background: 'rgba(10, 14, 39, 0.9)',
                  border: '1px solid rgba(255, 255, 255, 0.1)',
                  borderRadius: '8px',
                }}
                cursor={{ stroke: 'rgba(0, 102, 255, 0.3)' }}
              />
              <Line
                type="monotone"
                dataKey="value"
                stroke={isPositive ? '#10B981' : '#EF4444'}
                dot={false}
                strokeWidth={2}
                isAnimationActive={true}
              />
            </LineChart>
          </ResponsiveContainer>
        </motion.div>
      )}

      <div
        className={`absolute inset-0 rounded-2xl bg-gradient-to-br ${gradient} opacity-0 group-hover:opacity-5 transition-opacity pointer-events-none`}
      />
    </motion.div>
  );
};

export default MetricCard;
