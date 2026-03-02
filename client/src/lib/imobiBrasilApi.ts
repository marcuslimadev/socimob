/**
 * ImobiBrasil API Client
 * Typed wrappers for every /imobi-brasil/* backend route.
 */
import { api } from './api';

// ─── Generic ─────────────────────────────────────────────────────────────────

export interface ImoobiResponse<T = unknown> {
  success: boolean;
  result_set?: T;
  api_response?: unknown;
  error?: string;
}

export interface PaginatedResultSet<T> {
  page: number;
  total_pages: number;
  per_page: number;
  total_items: number;
  total_data: number;
  data: T[];
}

// ─── Conta ────────────────────────────────────────────────────────────────────

export interface AccountStatus {
  statusConta: boolean;
}

export const getAccountStatus = (): Promise<ImoobiResponse<AccountStatus>> =>
  api.get('/imobi-brasil/account/status').then(r => r.data);

// ─── Imóveis ──────────────────────────────────────────────────────────────────

export interface ImovelListItem {
  codigoImovel: number;
  referenciaImovel: string;
  statusImovel: boolean;
  dataInsercaoImovel: string;
  ultimaAtualizacaoImovel: string;
}

export interface ImovelDados {
  codigoImovel: number;
  codigoProprietario: number;
  codigoCorretor: number;
  codigoUsuarioAdicional: number;
  referenciaImovel: string;
  finalidadeImovel: string;
  descricaoTipoImovel: string;
  codigoTipoImovel: string;
  dormitorios: number;
  suites: number;
  banheiros: number;
  salas: number;
  garagem: number;
  acomodacoes: number;
  anoConstrucao: string;
  mobiliado: boolean;
  descricaoImovel: string;
  observacoesImovel: string;
  pontosFortesImovel: string;
  outrasInformacoesImovel: string;
  valorEsperado: number;
  valorIPTU: number;
  valorCondominio: number;
  valorTaxas: number;
  exibirImovel: boolean;
  tarjaImagem: string;
  cadastradoEm: string;
  atualizadoEm: string;
  video: string;
  tourVirtual: string;
  disponibilidade: string;
  caracteristicas: Array<{ nomeGrupo: string; nomeCaracteristica: string }>;
  imagens: Array<{ url: string; destaque: boolean }>;
  endereco: Array<Record<string, unknown>>;
  area: Record<string, unknown>;
  empreendimento: Array<Record<string, unknown>>;
  exportacaoPortais: Array<Record<string, unknown>>;
}

export interface TipoImovel {
  codigoTipoImovel: number;
  descricaoTipoImovel: string;
}

export interface ListImoveisParams {
  page?: number;
  per_page?: number;
  status?: string;
  referencia?: string;
  finalidade?: string;
  codigoCorretor?: number;
  codigoProprietario?: number;
}

export const listImoveis = (params?: ListImoveisParams): Promise<ImoobiResponse<PaginatedResultSet<ImovelListItem>>> =>
  api.get('/imobi-brasil/imoveis', { params }).then(r => r.data);

export const getImovel = (codigoImovel: number): Promise<ImoobiResponse<ImovelDados>> =>
  api.get(`/imobi-brasil/imoveis/${codigoImovel}`).then(r => r.data);

export const deleteImovel = (codigoImovel: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/imoveis/${codigoImovel}`).then(r => r.data);

export const listTiposImovel = (params?: { page?: number; per_page?: number; descricaoTipoImovel?: string }): Promise<ImoobiResponse<PaginatedResultSet<TipoImovel>>> =>
  api.get('/imobi-brasil/imoveis/tipos', { params }).then(r => r.data);

// ─── Imagens ──────────────────────────────────────────────────────────────────

export interface ImovelImagem {
  codigoImagem: number;
  url: string;
  destaque: boolean;
}

export const listImagensImovel = (codigoImovel: number, params?: { page?: number; per_page?: number; status?: string }): Promise<ImoobiResponse<ImovelImagem[]>> =>
  api.get(`/imobi-brasil/imoveis/${codigoImovel}/imagens`, { params }).then(r => r.data);

export const deleteImagemImovel = (codigoImovel: number, codigoImagem: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/imoveis/${codigoImovel}/imagens/${codigoImagem}`).then(r => r.data);

// ─── Características ──────────────────────────────────────────────────────────

export interface Caracteristica {
  codigoCaracteristica: number;
  nomeCaracteristica: string;
  nomeGrupo: string;
}

