import { useState, useEffect } from 'react';
import { useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { RefreshCw, Search, Filter, Download, AlertCircle, Info, AlertTriangle, CheckCircle } from 'lucide-react';
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
      case 'error': return 'bg-red-100 text-red-800 border-red-300';
      case 'warning': return 'bg-yellow-100 text-yellow-800 border-yellow-300';
      case 'success': return 'bg-green-100 text-green-800 border-green-300';
      default: return 'bg-blue-100 text-blue-800 border-blue-300';
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
    <div className="flex h-screen bg-background">
      <Sidebar />
      <main className="flex-1 overflow-y-auto">
        <div className="p-6 space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold">Logs do Sistema</h1>
              <p className="text-gray-600 mt-1">Monitoramento e auditoria de eventos</p>
            </div>
        <div className="flex gap-2">
          <Button onClick={exportLogs} variant="outline" size="sm">
            <Download className="w-4 h-4 mr-2" />
            Exportar CSV
          </Button>
          <Button onClick={fetchLogs} variant="outline" size="sm">
            <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Atualizar
          </Button>
        </div>
      </div>

      {/* Filtros */}
      <Card className="p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
            <Input
              placeholder="Buscar mensagem ou ação..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && fetchLogs()}
              className="pl-10"
            />
          </div>

          <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger>
              <SelectValue placeholder="Categoria" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas Categorias</SelectItem>
              <SelectItem value="automation">Automação IA</SelectItem>
              <SelectItem value="twilio">Twilio/WhatsApp</SelectItem>
              <SelectItem value="openai">OpenAI</SelectItem>
              <SelectItem value="auth">Autenticação</SelectItem>
              <SelectItem value="webhook">Webhooks</SelectItem>
              <SelectItem value="system">Sistema</SelectItem>
            </SelectContent>
          </Select>

          <Select value={levelFilter} onValueChange={setLevelFilter}>
            <SelectTrigger>
              <SelectValue placeholder="Nível" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos Níveis</SelectItem>
              <SelectItem value="error">Erro</SelectItem>
              <SelectItem value="warning">Aviso</SelectItem>
              <SelectItem value="info">Info</SelectItem>
              <SelectItem value="success">Sucesso</SelectItem>
            </SelectContent>
          </Select>

          <Button onClick={fetchLogs} className="w-full">
            <Filter className="w-4 h-4 mr-2" />
            Filtrar
          </Button>
        </div>
      </Card>

      {/* Lista de Logs */}
      <div className="space-y-2">
        {loading && logs.length === 0 ? (
          <Card className="p-8 text-center text-gray-500">
            <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2" />
            Carregando logs...
          </Card>
        ) : logs.length === 0 ? (
          <Card className="p-8 text-center text-gray-500">
            Nenhum log encontrado
          </Card>
        ) : (
          logs.map((log) => (
            <Card key={log.id} className="p-4 hover:shadow-md transition-shadow">
              <div className="flex items-start gap-4">
                <div className="pt-1">
                  {getLevelIcon(log.level)}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-2">
                    <Badge variant="outline" className={getLevelColor(log.level)}>
                      {log.level.toUpperCase()}
                    </Badge>
                    <Badge variant="outline">{log.category}</Badge>
                    <span className="text-xs text-gray-500">
                      {new Date(log.created_at).toLocaleString('pt-BR')}
                    </span>
                    <span className="text-xs text-gray-400">#{log.id}</span>
                  </div>
                  <div className="font-medium text-gray-900 mb-1">{log.action}</div>
                  <div className="text-sm text-gray-600 mb-2">{log.message}</div>
                  {log.metadata && Object.keys(log.metadata).length > 0 && (
                    <details className="text-xs">
                      <summary className="cursor-pointer text-blue-600 hover:text-blue-800">
                        Ver detalhes técnicos
                      </summary>
                      <pre className="mt-2 p-3 bg-gray-50 rounded border overflow-x-auto">
                        {JSON.stringify(log.metadata, null, 2)}
                      </pre>
                    </details>
                  )}
                </div>
              </div>
            </Card>
          ))
        )}
      </div>

      {/* Paginação */}
      {totalPages > 1 && (
        <div className="flex justify-center gap-2">
          <Button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page === 1}
            variant="outline"
          >
            Anterior
          </Button>
          <div className="flex items-center gap-2 px-4">
            <span className="text-sm text-gray-600">
              Página {page} de {totalPages}
            </span>
          </div>
          <Button
            onClick={() => setPage(p => Math.min(totalPages, p + 1))}
            disabled={page === totalPages}
            variant="outline"
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
