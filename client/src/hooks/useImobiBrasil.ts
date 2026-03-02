/**
 * useImobiBrasil
 * Centralised React hook for all ImobiBrasil API interactions.
 * Uses @tanstack/react-query for caching / mutations.
 */
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import * as ib from '@/lib/imobiBrasilApi';

// ─── Query keys ───────────────────────────────────────────────────────────────

export const ibKeys = {
  accountStatus:             ['ib', 'account', 'status'] as const,
  imoveis:                   (p?: ib.ListImoveisParams) => ['ib', 'imoveis', p] as const,
  imovel:                    (id: number) => ['ib', 'imovel', id] as const,
  tiposImovel:               (p?: object) => ['ib', 'tipos-imovel', p] as const,
  imagensImovel:             (id: number) => ['ib', 'imovel-imagens', id] as const,
  caracteristicas:           (p?: object) => ['ib', 'caracteristicas', p] as const,
  pessoas:                   (p?: ib.ListPessoasParams) => ['ib', 'pessoas', p] as const,
  pessoa:                    (id: number) => ['ib', 'pessoa', id] as const,
  mensagens:                 (p?: ib.ListMensagensParams) => ['ib', 'mensagens', p] as const,
  mensagem:                  (id: number) => ['ib', 'mensagem', id] as const,
  negocios:                  (p?: ib.ListNegociosParams) => ['ib', 'negocios', p] as const,
  negocio:                   (id: number) => ['ib', 'negocio', id] as const,
  etapasNegocios:            ['ib', 'etapas-negocios'] as const,
  corretores:                (p?: object) => ['ib', 'corretores', p] as const,
  corretor:                  (id: number) => ['ib', 'corretor', id] as const,
  imoveisCorretor:           (id: number) => ['ib', 'corretor-imoveis', id] as const,
  clientes:                  (p?: object) => ['ib', 'clientes', p] as const,
  cliente:                   (id: number) => ['ib', 'cliente', id] as const,
  cidades:                   (p?: object) => ['ib', 'cidades', p] as const,
  usuarioAdicional:          (id: number) => ['ib', 'usuario-adicional', id] as const,
};

// ─── Conta ────────────────────────────────────────────────────────────────────

export function useIbAccountStatus() {
  return useQuery({
    queryKey: ibKeys.accountStatus,
    queryFn:  ib.getAccountStatus,
    staleTime: 60_000,
  });
}

// ─── Imóveis ──────────────────────────────────────────────────────────────────

export function useIbImoveis(params?: ib.ListImoveisParams) {
  return useQuery({
    queryKey: ibKeys.imoveis(params),
    queryFn:  () => ib.listImoveis(params),
    staleTime: 30_000,
  });
}

export function useIbImovel(codigoImovel: number | null) {
  return useQuery({
    queryKey: ibKeys.imovel(codigoImovel!),
    queryFn:  () => ib.getImovel(codigoImovel!),
    enabled:  codigoImovel !== null,
    staleTime: 30_000,
  });
}

export function useIbTiposImovel(params?: { page?: number; per_page?: number; descricaoTipoImovel?: string }) {
  return useQuery({
    queryKey: ibKeys.tiposImovel(params),
    queryFn:  () => ib.listTiposImovel(params),
    staleTime: 300_000,
  });
}

export function useIbDeleteImovel() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoImovel: number) => ib.deleteImovel(codigoImovel),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Imóvel excluído no Imobi Brasil');
        qc.invalidateQueries({ queryKey: ['ib', 'imoveis'] });
      } else {
        toast.error(res.error ?? 'Erro ao excluir imóvel');
      }
    },
    onError: () => toast.error('Erro ao excluir imóvel'),
  });
}

// ─── Imagens de imóvel ────────────────────────────────────────────────────────

export function useIbImagensImovel(codigoImovel: number | null) {
  return useQuery({
    queryKey: ibKeys.imagensImovel(codigoImovel!),
    queryFn:  () => ib.listImagensImovel(codigoImovel!),
    enabled:  codigoImovel !== null,
  });
}

