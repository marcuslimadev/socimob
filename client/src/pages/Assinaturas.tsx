import { motion } from 'framer-motion';
import { FileSignature } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

export default function Assinaturas() {
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
            <h1 className="text-4xl font-bold gradient-text mb-2">Assinaturas</h1>
            <p className="text-muted-foreground">Gestão de documentos e assinantes.</p>
          </div>

          <div className="glass-panel p-8 rounded-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-sky-600 flex items-center justify-center text-white">
                <FileSignature size={24} />
              </div>
              <div>
                <p className="text-lg font-semibold text-foreground">Em desenvolvimento</p>
                <p className="text-sm text-muted-foreground">
                  Estamos estruturando a listagem e o fluxo de envio de documentos.
                </p>
              </div>
            </div>
            <div className="text-sm text-muted-foreground">
              Próximos passos: upload, definição de assinantes e integração com provedor.
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
