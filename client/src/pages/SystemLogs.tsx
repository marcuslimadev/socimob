import { useState, useEffect } from 'react';
import { useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { RefreshCw, Search, Download, AlertCircle, Info, AlertTriangle, CheckCircle, FileText, X } from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

interface SystemLog {
  id: number;
  tenant_id: number;
  category: string;
  level: 'error' | 'warning' | 'info' | 'success';
  action: string;
  message: string;
  metadata: any;
  user_id?: number;
  created_at: string;
}

export default function SystemLogs() {
  const [, setLocation] = useLocation();
  
  // Verificar autenticação e permissão
  useEffect(() => {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token) {
      setLocation('/login');
      return;
    }

    if (userStr) {
      const user = JSON.parse(userStr);
      if (user.role !== 'admin' && user.role !== 'super_admin') {
        setLocation('/dashboard');
        return;
      }
    }
  }, [setLocation]);
  const [logs, setLogs] = useState<SystemLog[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [levelFilter, setLevelFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams({
        page: page.toString(),
        per_page: '50',
        ...(search && { search }),
        ...(categoryFilter !== 'all' && { category: categoryFilter }),
        ...(levelFilter !== 'all' && { level: levelFilter }),
      });

      const response = await fetch(`/api/admin/system-logs?${params}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setLogs(data.data || []);
        setTotalPages(data.last_page || 1);
      }
    } catch (error) {
      console.error('Erro ao carregar logs:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, [page, categoryFilter, levelFilter]);

  const getLevelIcon = (level: string) => {
    switch (level) {
      case 'error': return <AlertCircle className="w-4 h-4 text-red-500" />;
      case 'warning': return <AlertTriangle className="w-4 h-4 text-yellow-500" />;
      case 'success': return <CheckCircle className="w-4 h-4 text-green-500" />;
      default: return <Info className="w-4 h-4 text-blue-500" />;
    }
  };

  const getLevelColor = (level: string) => {
    switch (level) {
      case 'error': return 'bg-red-900/20 text-red-400 border-red-800';
      case 'warning': return 'bg-yellow-900/20 text-yellow-400 border-yellow-800';
      case 'success': return 'bg-green-900/20 text-green-400 border-green-800';
      default: return 'bg-blue-900/20 text-blue-400 border-blue-800';
    }
  };

  const exportLogs = () => {
    const csv = [
      ['ID', 'Data/Hora', 'Nível', 'Categoria', 'Ação', 'Mensagem', 'Metadados'].join(','),
      ...logs.map(log => [
        log.id,
        new Date(log.created_at).toLocaleString('pt-BR'),
        log.level,
        log.category,
        log.action,
        `"${log.message}"`,
        `"${JSON.stringify(log.metadata || {})}"`,
      ].join(','))
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `system-logs-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  return (
    <div className="flex min-h-screen bg-[#0f0f0f]">
      <Sidebar />
      <main className="page-shell overflow-y-auto">
        <div className="max-w-7xl mx-auto space-y-6">
          {/* Header */}
          <div className="page-header">
            <div className="space-y-1">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center">
                  <FileText className="w-5 h-5 text-white" />
                </div>
                <h1 className="text-3xl font-bold text-white">Logs do Sistema</h1>
              </div>
              <p className="text-gray-400 ml-13">Monitoramento e auditoria de eventos em tempo real</p>
            </div>
            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
              <Button 
                onClick={exportLogs} 
                variant="outline" 
                className="w-full bg-[#1a1a1a] border-gray-800 text-gray-300 hover:bg-[#252525] hover:text-white sm:w-auto"
              >
                <Download className="w-4 h-4 mr-2" />
                Exportar
              </Button>
              <Button 
                onClick={fetchLogs}
                className="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 sm:w-auto"
              >
                <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                Atualizar
              </Button>
            </div>
          </div>

          {/* Filtros */}
          <Card className="bg-[#1a1a1a] border-gray-800">
            <div className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 w-4 h-4" />
                  <Input
                    placeholder="Buscar em mensagens e ações..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && fetchLogs()}
                    className="pl-10 bg-[#0f0f0f] border-gray-800 text-white placeholder:text-gray-500"
                  />
                </div>

                <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                  <SelectTrigger className="bg-[#0f0f0f] border-gray-800 text-white">
                    <SelectValue placeholder="Todas Categorias" />
                  </SelectTrigger>
                  <SelectContent className="bg-[#1a1a1a] border-gray-800 text-white">
                    <SelectItem value="all">Todas Categorias</SelectItem>
                    <SelectItem value="automation">🤖 Automação IA</SelectItem>
                    <SelectItem value="twilio">📱 Twilio/WhatsApp</SelectItem>
                    <SelectItem value="openai">🧠 OpenAI</SelectItem>
                    <SelectItem value="auth">🔐 Autenticação</SelectItem>
                    <SelectItem value="webhook">🔗 Webhooks</SelectItem>
                    <SelectItem value="system">⚙️ Sistema</SelectItem>
                  </SelectContent>
                </Select>

                <Select value={levelFilter} onValueChange={setLevelFilter}>
                  <SelectTrigger className="bg-[#0f0f0f] border-gray-800 text-white">
                    <SelectValue placeholder="Todos Níveis" />
                  </SelectTrigger>
                  <SelectContent className="bg-[#1a1a1a] border-gray-800 text-white">
                    <SelectItem value="all">Todos Níveis</SelectItem>
                    <SelectItem value="error">🔴 Erro</SelectItem>
                    <SelectItem value="warning">🟡 Aviso</SelectItem>
                    <SelectItem value="info">🔵 Info</SelectItem>
                    <SelectItem value="success">🟢 Sucesso</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              {(search || categoryFilter !== 'all' || levelFilter !== 'all') && (
                <div className="mt-4 flex items-center gap-2">
                  <span className="text-sm text-gray-400">Filtros ativos:</span>
                  {search && (
                    <Badge className="bg-purple-600/20 text-purple-400 border-purple-600/50">
                      Busca: {search}
                      <X className="w-3 h-3 ml-1 cursor-pointer" onClick={() => setSearch('')} />
                    </Badge>
                  )}
                  {categoryFilter !== 'all' && (
                    <Badge className="bg-blue-600/20 text-blue-400 border-blue-600/50">
                      {categoryFilter}
                      <X className="w-3 h-3 ml-1 cursor-pointer" onClick={() => setCategoryFilter('all')} />
                    </Badge>
                  )}
                  {levelFilter !== 'all' && (
                    <Badge className="bg-orange-600/20 text-orange-400 border-orange-600/50">
                      {levelFilter}
                      <X className="w-3 h-3 ml-1 cursor-pointer" onClick={() => setLevelFilter('all')} />
                    </Badge>
                  )}
                </div>
              )}
            </div>
          </Card>

          {/* Lista de Logs */}
          <div className="space-y-3">
            {loading && logs.length === 0 ? (
              <Card className="bg-[#1a1a1a] border-gray-800">
                <div className="p-12 text-center">
                  <RefreshCw className="w-12 h-12 animate-spin mx-auto mb-4 text-purple-500" />
                  <p className="text-gray-400 text-lg">Carregando logs...</p>
                </div>
              </Card>
            ) : logs.length === 0 ? (
              <Card className="bg-[#1a1a1a] border-gray-800">
                <div className="p-12 text-center">
                  <FileText className="w-16 h-16 mx-auto mb-4 text-gray-600" />
                  <p className="text-gray-400 text-lg mb-2">Nenhum log encontrado</p>
                  <p className="text-gray-600 text-sm">Tente ajustar os filtros ou aguarde novos eventos</p>
                </div>
              </Card>
            ) : (
              logs.map((log) => (
                <Card 
                  key={log.id} 
                  className="bg-[#1a1a1a] border-gray-800 hover:border-gray-700 transition-all group"
                >
                  <div className="p-5">
                    <div className="flex items-start gap-4">
                      {/* Icon */}
                      <div className="pt-0.5">
                        {getLevelIcon(log.level)}
                      </div>
                      
                      {/* Content */}
                      <div className="flex-1 min-w-0 space-y-2">
                        {/* Header */}
                        <div className="flex items-center gap-2 flex-wrap">
                          <Badge 
                            variant="outline" 
                            className={`${getLevelColor(log.level)} font-semibold`}
                          >
                            {log.level.toUpperCase()}
                          </Badge>
                          <Badge variant="outline" className="bg-gray-800/50 text-gray-300 border-gray-700">
                            {log.category}
                          </Badge>
                          <span className="text-xs text-gray-500">
                            {new Date(log.created_at).toLocaleString('pt-BR', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                              second: '2-digit'
                            })}
                          </span>
                          <span className="text-xs text-gray-600">ID: {log.id}</span>
                        </div>
                        
                        {/* Action */}
                        <div className="font-semibold text-white group-hover:text-purple-400 transition-colors">
                          {log.action}
                        </div>
                        
                        {/* Message */}
                        <div className="text-sm text-gray-400 leading-relaxed">
                          {log.message}
                        </div>
                        
                        {/* Metadata */}
                        {log.metadata && Object.keys(log.metadata).length > 0 && (
                          <details className="text-xs">
                            <summary className="cursor-pointer text-blue-400 hover:text-blue-300 inline-flex items-center gap-1">
                              <span>Metadados técnicos</span>
                              <span className="text-gray-600">({Object.keys(log.metadata).length} campos)</span>
                            </summary>
                            <pre className="mt-3 p-4 bg-[#0f0f0f] rounded-lg border border-gray-800 overflow-x-auto text-gray-300 font-mono text-xs">
                              {JSON.stringify(log.metadata, null, 2)}
                            </pre>
                          </details>
                        )}
                      </div>
                    </div>
                  </div>
                </Card>
              ))
            )}
          </div>

          {/* Paginação */}
          {totalPages > 1 && (
            <div className="flex justify-center items-center gap-4 pt-4">
              <Button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                variant="outline"
                className="bg-[#1a1a1a] border-gray-800 text-gray-300 hover:bg-[#252525] disabled:opacity-50"
              >
                Anterior
              </Button>
              <div className="flex items-center gap-2 px-6 py-2 bg-[#1a1a1a] border border-gray-800 rounded-lg">
                <span className="text-sm text-gray-400">
                  Página <span className="text-white font-semibold">{page}</span> de <span className="text-white font-semibold">{totalPages}</span>
                </span>
              </div>
              <Button
                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
                variant="outline"
                className="bg-[#1a1a1a] border-gray-800 text-gray-300 hover:bg-[#252525] disabled:opacity-50"
              >
                Próxima
              </Button>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