export function useIbDeleteImagemImovel() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ codigoImovel, codigoImagem }: { codigoImovel: number; codigoImagem: number }) =>
      ib.deleteImagemImovel(codigoImovel, codigoImagem),
    onSuccess: (res, vars) => {
      if (res.success) {
        toast.success('Imagem removida');
        qc.invalidateQueries({ queryKey: ibKeys.imagensImovel(vars.codigoImovel) });
      } else {
        toast.error(res.error ?? 'Erro ao remover imagem');
      }
    },
    onError: () => toast.error('Erro ao remover imagem'),
  });
}

// ─── Características ──────────────────────────────────────────────────────────

export function useIbCaracteristicas(params?: { page?: number; per_page?: number; nomeGrupo?: string; nomeCaracteristica?: string }) {
  return useQuery({
    queryKey: ibKeys.caracteristicas(params),
    queryFn:  () => ib.listCaracteristicas(params),
    staleTime: 120_000,
  });
}

export function useIbInsertCaracteristica() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ nomeCaracteristica, nomeGrupo }: { nomeCaracteristica: string; nomeGrupo: string }) =>
      ib.insertCaracteristica(nomeCaracteristica, nomeGrupo),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Característica criada');
        qc.invalidateQueries({ queryKey: ['ib', 'caracteristicas'] });
      } else {
        toast.error(res.error ?? 'Erro ao criar característica');
      }
    },
    onError: () => toast.error('Erro ao criar característica'),
  });
}

export function useIbDeleteCaracteristica() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoCaracteristica: number) => ib.deleteCaracteristica(codigoCaracteristica),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Característica excluída');
        qc.invalidateQueries({ queryKey: ['ib', 'caracteristicas'] });
      } else {
        toast.error(res.error ?? 'Erro ao excluir característica');
      }
    },
    onError: () => toast.error('Erro ao excluir característica'),
  });
}

export function useIbAddCaracteristicaToImovel() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ codigoImovel, codigoCaracteristica }: { codigoImovel: number; codigoCaracteristica: number }) =>
      ib.addCaracteristicaToImovel(codigoImovel, codigoCaracteristica),
    onSuccess: (res, vars) => {
      if (res.success) {
        toast.success('Característica adicionada ao imóvel');
        qc.invalidateQueries({ queryKey: ibKeys.imovel(vars.codigoImovel) });
      } else {
        toast.error(res.error ?? 'Erro ao adicionar característica');
      }
    },
    onError: () => toast.error('Erro ao adicionar característica'),
  });
}

export function useIbRemoveCaracteristicaFromImovel() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ codigoImovel, codigoCaracteristica }: { codigoImovel: number; codigoCaracteristica: number }) =>
      ib.removeCaracteristicaFromImovel(codigoImovel, codigoCaracteristica),
    onSuccess: (res, vars) => {
      if (res.success) {
        toast.success('Característica removida do imóvel');
        qc.invalidateQueries({ queryKey: ibKeys.imovel(vars.codigoImovel) });
      } else {
        toast.error(res.error ?? 'Erro ao remover característica');
      }
    },
    onError: () => toast.error('Erro ao remover característica'),
  });
}

// ─── Pessoas ──────────────────────────────────────────────────────────────────

export function useIbPessoas(params?: ib.ListPessoasParams) {
  return useQuery({
    queryKey: ibKeys.pessoas(params),
    queryFn:  () => ib.listPessoas(params),
    staleTime: 30_000,
  });
}

export function useIbPessoa(codigoPessoa: number | null) {
  return useQuery({
    queryKey: ibKeys.pessoa(codigoPessoa!),
    queryFn:  () => ib.getPessoa(codigoPessoa!),
    enabled:  codigoPessoa !== null,
  });
}

export function useIbInsertPessoa() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ib.InsertPessoaPayload) => ib.insertPessoa(payload),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Pessoa cadastrada no Imobi Brasil');
        qc.invalidateQueries({ queryKey: ['ib', 'pessoas'] });
      } else {
        toast.error(res.error ?? 'Erro ao cadastrar pessoa');
      }
    },
    onError: () => toast.error('Erro ao cadastrar pessoa'),
  });
}

