import { useState, useEffect } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { motion } from 'framer-motion';
import { Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';

export default function ResetPassword() {
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [, setLocation] = useLocation();
    const [token, setToken] = useState('');
    const [email, setEmail] = useState('');

    useEffect(() => {
        // Extract query params manually since wouter doesn't have useSearchParams hook built-in same way
        const searchParams = new URLSearchParams(window.location.search);
        const t = searchParams.get('token');
        const e = searchParams.get('email');

        if (t) setToken(t);
        if (e) setEmail(e);

        if (!t || !e) {
            toast.error('Link inválido ou incompleto.');
            // setLocation('/login');
        }
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!password || !passwordConfirmation) {
            toast.error('Preencha os campos de senha');
            return;
        }

        if (password !== passwordConfirmation) {
            toast.error('As senhas não conferem');
            return;
        }

        if (password.length < 6) {
            toast.error('A senha deve ter no mínimo 6 caracteres');
            return;
        }

        setIsLoading(true);

        try {
            const response = await api.post('/auth/reset-password', {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation
            });

            if (response.data.success) {
                toast.success(response.data.message);
                setTimeout(() => setLocation('/login'), 2000);
            } else {
                toast.error(response.data.message || 'Erro ao redefinir senha');
            }
        } catch (error: any) {
            console.error('Reset error:', error);
            const errorMessage = error.response?.data?.message || 'Erro ao redefinir senha.';
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
                        className="w-16 h-16 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4 glow-lg"
                    >
                        <Lock size={32} />
                    </motion.div>
                    <h1 className="text-2xl sm:text-3xl font-bold gradient-text mb-2">Nova Senha</h1>
                    <p className="text-sm sm:text-base text-muted-foreground">Defina uma nova senha para sua conta</p>
                </motion.div>

                <motion.div
                    variants={itemVariants}
                    className="glass-panel p-6 sm:p-8 rounded-3xl mb-6"
                >
                    <form onSubmit={handleSubmit}>
                        <motion.div variants={itemVariants} className="mb-6">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Nova Senha
                            </label>
                            <div className="relative">
                                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full pl-12 pr-12 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                    disabled={isLoading}
                                />
                                <motion.button
                                    type="button"
                                    whileHover={{ scale: 1.1 }}
                                    whileTap={{ scale: 0.95 }}
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                    disabled={isLoading}
                                >
                                    {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                                </motion.button>
                            </div>
                        </motion.div>

                        <motion.div variants={itemVariants} className="mb-6">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Confirmar Senha
                            </label>
                            <div className="relative">
                                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={passwordConfirmation}
                                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full pl-12 pr-12 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
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
                            {isLoading ? 'Redefinindo...' : 'Alterar Senha'}
                            {!isLoading && <ArrowRight size={20} />}
                        </motion.button>
                    </form>
                </motion.div>
            </motion.div>
        </div>
    );
}
