import { motion } from 'framer-motion';
import { X } from 'lucide-react';

interface QuickFiltersProps {
  selectedStatus: string;
  onStatusChange: (status: string) => void;
  selectedSort: string;
  onSortChange: (sort: string) => void;
}

const statusOptions = [
  { value: 'todos', label: 'Todos' },
  { value: 'novo', label: 'Novo' },
  { value: 'em_atendimento', label: 'Atendimento' },
  { value: 'qualificado', label: 'Qualificado' },
  { value: 'proposta', label: 'Proposta' },
  { value: 'fechado', label: 'Fechado' },
  { value: 'perdido', label: 'Perdido' },
];

const sortOptions = [
  { value: 'recente', label: 'Recente' },
  { value: 'nome', label: 'Nome' },
  { value: 'valor-alto', label: 'Valor ↓' },
  { value: 'valor-baixo', label: 'Valor ↑' },
];

export default function QuickFilters({
  selectedStatus,
  onStatusChange,
  selectedSort,
  onSortChange,
}: QuickFiltersProps) {
  return (
    <div className="space-y-3 mb-4">
      {/* Status Filters */}
      <div>
        <p className="text-xs font-semibold text-muted-foreground mb-2 px-1">Status</p>
        <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
          {statusOptions.map((option) => (
            <motion.button
              key={option.value}
              whileTap={{ scale: 0.95 }}
              onClick={() => onStatusChange(option.value)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-all ${
                selectedStatus === option.value
                  ? 'bg-gradient-to-r from-blue-500 to-purple-500 text-white shadow-lg'
                  : 'bg-white/5 text-muted-foreground hover:bg-white/10 border border-white/10'
              }`}
            >
              {option.label}
            </motion.button>
          ))}
        </div>
      </div>

      {/* Sort Options */}
      <div>
        <p className="text-xs font-semibold text-muted-foreground mb-2 px-1">Ordenar</p>
        <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
          {sortOptions.map((option) => (
            <motion.button
              key={option.value}
              whileTap={{ scale: 0.95 }}
              onClick={() => onSortChange(option.value)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-all ${
                selectedSort === option.value
                  ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg'
                  : 'bg-white/5 text-muted-foreground hover:bg-white/10 border border-white/10'
              }`}
            >
              {option.label}
            </motion.button>
          ))}
        </div>
      </div>

      {/* Active Filters Badge */}
      {(selectedStatus !== 'todos' || selectedSort !== 'recente') && (
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-500/10 border border-blue-500/30"
        >
          <span className="text-xs text-blue-400 font-medium">
            Filtros ativos: {selectedStatus !== 'todos' && 'Status'} {selectedSort !== 'recente' && 'Ordenação'}
          </span>
          <button
            onClick={() => {
              onStatusChange('todos');
              onSortChange('recente');
            }}
            className="ml-auto text-blue-400 hover:text-blue-300 transition-colors"
          >
            <X size={14} />
          </button>
        </motion.div>
      )}
    </div>
  );
}