export function useIbUpdatePessoa() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ codigoPessoa, payload }: { codigoPessoa: number; payload: ib.InsertPessoaPayload }) =>
      ib.updatePessoa(codigoPessoa, payload),
    onSuccess: (res, vars) => {
      if (res.success) {
        toast.success('Pessoa atualizada no Imobi Brasil');
        qc.invalidateQueries({ queryKey: ibKeys.pessoa(vars.codigoPessoa) });
        qc.invalidateQueries({ queryKey: ['ib', 'pessoas'] });
      } else {
        toast.error(res.error ?? 'Erro ao atualizar pessoa');
      }
    },
    onError: () => toast.error('Erro ao atualizar pessoa'),
  });
}

export function useIbDeletePessoa() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoPessoa: number) => ib.deletePessoa(codigoPessoa),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Pessoa excluída no Imobi Brasil');
        qc.invalidateQueries({ queryKey: ['ib', 'pessoas'] });
      } else {
        toast.error(res.error ?? 'Erro ao excluir pessoa');
      }
    },
    onError: () => toast.error('Erro ao excluir pessoa'),
  });
}

export function useIbDeletePessoaImage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoPessoa: number) => ib.deletePessoaImage(codigoPessoa),
    onSuccess: (res, codigoPessoa) => {
      if (res.success) {
        toast.success('Imagem da pessoa removida');
        qc.invalidateQueries({ queryKey: ibKeys.pessoa(codigoPessoa) });
      } else {
        toast.error(res.error ?? 'Erro ao remover imagem');
      }
    },
    onError: () => toast.error('Erro ao remover imagem da pessoa'),
  });
}

// ─── Mensagens ────────────────────────────────────────────────────────────────

export function useIbMensagens(params?: ib.ListMensagensParams) {
  return useQuery({
    queryKey: ibKeys.mensagens(params),
    queryFn:  () => ib.listMensagens(params),
    staleTime: 15_000,
  });
}

export function useIbMensagem(codigoMensagem: number | null) {
  return useQuery({
    queryKey: ibKeys.mensagem(codigoMensagem!),
    queryFn:  () => ib.getMensagem(codigoMensagem!),
    enabled:  codigoMensagem !== null,
  });
}

export function useIbInsertMensagem() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ib.InsertMensagemPayload) => ib.insertMensagem(payload),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Mensagem enviada para Imobi Brasil');
        qc.invalidateQueries({ queryKey: ['ib', 'mensagens'] });
      } else {
        toast.error(res.error ?? 'Erro ao enviar mensagem');
      }
    },
    onError: () => toast.error('Erro ao enviar mensagem'),
  });
}

export function useIbDeleteMensagem() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoMensagem: number) => ib.deleteMensagem(codigoMensagem),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Mensagem excluída');
        qc.invalidateQueries({ queryKey: ['ib', 'mensagens'] });
      } else {
        toast.error(res.error ?? 'Erro ao excluir mensagem');
      }
    },
    onError: () => toast.error('Erro ao excluir mensagem'),
  });
}

export function useIbMarcarMensagemLida() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoMensagem: number) => ib.marcarMensagemLida(codigoMensagem),
    onSuccess: (res, codigoMensagem) => {
      if (res.success) {
        qc.invalidateQueries({ queryKey: ibKeys.mensagem(codigoMensagem) });
        qc.invalidateQueries({ queryKey: ['ib', 'mensagens'] });
      }
    },
  });
}

// ─── Negócios ─────────────────────────────────────────────────────────────────

export function useIbNegocios(params?: ib.ListNegociosParams) {
  return useQuery({
    queryKey: ibKeys.negocios(params),
    queryFn:  () => ib.listNegocios(params),
    staleTime: 30_000,
  });
}

export function useIbNegocio(codigoNegocio: number | null) {
  return useQuery({
    queryKey: ibKeys.negocio(codigoNegocio!),
    queryFn:  () => ib.getNegocio(codigoNegocio!),
    enabled:  codigoNegocio !== null,
  });
}

