import { useState } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { motion } from 'framer-motion';
import { Mail, ArrowRight, ArrowLeft } from 'lucide-react';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [, setLocation] = useLocation();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!email) {
            toast.error('Por favor, informe seu e-mail');
            return;
        }

        setIsLoading(true);

        try {
            const response = await api.post('/auth/forgot-password', {
                email
            });

            if (response.data.success) {
                toast.success(response.data.message);
                setTimeout(() => setLocation('/login'), 3000);
            } else {
                toast.error(response.data.message || 'Erro ao enviar email');
            }
        } catch (error: any) {
            console.error('Forgot password error:', error);
            const errorMessage = error.response?.data?.message || 'Erro ao solicitar recuperação. Tente novamente.';
            toast.error(errorMessage);
        } finally {
            setIsLoading(false);
        }
    };

    const containerVariants = {
        hidden: { opacity: 0 },
        visible: {
            opacity: 1,
            transition: { staggerChildren: 0.1, delayChildren: 0.2 },
        },
    };

    const itemVariants = {
        hidden: { opacity: 0, y: 20 },
        visible: { opacity: 1, y: 0 },
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-background via-background to-purple-950/20 flex items-center justify-center p-4">
            <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="w-full max-w-md"
            >
                <motion.div variants={itemVariants} className="text-center mb-8">
                    <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        className="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4 glow-lg"
                    >
                        <Mail size={32} />
                    </motion.div>
                    <h1 className="text-2xl sm:text-3xl font-bold gradient-text mb-2">Recuperar Senha</h1>
                    <p className="text-sm sm:text-base text-muted-foreground">Informe seu e-mail para receber o link de redefinição</p>
                </motion.div>

                <motion.div
                    variants={itemVariants}
                    className="glass-panel p-6 sm:p-8 rounded-3xl mb-6"
                >
                    <form onSubmit={handleSubmit}>
                        <motion.div variants={itemVariants} className="mb-6">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Email
                            </label>
                            <div className="relative">
                                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="seu@email.com"
                                    className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                    disabled={isLoading}
                                />
                            </div>
                        </motion.div>

                        <motion.button
                            type="submit"
                            variants={itemVariants}
                            whileHover={{ scale: 1.02 }}
                            whileTap={{ scale: 0.98 }}
                            className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg mb-4 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled={isLoading}
                        >
                            {isLoading ? 'Enviando...' : 'Enviar Link'}
                            {!isLoading && <ArrowRight size={20} />}
                        </motion.button>

                        <div className="text-center">
                            <a href="/login" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors">
                                <ArrowLeft size={16} />
                                Voltar para Login
                            </a>
                        </div>
                    </form>
                </motion.div>
            </motion.div>
        </div>
    );
}