export const listCaracteristicas = (params?: { page?: number; per_page?: number; nomeGrupo?: string; nomeCaracteristica?: string }): Promise<ImoobiResponse<PaginatedResultSet<Caracteristica>>> =>
  api.get('/imobi-brasil/caracteristicas', { params }).then(r => r.data);

export const insertCaracteristica = (nomeCaracteristica: string, nomeGrupo: string): Promise<ImoobiResponse> =>
  api.post('/imobi-brasil/caracteristicas', { nomeCaracteristica, nomeGrupo }).then(r => r.data);

export const deleteCaracteristica = (codigoCaracteristica: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/caracteristicas/${codigoCaracteristica}`).then(r => r.data);

export const addCaracteristicaToImovel = (codigoImovel: number, codigoCaracteristica: number): Promise<ImoobiResponse> =>
  api.post(`/imobi-brasil/imoveis/${codigoImovel}/caracteristicas/${codigoCaracteristica}`).then(r => r.data);

export const removeCaracteristicaFromImovel = (codigoImovel: number, codigoCaracteristica: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/imoveis/${codigoImovel}/caracteristicas/${codigoCaracteristica}`).then(r => r.data);

// ─── Pessoas ──────────────────────────────────────────────────────────────────

export interface PessoaListItem {
  codigoPessoa: number;
  codigoCorretor: number;
  codigoUsuarioAdicional: number;
  tipoPessoa: 'F' | 'J';
  statusPessoa: boolean;
  nomeResponsavel: string;
  referenciaPessoa: string;
  cpf: number | null;
  cnpj: number | null;
  telefone1: number | null;
  telefone2: number | null;
  telefone3: number | null;
}

export interface PessoaDados extends PessoaListItem {
  observacaoTipoCadastro: string;
  razaoSocial: string;
  nomeFantasia: string;
  rg: number | null;
  profissao: string;
  dataNascimento: string | null;
  imagem: string | null;
  clienteDesde: string | null;
  maisInfo: string;
  creci: number | null;
  origemCaptacao: string;
  ultimaAtualizacao: string;
  cadastradoEm: string;
  endereco: Array<{
    cep: number;
    logradouro: string;
    bairro: string;
    numero: number;
    complemento: string;
    cidade: string;
    estado: string;
  }>;
  tipoCadastro: Array<{
    cliente: boolean;
    corretor: boolean;
    proprietario: boolean;
    locatario: boolean;
    interessado: boolean;
    outros: boolean;
  }>;
  corretorSite: Array<{
    nomeCorretorSite: string;
    descricaoCorretorSite: string;
    exibirCorretorSite: boolean;
    urlCorretorSite: string;
  }>;
}

export interface InsertPessoaPayload {
  tipoPessoa: 'F' | 'J';
  nomeResponsavel: string;
  tipoCadastro?: string;
  codigoUsuarioAdicional?: number;
  codigoCorretor?: number;
  status?: 'ativo' | 'inativo';
  referencia?: string;
  razaoSocial?: string;
  cnpj?: string;
  nomeFantasia?: string;
  cpf?: string;
  rg?: string;
  profissao?: string;
  email?: string;
  telefone1?: string;
  telefone2?: string;
  telefone3?: string;
  dataNascimento?: string;
  logradouro?: string;
  bairro?: string;
  complemento?: string;
  cidade?: string;
  estado?: string;
  cep?: string;
  numero?: string;
  maisInfo?: string;
  creci?: string;
  origemCaptacao?: string;
  [key: string]: unknown;
}

export interface ListPessoasParams {
  page?: number;
  per_page?: number;
  status?: string;
  tipoPessoa?: string;
  nomeResponsavel?: string;
  tipoCadastro?: string;
}

export const listPessoas = (params?: ListPessoasParams): Promise<ImoobiResponse<PaginatedResultSet<PessoaListItem>>> =>
  api.get('/imobi-brasil/pessoas', { params }).then(r => r.data);

export const getPessoa = (codigoPessoa: number): Promise<ImoobiResponse<PessoaDados>> =>
  api.get(`/imobi-brasil/pessoas/${codigoPessoa}`).then(r => r.data);

export const insertPessoa = (payload: InsertPessoaPayload): Promise<ImoobiResponse> =>
  api.post('/imobi-brasil/pessoas', payload).then(r => r.data);

export const updatePessoa = (codigoPessoa: number, payload: InsertPessoaPayload): Promise<ImoobiResponse> =>
  api.put(`/imobi-brasil/pessoas/${codigoPessoa}`, payload).then(r => r.data);