export function useIbEtapasNegocios() {
  return useQuery({
    queryKey: ibKeys.etapasNegocios,
    queryFn:  ib.listEtapasNegocios,
    staleTime: 300_000,
  });
}

export function useIbInsertNegocio() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ib.InsertNegocioPayload) => ib.insertNegocio(payload),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Negócio criado no Imobi Brasil');
        qc.invalidateQueries({ queryKey: ['ib', 'negocios'] });
      } else {
        toast.error(res.error ?? 'Erro ao criar negócio');
      }
    },
    onError: () => toast.error('Erro ao criar negócio'),
  });
}

export function useIbUpdateNegocio() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ codigoNegocio, payload }: { codigoNegocio: number; payload: ib.InsertNegocioPayload }) =>
      ib.updateNegocio(codigoNegocio, payload),
    onSuccess: (res, vars) => {
      if (res.success) {
        toast.success('Negócio atualizado');
        qc.invalidateQueries({ queryKey: ibKeys.negocio(vars.codigoNegocio) });
        qc.invalidateQueries({ queryKey: ['ib', 'negocios'] });
      } else {
        toast.error(res.error ?? 'Erro ao atualizar negócio');
      }
    },
    onError: () => toast.error('Erro ao atualizar negócio'),
  });
}

export function useIbDeleteNegocio() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (codigoNegocio: number) => ib.deleteNegocio(codigoNegocio),
    onSuccess: (res) => {
      if (res.success) {
        toast.success('Negócio excluído');
        qc.invalidateQueries({ queryKey: ['ib', 'negocios'] });
      } else {
        toast.error(res.error ?? 'Erro ao excluir negócio');
      }
    },
    onError: () => toast.error('Erro ao excluir negócio'),
  });
}

// ─── Corretores ───────────────────────────────────────────────────────────────

export function useIbCorretores(params?: { page?: number; per_page?: number; status?: string }) {
  return useQuery({
    queryKey: ibKeys.corretores(params),
    queryFn:  () => ib.listCorretores(params),
    staleTime: 60_000,
  });
}

export function useIbCorretor(codigoCorretor: number | null) {
  return useQuery({
    queryKey: ibKeys.corretor(codigoCorretor!),
    queryFn:  () => ib.getCorretor(codigoCorretor!),
    enabled:  codigoCorretor !== null,
  });
}

export function useIbImoveisCorretor(codigoCorretor: number | null) {
  return useQuery({
    queryKey: ibKeys.imoveisCorretor(codigoCorretor!),
    queryFn:  () => ib.listImoveisCorretor(codigoCorretor!),
    enabled:  codigoCorretor !== null,
  });
}

// ─── Clientes ─────────────────────────────────────────────────────────────────

export function useIbClientes(params?: { page?: number; per_page?: number; status?: string }) {
  return useQuery({
    queryKey: ibKeys.clientes(params),
    queryFn:  () => ib.listClientes(params),
    staleTime: 60_000,
  });
}

export function useIbCliente(codigoCliente: number | null) {
  return useQuery({
    queryKey: ibKeys.cliente(codigoCliente!),
    queryFn:  () => ib.getCliente(codigoCliente!),
    enabled:  codigoCliente !== null,
  });
}

// ─── Cidades ──────────────────────────────────────────────────────────────────

export function useIbCidades(params?: { page?: number; per_page?: number }) {
  return useQuery({
    queryKey: ibKeys.cidades(params),
    queryFn:  () => ib.listCidades(params),
    staleTime: 600_000, // 10 min — cidades raramente mudam
  });
}

// ─── Usuário Adicional ────────────────────────────────────────────────────────

export function useIbUsuarioAdicional(codigoUsuario: number | null) {
  return useQuery({
    queryKey: ibKeys.usuarioAdicional(codigoUsuario!),
    queryFn:  () => ib.getUsuarioAdicional(codigoUsuario!),
    enabled:  codigoUsuario !== null,
  });
}
