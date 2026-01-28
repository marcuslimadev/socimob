import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Loader2, Plus, ChevronLeft, ChevronRight, Calendar as CalendarIcon } from 'lucide-react';
import { toast } from 'sonner';
import { Link } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Solicitacao {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string;
  tipo: string;
  imovel_id: number | null;
  created_at?: string | null;
}

const MESES = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
];

const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    solicitada: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    designada: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    andamento: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
    concluida: 'bg-green-500/20 text-green-300 border-green-500/30',
    cancelada: 'bg-red-500/20 text-red-300 border-red-500/30',
  };
  return colors[status] || 'bg-gray-500/20 text-gray-300 border-gray-500/30';
};

export default function VistoriaSolicitacoesCalendario() {
  const [solicitacoes, setSolicitacoes] = useState<Solicitacao[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [currentDate, setCurrentDate] = useState(new Date());

  useEffect(() => {
    fetchSolicitacoes();
  }, [currentDate]);

  const fetchSolicitacoes = async () => {
    try {
      setIsLoading(true);

      // Buscar solicitações do mês atual
      const startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
      const endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);

      const response = await api.get('/vistorias/solicitacoes', {
        params: { per_page: 100 }
      });

      setSolicitacoes(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar solicitações:', error);
      toast.error('Erro ao carregar solicitações');
    } finally {
      setIsLoading(false);
    }
  };

  const getDaysInMonth = () => {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const days = [];

    // Dias do mês anterior
    for (let i = 0; i < firstDay; i++) {
      days.push(null);
    }

    // Dias do mês atual
    for (let i = 1; i <= daysInMonth; i++) {
      days.push(i);
    }

    return days;
  };

  const getSolicitacoesForDay = (day: number | null) => {
    if (!day) return [];

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    return solicitacoes.filter(s => {
      if (!s.created_at) return false;
      const solicitacaoDate = new Date(s.created_at).toISOString().split('T')[0];
      return solicitacaoDate === dateStr;
    });
  };

  const previousMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1));
  };

  const nextMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1));
  };

  const goToToday = () => {
    setCurrentDate(new Date());
  };

  const days = getDaysInMonth();

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="mb-8 flex items-center justify-between">
            <div>
              <h1 className="text-4xl font-bold gradient-text mb-2">Calendário - Solicitações</h1>
              <p className="text-muted-foreground">Visualização em calendário</p>
            </div>
            <div className="flex gap-3">
              <Link to="/vistorias/solicitacoes">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="px-5 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold"
                >
                  Ver Lista
                </motion.button>
              </Link>
              <Link to="/vistorias/solicitacoes/kanban">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="px-5 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold"
                >
                  Ver Kanban
                </motion.button>
              </Link>
              <Link to="/vistorias/solicitacoes/nova">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold"
                >
                  <Plus size={18} />
                  Nova Solicitação
                </motion.button>
              </Link>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-4">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={previousMonth}
                  className="p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-all"
                >
                  <ChevronLeft size={20} />
                </motion.button>

                <h2 className="text-2xl font-bold text-foreground flex items-center gap-2">
                  <CalendarIcon size={24} />
                  {MESES[currentDate.getMonth()]} {currentDate.getFullYear()}
                </h2>

                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={nextMonth}
                  className="p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-all"
                >
                  <ChevronRight size={20} />
                </motion.button>
              </div>

              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={goToToday}
                className="px-4 py-2 rounded-lg bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 transition-all"
              >
                Hoje
              </motion.button>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : (
              <div className="grid grid-cols-7 gap-2">
                {/* Cabeçalho dos dias da semana */}
                {DIAS_SEMANA.map(dia => (
                  <div key={dia} className="text-center font-semibold text-muted-foreground py-2">
                    {dia}
                  </div>
                ))}

                {/* Dias do mês */}
                {days.map((day, index) => {
                  const solicitacoesDay = day ? getSolicitacoesForDay(day) : [];
                  const isToday = day &&
                    day === new Date().getDate() &&
                    currentDate.getMonth() === new Date().getMonth() &&
                    currentDate.getFullYear() === new Date().getFullYear();

                  return (
                    <div
                      key={index}
                      className={`min-h-[120px] p-2 rounded-lg border ${
                        day ? 'bg-white/5 border-white/10' : 'bg-transparent border-transparent'
                      } ${isToday ? 'ring-2 ring-blue-500' : ''}`}
                    >
                      {day && (
                        <>
                          <div className={`text-sm font-semibold mb-2 ${
                            isToday ? 'text-blue-400' : 'text-foreground'
                          }`}>
                            {day}
                          </div>

                          <div className="space-y-1">
                            {solicitacoesDay.slice(0, 3).map(solicitacao => (
                              <div
                                key={solicitacao.id}
                                className={`text-xs p-1 rounded border ${getStatusColor(solicitacao.status)}`}
                              >
                                <div className="font-semibold truncate">
                                  {solicitacao.codigo || `#${solicitacao.id}`}
                                </div>
                                <div className="truncate opacity-80">
                                  {solicitacao.cliente_nome}
                                </div>
                              </div>
                            ))}

                            {solicitacoesDay.length > 3 && (
                              <div className="text-xs text-muted-foreground text-center">
                                +{solicitacoesDay.length - 3} mais
                              </div>
                            )}
                          </div>
                        </>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