export const deletePessoa = (codigoPessoa: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/pessoas/${codigoPessoa}`).then(r => r.data);

export const deletePessoaImage = (codigoPessoa: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/pessoas/${codigoPessoa}/imagem`).then(r => r.data);

// ─── Mensagens ────────────────────────────────────────────────────────────────

export type MensagemTipo = 'PP' | 'L' | 'I' | 'W' | 'C' | 'VL' | 'EE';

export interface MensagemListItem {
  codigoMensagem: number;
  codigoImovel: number;
  dataRecebimentoMensagem: string;
  assunto: string;
}

export interface MensagemDados {
  codigoMensagem: number;
  tipoAcao: string;
  importante: boolean;
  tipoMensagem: string;
  codigoImovel: number;
  dataRecebimentoMensagem: string;
  assunto: string;
  mensagem: string;
  lido: boolean;
  nomeContato: string;
  emailContato: string;
  telefoneContato: string;
}

export interface InsertMensagemPayload {
  tipo: MensagemTipo;
  assunto: string;
  mensagem: string;
  nome: string;
  acao?: 'E' | 'S';
  importante?: 'sim' | 'nao';
  codigoCliente?: number;
  codigoUsuarioAdicional?: number;
  codigoImovel?: number;
  lido?: 'sim' | 'não';
  email?: string;
  cidade?: string;
  estado?: string;
  telefone?: string;
}

export interface ListMensagensParams {
  page?: number;
  per_page?: number;
  lido?: string;
  dataInicio?: string;
  dataFim?: string;
  tipo?: string;
}

export const listMensagens = (params?: ListMensagensParams): Promise<ImoobiResponse<PaginatedResultSet<MensagemListItem>>> =>
  api.get('/imobi-brasil/mensagens', { params }).then(r => r.data);

export const getMensagem = (codigoMensagem: number): Promise<ImoobiResponse<MensagemDados>> =>
  api.get(`/imobi-brasil/mensagens/${codigoMensagem}`).then(r => r.data);

export const insertMensagem = (payload: InsertMensagemPayload): Promise<ImoobiResponse> =>
  api.post('/imobi-brasil/mensagens', payload).then(r => r.data);

