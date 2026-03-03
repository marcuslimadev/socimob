import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Plus, GripVertical } from 'lucide-react';
import LeadCard from './LeadCard';

interface Lead {
  id: string;
  name: string;
  phone: string;
  email?: string;
  value?: number;
  lastContact?: string;
}

interface Column {
  id: string;
  title: string;
  icon: string;
  color: string;
  leads: Lead[];
}

interface KanbanBoardProps {
  columns?: Column[];
}

const KanbanBoard = ({ columns: initialColumns }: KanbanBoardProps) => {
  const [columns, setColumns] = useState<Column[]>(
    initialColumns || [
      {
        id: 'novo',
        title: 'Novo',
        icon: '✨',
        color: 'from-blue-500 to-blue-600',
        leads: [
          { id: '1', name: 'João Silva', phone: '(11) 98765-4321', email: 'joao@email.com' },
          { id: '2', name: 'Maria Santos', phone: '(11) 98765-4322' },
        ],
      },
      {
        id: 'contato',
        title: 'Primeiro Contato',
        icon: '📞',
        color: 'from-cyan-500 to-cyan-600',
        leads: [
          { id: '3', name: 'Pedro Costa', phone: '(11) 98765-4323', value: 450000 },
        ],
      },
      {
        id: 'interesse',
        title: 'Interesse',
        icon: '❤️',
        color: 'from-purple-500 to-purple-600',
        leads: [
          { id: '4', name: 'Ana Oliveira', phone: '(11) 98765-4324', value: 180000 },
        ],
      },
      {
        id: 'negociacao',
        title: 'Negociação',
        icon: '💬',
        color: 'from-orange-500 to-orange-600',
        leads: [],
      },
      {
        id: 'fechado',
        title: 'Fechado',
        icon: '✅',
        color: 'from-green-500 to-green-600',
        leads: [
          { id: '5', name: 'Carlos Souza', phone: '(11) 98765-4325', value: 320000, lastContact: 'Hoje' },
        ],
      },
    ]
  );

  const [draggedItem, setDraggedItem] = useState<{ columnId: string; leadId: string } | null>(null);

  const handleDragStart = (columnId: string, leadId: string) => {
    setDraggedItem({ columnId, leadId });
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = (targetColumnId: string) => {
    if (!draggedItem) return;

    if (draggedItem.columnId === targetColumnId) {
      setDraggedItem(null);
      return;
    }

    setColumns((prevColumns) => {
      const newColumns = prevColumns.map((col) => ({ ...col }));
      const sourceColumn = newColumns.find((col) => col.id === draggedItem.columnId);
      const targetColumn = newColumns.find((col) => col.id === targetColumnId);

      if (!sourceColumn || !targetColumn) return prevColumns;

      const leadIndex = sourceColumn.leads.findIndex((lead) => lead.id === draggedItem.leadId);
      if (leadIndex === -1) return prevColumns;

      const [lead] = sourceColumn.leads.splice(leadIndex, 1);
      targetColumn.leads.push(lead);

      return newColumns;
    });

    setDraggedItem(null);
  };

  const statusMap: Record<string, 'novo' | 'contato' | 'interesse' | 'negociacao' | 'fechado'> = {
    novo: 'novo',
    contato: 'contato',
    interesse: 'interesse',
    negociacao: 'negociacao',
    fechado: 'fechado',
  };

  return (
    <div className="overflow-x-auto pb-4">
      <div className="flex gap-4 min-w-max">
        <AnimatePresence>
          {columns.map((column, columnIndex) => (
            <motion.div
              key={column.id}
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -20 }}
              transition={{ delay: columnIndex * 0.1 }}
              className="w-80 flex-shrink-0"
            >
              <div className="mb-4">
                <div className={`flex items-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r ${column.color} bg-opacity-10 border border-white/10`}>
                  <span className="text-xl">{column.icon}</span>
                  <h3 className="font-bold text-foreground flex-1">{column.title}</h3>
                  <motion.span
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    className="px-2 py-1 bg-white/10 rounded-lg text-sm font-semibold text-muted-foreground"
                  >
                    {column.leads.length}
                  </motion.span>
                </div>
              </div>

              <motion.div
                onDragOver={handleDragOver}
                onDrop={() => handleDrop(column.id)}
                className="space-y-3 min-h-96 p-2 rounded-xl bg-white/5 border border-white/10 transition-all"
                animate={{
                  backgroundColor: draggedItem && draggedItem.columnId !== column.id ? 'rgba(255, 255, 255, 0.1)' : 'rgba(255, 255, 255, 0.05)',
                }}
              >
                <AnimatePresence>
                  {column.leads.length === 0 ? (
                    <motion.div
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      className="h-32 flex items-center justify-center text-muted-foreground text-sm"
                    >
                      <div className="text-center">
                        <p className="mb-2">Nenhum lead</p>
                        <p className="text-xs">Arraste leads aqui</p>
                      </div>
                    </motion.div>
                  ) : (
                    column.leads.map((lead, leadIndex) => (
                      <motion.div
                        key={lead.id}
                        draggable
                        onDragStart={() => handleDragStart(column.id, lead.id)}
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.9 }}
                        transition={{ delay: leadIndex * 0.05 }}
                        className="cursor-grab active:cursor-grabbing"
                      >
                        <div className="relative group">
                          <div className="absolute -left-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <GripVertical size={16} className="text-muted-foreground" />
                          </div>

                          <LeadCard
                            name={lead.name}
                            phone={lead.phone}
                            email={lead.email}
                            status={statusMap[column.id]}
                            value={lead.value}
                            lastContact={lead.lastContact}
                          />
                        </div>
                      </motion.div>
                    ))
                  )}
                </AnimatePresence>

                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-muted-foreground hover:text-foreground hover:bg-white/10 transition-all border border-dashed border-white/20"
                >
                  <Plus size={16} />
                  <span className="text-sm font-medium">Novo Lead</span>
                </motion.button>
              </motion.div>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>
    </div>
  );
};

export default KanbanBoard;
