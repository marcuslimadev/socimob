import { motion } from 'framer-motion';
import { UserRound } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

export default function Pessoas() {
  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-5xl mx-auto"
        >
          <div className="mb-8">
            <h1 className="text-4xl font-bold gradient-text mb-2">Pessoas</h1>
            <p className="text-muted-foreground">Cadastro completo de clientes, proprietários e contatos.</p>
          </div>

          <div className="glass-panel p-8 rounded-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center text-white">
                <UserRound size={24} />
              </div>
              <div>
                <p className="text-lg font-semibold text-foreground">Em desenvolvimento</p>
                <p className="text-sm text-muted-foreground">
                  Estamos preparando o cadastro multi-abas e a pesquisa avançada.
                </p>
              </div>
            </div>
            <div className="text-sm text-muted-foreground">
              Próximos passos: criação, importação e vínculos com imóveis e vistorias.
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