export const deleteMensagem = (codigoMensagem: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/mensagens/${codigoMensagem}`).then(r => r.data);

export const marcarMensagemLida = (codigoMensagem: number): Promise<ImoobiResponse> =>
  api.post(`/imobi-brasil/mensagens/${codigoMensagem}/lido`).then(r => r.data);

// ─── Negócios ─────────────────────────────────────────────────────────────────

export interface NegocioListItem {
  codigoNegocio: number;
  tituloNegocio: string;
  statusNegocio: string;
  dataFechamento: string;
  dataCriacao: string;
  dataAlteracao: string;
}

export interface NegocioDados {
  codigoNegocio: number;
  usuarioCriouNegocio: number;
  codigoCliente: number;
  codigoImovel: number;
  codigoCorretor: number;
  ordemNegocio: number;
  tituloNegocio: string;
  codigoEtapaNegocio: number;
  tituloEtapaNegocio: string;
  probabilidadeNegocio: string;
  statusNegocio: string;
  motivoPerdidoNegocio: string;
  valorGanhoNegocio: number;
  anotacaoNegocio: string;
  descricaoNegocio: string;
  sinalizaoNegocio: boolean;
  operacaoNegocio: string;
  dataPrevistaNegocio: string;
  dataFechamento: string;
  dataCriacao: string;
  dataAlteracao: string;
}

export interface EtapaNegocio {
  codigoEtapa: number;
  usuarioCriouEtapa: number;
  descricaoEtapa: string;
  diasEstagnado: number;
  cadastradoEm: string;
}

export interface InsertNegocioPayload {
  codigoEtapa: number;
  titulo: string;
  status: string;
  codigoCliente?: number;
  sinalizacao?: 'ativo' | 'inativo';
  dataPrevisao?: string;
}

export interface ListNegociosParams {
  page?: number;
  per_page?: number;
  status?: string;
  codigoCorretor?: number;
  codigoCliente?: number;
}

export const listNegocios = (params?: ListNegociosParams): Promise<ImoobiResponse<PaginatedResultSet<NegocioListItem>>> =>
  api.get('/imobi-brasil/negocios', { params }).then(r => r.data);

export const listEtapasNegocios = (): Promise<ImoobiResponse<EtapaNegocio[]>> =>
  api.get('/imobi-brasil/negocios/etapas').then(r => r.data);

export const getNegocio = (codigoNegocio: number): Promise<ImoobiResponse<NegocioDados>> =>
  api.get(`/imobi-brasil/negocios/${codigoNegocio}`).then(r => r.data);

export const insertNegocio = (payload: InsertNegocioPayload): Promise<ImoobiResponse> =>
  api.post('/imobi-brasil/negocios', payload).then(r => r.data);

export const updateNegocio = (codigoNegocio: number, payload: InsertNegocioPayload): Promise<ImoobiResponse> =>
  api.put(`/imobi-brasil/negocios/${codigoNegocio}`, payload).then(r => r.data);

export const deleteNegocio = (codigoNegocio: number): Promise<ImoobiResponse> =>
  api.delete(`/imobi-brasil/negocios/${codigoNegocio}`).then(r => r.data);

// ─── Corretores ───────────────────────────────────────────────────────────────

export interface CorretorListItem {
  codigoCorretor: number;
  tipoPessoa: string;
  nome: string;
  razaoSocial: string;
  nomeFantasia: string;
  statusCorretor: boolean;
}

export interface CorretorDados extends CorretorListItem {
  creciCorretor: string;
  email: string;
  telefone1: string;
  telefone2: string;
  telefone3: string;
  cpf: string;
  cnpj: string;
  logradouro: string;
  bairro: string;
  numeroResidencia: string;
  cidade: string;
  estado: string;
  cep: string;
  ultimaAtualizacao: string;
  cadastradoEm: string;
  referenciaCorretor: string;
}

export const listCorretores = (params?: { page?: number; per_page?: number; status?: string }): Promise<ImoobiResponse<PaginatedResultSet<CorretorListItem>>> =>
  api.get('/imobi-brasil/corretores', { params }).then(r => r.data);

export const getCorretor = (codigoCorretor: number): Promise<ImoobiResponse<CorretorDados>> =>
  api.get(`/imobi-brasil/corretores/${codigoCorretor}`).then(r => r.data);

export const listImoveisCorretor = (codigoCorretor: number): Promise<ImoobiResponse<unknown>> =>
  api.get(`/imobi-brasil/corretores/${codigoCorretor}/imoveis`).then(r => r.data);

// ─── Clientes ─────────────────────────────────────────────────────────────────

export interface ClienteListItem {
  codigoCliente: number;
  tipoPessoa: string;
  nome: string;
  razaoSocial: string;
  nomeFantasia: string;
  statusCliente: boolean;
}

export interface ClienteDados extends ClienteListItem {
  codigoCorretor: number;
  email: string;
  telefone1: string;
  telefone2: string;
  telefone3: string;
  cpf: string;
  cnpj: string;
  logradouro: string;
  bairro: string;
  numeroResidencia: number;
  cidade: string;
  estado: string;
  cep: number;
  ultimaAtualizacao: string;
  cadastradoEm: string;
  referenciaCliente: number;
}

export const listClientes = (params?: { page?: number; per_page?: number; status?: string }): Promise<ImoobiResponse<PaginatedResultSet<ClienteListItem>>> =>
  api.get('/imobi-brasil/clientes', { params }).then(r => r.data);

export const getCliente = (codigoCliente: number): Promise<ImoobiResponse<ClienteDados>> =>
  api.get(`/imobi-brasil/clientes/${codigoCliente}`).then(r => r.data);

// ─── Cidades ──────────────────────────────────────────────────────────────────

export interface CidadeItem {
  codigoCidade: number;
  nomeCidade: string;
  siglaEstado: string;
  nomeEstado: string;
}

export const listCidades = (params?: { page?: number; per_page?: number }): Promise<ImoobiResponse<PaginatedResultSet<CidadeItem>>> =>
  api.get('/imobi-brasil/cidades', { params }).then(r => r.data);

// ─── Usuário Adicional ────────────────────────────────────────────────────────

export interface UsuarioAdicional {
  codigoUsuarioAdicional: number;
  nomeUsuarioAdicional: string;
  quantidadeAcesso: number;
  ultimoAcesso: string;
  cadastradoEm: string;
}

export const getUsuarioAdicional = (codigoUsuario: number): Promise<ImoobiResponse<UsuarioAdicional>> =>
  api.get(`/imobi-brasil/usuarios-adicionais/${codigoUsuario}`).then(r => r.data);
