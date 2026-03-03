import { useEffect, useMemo, useState } from 'react';
import { KeyRound, RefreshCw } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface PropertyKeyRow {
  id: number;
  codigo_imovel: string;
  titulo: string;
  bairro?: string;
  cidade?: string;
  local_chaves?: string;
  status_chaves?: 'disponivel' | 'retirada' | 'reserva' | string;
}

interface KeyMovement {
  id: number;
  property_id: number;
  tipo: 'retirada' | 'devolucao';
  responsavel: string;
  destino?: string;
  observacoes?: string;
  movimentado_em: string;
  codigo_imovel?: string;
}

export default function ControleChaves() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [rows, setRows] = useState<PropertyKeyRow[]>([]);
  const [movements, setMovements] = useState<KeyMovement[]>([]);
  const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(null);
  const [tipo, setTipo] = useState<'retirada' | 'devolucao'>('retirada');
  const [responsavel, setResponsavel] = useState('');
  const [destino, setDestino] = useState('');
  const [localChaves, setLocalChaves] = useState('');
  const [observacoes, setObservacoes] = useState('');

  const loadData = async () => {
    try {
      setLoading(true);
      const [keysRes, movRes] = await Promise.all([
        api.get('/chaves'),
        api.get('/chaves/movimentacoes'),
      ]);
      setRows(Array.isArray(keysRes.data?.data) ? keysRes.data.data : []);
      setMovements(Array.isArray(movRes.data?.data) ? movRes.data.data : []);
    } catch {
      toast.error('Erro ao carregar controle de chaves.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const selectedProperty = useMemo(
    () => rows.find((row) => row.id === selectedPropertyId) || null,
    [rows, selectedPropertyId],
  );

  const handleSelectProperty = (id: number) => {
    setSelectedPropertyId(id);
    const item = rows.find((row) => row.id === id);
    setLocalChaves(item?.local_chaves || '');
  };

  const handleRegisterMovement = async () => {
    if (!selectedPropertyId) {
      toast.error('Selecione um imóvel.');
      return;
    }
    if (!responsavel.trim()) {
      toast.error('Informe o responsável.');
      return;
    }

    try {
      setSaving(true);
      await api.post('/chaves/movimentacoes', {
        property_id: selectedPropertyId,
        tipo,
        responsavel: responsavel.trim(),
        destino: destino.trim() || null,
        local_chaves: localChaves.trim() || null,
        observacoes: observacoes.trim() || null,
      });
      toast.success('Movimentação registrada.');
      setResponsavel('');
      setDestino('');
      setObservacoes('');
      await loadData();
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao registrar movimentação.');
    } finally {
      setSaving(false);
    }
  };

  const filteredRows = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return rows;
    return rows.filter((row) =>
      `${row.codigo_imovel} ${row.titulo} ${row.bairro || ''} ${row.cidade || ''}`.toLowerCase().includes(term),
    );
  }, [rows, search]);

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-7xl mx-auto space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title">Controle de Chaves</h1>
              <p className="page-subtitle">Retirada, devolução e localização atual das chaves dos imóveis.</p>
            </div>
            <button
              type="button"
              onClick={loadData}
              className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm"
            >
              <RefreshCw size={16} />
              Atualizar
            </button>
          </div>

          <div className="glass-panel rounded-2xl p-4">
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Buscar imóvel por código, título, bairro, cidade"
              className="w-full h-11 rounded-xl border border-black/10 bg-white px-4 text-sm text-slate-900"
            />
          </div>

          <div className="grid grid-cols-1 xl:grid-cols-[1.15fr_0.85fr] gap-6">
            <div className="glass-panel rounded-2xl overflow-hidden">
              <div className="p-4 border-b border-border font-semibold">Imóveis e status de chave</div>
              <div className="max-h-[560px] overflow-auto">
                {loading ? (
                  <p className="p-4 text-sm text-muted-foreground">Carregando...</p>
                ) : filteredRows.length === 0 ? (
                  <p className="p-4 text-sm text-muted-foreground">Nenhum imóvel encontrado.</p>
                ) : (
                  <table className="w-full min-w-[700px]">
                    <thead>
                      <tr className="border-b border-border">
                        <th className="text-left p-3 text-xs text-muted-foreground">Código</th>
                        <th className="text-left p-3 text-xs text-muted-foreground">Imóvel</th>
                        <th className="text-left p-3 text-xs text-muted-foreground">Local das chaves</th>
                        <th className="text-left p-3 text-xs text-muted-foreground">Status</th>
                        <th className="text-left p-3 text-xs text-muted-foreground">Ação</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredRows.map((row) => (
                        <tr key={row.id} className="border-b border-border/50">
                          <td className="p-3 text-sm font-mono">{row.codigo_imovel || '-'}</td>
                          <td className="p-3 text-sm">
                            <p className="font-medium">{row.titulo}</p>
                            <p className="text-xs text-muted-foreground">{[row.bairro, row.cidade].filter(Boolean).join(', ')}</p>
                          </td>
                          <td className="p-3 text-sm">{row.local_chaves || '-'}</td>
                          <td className="p-3 text-sm capitalize">{row.status_chaves || 'disponivel'}</td>
                          <td className="p-3">
                            <button
                              type="button"
                              onClick={() => handleSelectProperty(row.id)}
                              className="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-1 text-xs"
                            >
                              <KeyRound size={14} />
                              Selecionar
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>

            <div className="space-y-6">
              <div className="glass-panel rounded-2xl p-4 space-y-3">
                <h3 className="font-semibold">Nova movimentação</h3>
                <p className="text-xs text-muted-foreground">
                  Imóvel selecionado: {selectedProperty ? `${selectedProperty.codigo_imovel} - ${selectedProperty.titulo}` : 'nenhum'}
                </p>
                <select value={tipo} onChange={(e) => setTipo(e.target.value as 'retirada' | 'devolucao')} className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm">
                  <option value="retirada">Retirada de chave</option>
                  <option value="devolucao">Devolução de chave</option>
                </select>
                <input value={responsavel} onChange={(e) => setResponsavel(e.target.value)} className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm" placeholder="Responsável (corretor/portaria/cliente)" />
                <input value={destino} onChange={(e) => setDestino(e.target.value)} className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm" placeholder="Destino (opcional)" />
                <input value={localChaves} onChange={(e) => setLocalChaves(e.target.value)} className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm" placeholder="Local atual das chaves" />
                <textarea value={observacoes} onChange={(e) => setObservacoes(e.target.value)} className="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm" rows={3} placeholder="Observações (opcional)" />
                <button type="button" disabled={saving} onClick={handleRegisterMovement} className="w-full rounded-lg bg-blue-600 text-white py-2 text-sm font-semibold disabled:opacity-60">
                  {saving ? 'Salvando...' : 'Registrar movimentação'}
                </button>
              </div>

              <div className="glass-panel rounded-2xl overflow-hidden">
                <div className="p-4 border-b border-border font-semibold">Histórico recente</div>
                <div className="max-h-[300px] overflow-auto p-4 space-y-3">
                  {movements.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Sem movimentações registradas.</p>
                  ) : (
                    movements.slice(0, 20).map((item) => (
                      <div key={item.id} className="rounded-xl border border-border p-3">
                        <p className="text-xs text-muted-foreground">{new Date(item.movimentado_em).toLocaleString('pt-BR')}</p>
                        <p className="text-sm font-semibold">{item.codigo_imovel} - {item.tipo}</p>
                        <p className="text-sm">{item.responsavel}</p>
                        {item.destino ? <p className="text-xs text-muted-foreground">Destino: {item.destino}</p> : null}
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

