import { motion } from 'framer-motion';
import { BarChart3, Users, TrendingUp, MessageSquare, Calendar, Zap } from 'lucide-react';
import MetricCard from '@/components/MetricCard';
import LeadCard from '@/components/LeadCard';
import KanbanBoard from '@/components/KanbanBoard';
import Sidebar from '@/components/Sidebar';

export default function Dashboard() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.2,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-7xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <h1 className="text-4xl md:text-5xl font-bold gradient-text mb-2">
              Bem-vindo de volta! 👋
            </h1>
            <p className="text-muted-foreground text-lg">
              Aqui está um resumo do seu negócio imobiliário
            </p>
          </motion.div>

          <motion.div
            variants={itemVariants}
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"
          >
            <MetricCard
              title="Total de Leads"
              value="1,234"
              unit="leads"
              trend={12.5}
              icon={<Users size={24} />}
              gradient="from-blue-500 to-blue-600"
              delay={0}
            />
            <MetricCard
              title="Conversão"
              value="28.5%"
              trend={5.2}
              icon={<TrendingUp size={24} />}
              gradient="from-green-500 to-emerald-600"
              delay={0.1}
            />
            <MetricCard
              title="Imóveis Ativos"
              value="89"
              unit="propriedades"
              trend={-2.1}
              icon={<BarChart3 size={24} />}
              gradient="from-purple-500 to-pink-600"
              delay={0.2}
            />
            <MetricCard
              title="Mensagens"
              value="342"
              unit="não lidas"
              trend={8.3}
              icon={<MessageSquare size={24} />}
              gradient="from-cyan-500 to-blue-600"
              delay={0.3}
            />
          </motion.div>

          <motion.div variants={itemVariants} className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div className="lg:col-span-2 glass-panel p-6 rounded-2xl">
              <h2 className="text-xl font-bold text-foreground mb-6 flex items-center gap-2">
                <Zap size={24} className="text-yellow-400" />
                Funil de Vendas
              </h2>
              <div className="space-y-4">
                {[
                  { label: 'Leads', value: 1234, percent: 100, color: 'from-blue-500 to-blue-600' },
                  { label: 'Contato', value: 856, percent: 69, color: 'from-cyan-500 to-cyan-600' },
                  { label: 'Interesse', value: 542, percent: 44, color: 'from-purple-500 to-purple-600' },
                  { label: 'Negociação', value: 234, percent: 19, color: 'from-orange-500 to-orange-600' },
                  { label: 'Fechado', value: 89, percent: 7, color: 'from-green-500 to-green-600' },
                ].map((stage, index) => (
                  <motion.div
                    key={stage.label}
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.4 + index * 0.1 }}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-medium text-foreground">{stage.label}</span>
                      <span className="text-sm text-muted-foreground">{stage.value} ({stage.percent}%)</span>
                    </div>
                    <div className="h-3 rounded-full bg-white/10 overflow-hidden">
                      <motion.div
                        initial={{ width: 0 }}
                        animate={{ width: `${stage.percent}%` }}
                        transition={{ delay: 0.5 + index * 0.1, duration: 1 }}
                        className={`h-full bg-gradient-to-r ${stage.color} rounded-full`}
                      />
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              className="glass-panel p-6 rounded-2xl"
            >
              <h2 className="text-xl font-bold text-foreground mb-6 flex items-center gap-2">
                <Calendar size={24} className="text-pink-400" />
                Próximas Visitas
              </h2>
              <div className="space-y-4">
                {[
                  { name: 'João Silva', time: 'Hoje, 14:00', property: 'Apt. Pinheiros' },
                  { name: 'Maria Santos', time: 'Amanhã, 10:00', property: 'Casa Vila Madalena' },
                  { name: 'Pedro Costa', time: 'Amanhã, 15:30', property: 'Apt. Itaim' },
                ].map((visit, index) => (
                  <motion.div
                    key={visit.name}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.5 + index * 0.1 }}
                    className="p-3 bg-white/5 rounded-lg border border-white/10 hover:bg-white/10 transition-all"
                  >
                    <p className="font-semibold text-foreground text-sm">{visit.name}</p>
                    <p className="text-xs text-muted-foreground mt-1">{visit.time}</p>
                    <p className="text-xs text-cyan-400 mt-1">{visit.property}</p>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          </motion.div>

          <motion.div variants={itemVariants} className="mb-8">
            <h2 className="text-2xl font-bold text-foreground mb-6">Leads Recentes</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <LeadCard
                name="João Silva"
                phone="(11) 98765-4321"
                email="joao@email.com"
                status="novo"
                delay={0.5}
              />
              <LeadCard
                name="Maria Santos"
                phone="(11) 98765-4322"
                status="contato"
                value={450000}
                delay={0.6}
              />
              <LeadCard
                name="Pedro Costa"
                phone="(11) 98765-4323"
                status="interesse"
                value={180000}
                lastContact="Há 2 horas"
                delay={0.7}
              />
            </div>
          </motion.div>

          <motion.div variants={itemVariants}>
            <h2 className="text-2xl font-bold text-foreground mb-6">Funil de Leads</h2>
            <KanbanBoard />
          </motion.div>
        </motion.div>
      </div>
    </div>
  );
}
