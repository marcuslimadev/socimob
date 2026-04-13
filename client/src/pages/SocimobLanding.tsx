import { useEffect, useState } from "react";
import {
  ArrowRight,
  BarChart3,
  Building2,
  Check,
  ClipboardCheck,
  FileSignature,
  Globe,
  KeyRound,
  MessageSquareMore,
  ShieldCheck,
  Sparkles,
  Users,
  Wallet,
  Zap,
} from "lucide-react";

type PlanId = "basico" | "gestao" | "pro";
type ModuleId =
  | "locacao_financeiro"
  | "compra_venda"
  | "vistorias_assinaturas"
  | "marketing_ads"
  | "portal_proprietario"
  | "integracoes_automacoes";

interface PlanDefinition {
  id: PlanId;
  name: string;
  subtitle: string;
  monthlyPrice: number;
  includedUsers: number;
  extraUserPrice: number;
  idealFor: string;
  spotlight?: boolean;
  includedModules: ModuleId[];
  highlights: string[];
}

interface ModuleDefinition {
  id: ModuleId;
  name: string;
  monthlyPrice: number;
  description: string;
  items: string[];
}

const appUrl = "https://app.socimob.com/login";
const whatsappPhone = "5592992287144";
const heroDealImage = "/images/deal.png";

const plans: PlanDefinition[] = [
  {
    id: "basico",
    name: "Básico",
    subtitle: "Entrada enxuta para estruturar atendimento, imóveis e operação comercial.",
    monthlyPrice: 349,
    includedUsers: 2,
    extraUserPrice: 59,
    idealFor: "Imobiliárias que precisam sair do improviso e consolidar o comercial.",
    includedModules: [],
    highlights: [
      "CRM com leads, agenda e atendimento",
      "Cadastro de imóveis, documentos e portal público",
      "Base pronta para crescer por módulos",
    ],
  },
  {
    id: "gestao",
    name: "Gestão",
    subtitle: "Equilíbrio entre venda, locação, portal e financeiro imobiliário.",
    monthlyPrice: 749,
    includedUsers: 5,
    extraUserPrice: 49,
    spotlight: true,
    idealFor: "Operações que já precisam de carteira, financeiro e relacionamento.",
    includedModules: ["locacao_financeiro", "compra_venda", "portal_proprietario"],
    highlights: [
      "Tudo do Básico",
      "Locação, contas, repasses e rotina financeira",
      "Compra e venda com fluxo contratual e operação unificada",
    ],
  },
  {
    id: "pro",
    name: "Pró",
    subtitle: "Estrutura completa para centralizar operação, marketing e automações.",
    monthlyPrice: 1290,
    includedUsers: 10,
    extraUserPrice: 39,
    idealFor: "Equipes que querem CRM, operação, marketing e automação em uma base só.",
    includedModules: [
      "locacao_financeiro",
      "compra_venda",
      "vistorias_assinaturas",
      "marketing_ads",
      "portal_proprietario",
      "integracoes_automacoes",
    ],
    highlights: [
      "Tudo do Gestão",
      "Vistorias, assinaturas e controle de chaves",
      "Marketing, analytics, integrações e atendimento com IA",
    ],
  },
];

const modules: ModuleDefinition[] = [
  {
    id: "locacao_financeiro",
    name: "Locação & Financeiro",
    monthlyPrice: 249,
    description: "Controle administrativo e financeiro da carteira recorrente.",
    items: ["Contas a pagar/receber", "Locação, repasses e cobrança", "Rotina da carteira imobiliária"],
  },
  {
    id: "compra_venda",
    name: "Compra & Venda",
    monthlyPrice: 179,
    description: "Fluxo comercial e documental dedicado para compra e venda.",
    items: ["Gestão do negócio", "Templates de contrato", "Processo do lead ao fechamento"],
  },
  {
    id: "vistorias_assinaturas",
    name: "Vistorias & Assinaturas",
    monthlyPrice: 149,
    description: "Formalização, vistoria e execução no mesmo ambiente.",
    items: ["Vistorias e contestações", "Assinaturas eletrônicas", "Controle de chaves"],
  },
  {
    id: "marketing_ads",
    name: "Marketing & Anúncios",
    monthlyPrice: 199,
    description: "Captação, propaganda e leitura de performance.",
    items: ["Propaganda de imóveis", "Automação de leads", "Analytics de funil e campanhas"],
  },
  {
    id: "portal_proprietario",
    name: "Portais de Relacionamento",
    monthlyPrice: 129,
    description: "Cliente e proprietário com acesso direto ao que importa.",
    items: ["Portal público", "Portal financeiro do cliente", "Portal do proprietário"],
  },
  {
    id: "integracoes_automacoes",
    name: "Integrações & Automações",
    monthlyPrice: 249,
    description: "Conecta operação, canais e parceiros sem remendo manual.",
    items: ["WhatsApp e IA", "Integrações especiais", "Automação sob medida"],
  },
];

const moduleIcons: Record<ModuleId, JSX.Element> = {
  locacao_financeiro: <Wallet size={18} />,
  compra_venda: <FileSignature size={18} />,
  vistorias_assinaturas: <ClipboardCheck size={18} />,
  marketing_ads: <Zap size={18} />,
  portal_proprietario: <Globe size={18} />,
  integracoes_automacoes: <KeyRound size={18} />,
};

const pillars = [
  {
    icon: <Users size={18} />,
    title: "CRM e atendimento",
    description: "Leads, funil, agenda e follow-up sem perder contexto.",
  },
  {
    icon: <Building2 size={18} />,
    title: "Imóveis e portal",
    description: "Cadastro, catálogo, documentos e presença digital.",
  },
  {
    icon: <Wallet size={18} />,
    title: "Financeiro imobiliário",
    description: "Locação, cobranças, repasses e visão da carteira.",
  },
  {
    icon: <ClipboardCheck size={18} />,
    title: "Operação e compliance",
    description: "Vistorias, contratos, assinaturas e chaves no mesmo fluxo.",
  },
  {
    icon: <BarChart3 size={18} />,
    title: "Marketing e analytics",
    description: "Campanhas, anúncios e leitura do que gera resultado.",
  },
  {
    icon: <Sparkles size={18} />,
    title: "Automação e escala",
    description: "WhatsApp, IA, integrações e processos sob medida.",
  },
];

const compareRows: Array<{ label: string; moduleId?: ModuleId }> = [
  { label: "CRM, leads, agenda e atendimento" },
  { label: "Cadastro de imóveis e portal público" },
  { label: "Locação e financeiro", moduleId: "locacao_financeiro" },
  { label: "Compra e venda", moduleId: "compra_venda" },
  { label: "Vistorias e assinaturas", moduleId: "vistorias_assinaturas" },
  { label: "Portais do cliente e proprietário", moduleId: "portal_proprietario" },
  { label: "Marketing, anúncios e analytics", moduleId: "marketing_ads" },
  { label: "Integrações, WhatsApp e automações", moduleId: "integracoes_automacoes" },
];

const faqItems = [
  {
    question: "Preciso contratar todos os módulos de uma vez?",
    answer: "Não. Você entra com a base certa e ativa módulos conforme a operação amadurece.",
  },
  {
    question: "Implantação e integração estão inclusas nesses valores?",
    answer: "Os valores desta página são mensais. Implantação, migração e integrações especiais entram na proposta comercial.",
  },
  {
    question: "Serve para venda e para locação?",
    answer: "Sim. A base cobre CRM e imóveis; os módulos expandem compra e venda, locação, financeiro, vistorias e relacionamento.",
  },
  {
    question: "Posso usar esta página para defender a contratação internamente?",
    answer: "Sim. Ela foi organizada para simplificar comparação entre planos, usuários e módulos.",
  },
];

const getWhatsappUrl = (message: string) =>
  `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(message)}`;

const formatCurrency = (value: number) => `R$ ${value.toLocaleString("pt-BR")}`;

export default function SocimobLanding() {
  const [selectedPlanId, setSelectedPlanId] = useState<PlanId>("gestao");
  const [userCount, setUserCount] = useState(5);
  const [selectedModules, setSelectedModules] = useState<ModuleId[]>([
    "vistorias_assinaturas",
    "marketing_ads",
  ]);

  useEffect(() => {
    document.title = "SOCIMOB | Sistema imobiliário com CRM, portal, financeiro e automação";
    const description = document.querySelector("meta[name='description']") || document.createElement("meta");
    description.setAttribute("name", "description");
    description.setAttribute(
      "content",
      "Sistema imobiliário com CRM, imóveis, portal, financeiro, contratos, anúncios, WhatsApp e automações em planos modulares."
    );
    if (!description.parentNode) document.head.appendChild(description);
    delete document.body.dataset.sidebar;
    delete document.body.dataset.sectionTabs;
  }, []);

  const selectedPlan = plans.find((plan) => plan.id === selectedPlanId) || plans[1];
  const includedModules = new Set(selectedPlan.includedModules);
  const extraUsers = Math.max(0, userCount - selectedPlan.includedUsers);
  const extraUsersTotal = extraUsers * selectedPlan.extraUserPrice;
  const billableModuleTotal = modules.reduce((sum, moduleItem) => {
    if (!selectedModules.includes(moduleItem.id) || includedModules.has(moduleItem.id)) return sum;
    return sum + moduleItem.monthlyPrice;
  }, 0);
  const monthlyTotal = selectedPlan.monthlyPrice + extraUsersTotal + billableModuleTotal;
  const selectedExtraModules = modules.filter(
    (moduleItem) => selectedModules.includes(moduleItem.id) && !includedModules.has(moduleItem.id)
  );

  const toggleModule = (moduleId: ModuleId) => {
    setSelectedModules((current) =>
      current.includes(moduleId) ? current.filter((item) => item !== moduleId) : [...current, moduleId]
    );
  };

  const calculatorMessage = [
    "Olá! Quero uma proposta do SOCIMOB.",
    `Plano: ${selectedPlan.name}.`,
    `Usuários: ${userCount}.`,
    `Módulos extras: ${selectedExtraModules.map((item) => item.name).join(", ") || "nenhum"}.`,
    `Estimativa mensal: ${formatCurrency(monthlyTotal)}.`,
  ].join(" ");

  return (
    <div className="min-h-screen overflow-x-hidden bg-[#f4efe7] text-slate-950">
      <div className="absolute inset-x-0 top-0 -z-10 h-[860px] bg-[radial-gradient(circle_at_top_left,_rgba(217,119,6,0.12),_transparent_28%),radial-gradient(circle_at_top_right,_rgba(15,23,42,0.12),_transparent_30%),linear-gradient(180deg,_#fff7ed_0%,_#f8f1e7_38%,_#f4efe7_100%)]" />

      <header className="sticky top-0 z-30 border-b border-white/15 bg-[#0f172a]/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <a href="/" className="flex items-center gap-3 text-white">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/15 bg-white/10">
              <Sparkles size={18} />
            </div>
            <div>
              <p className="text-[12px] font-semibold uppercase tracking-[0.32em] text-white">SOCIMOB</p>
              <p className="text-sm text-slate-300">Sistema imobiliário modular</p>
            </div>
          </a>

          <nav className="hidden items-center gap-6 text-sm text-white/80 md:flex">
            <a href="#planos" className="hover:text-white">Planos</a>
            <a href="#modulos" className="hover:text-white">Módulos</a>
            <a href="#comparativo" className="hover:text-white">Comparativo</a>
            <a href="#calculadora" className="hover:text-white">Simulação</a>
            <a href="#contato" className="hover:text-white">Contato</a>
          </nav>

          <a
            href={getWhatsappUrl("Olá! Quero conhecer melhor os planos do SOCIMOB.")}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-full bg-[#f59e0b] px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-[#fbbf24]"
          >
            Falar no WhatsApp
            <ArrowRight size={16} />
          </a>
        </div>
      </header>

      <main>
        <section className="mx-auto max-w-7xl px-4 pb-12 pt-8 sm:px-6 lg:px-8 lg:pt-10">
          <div className="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div className="rounded-[36px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.96)_0%,rgba(255,248,239,0.92)_100%)] p-6 shadow-[0_30px_80px_rgba(41,33,20,0.08)] sm:p-8 lg:p-10">
              <div className="inline-flex items-center gap-2 rounded-full border border-[#ead9c2] bg-white px-4 py-2 text-xs uppercase tracking-[0.24em] text-slate-700">
                <ShieldCheck size={15} />
                CRM, portal, financeiro e operação em uma base só
              </div>

              <h1 className="mt-6 max-w-4xl text-4xl font-semibold leading-[0.94] tracking-[-0.05em] text-slate-950 sm:text-5xl lg:text-[4.25rem]">
                O sistema imobiliário para vender melhor e crescer sem remendos.
              </h1>

              <p className="mt-5 max-w-3xl text-base leading-8 text-slate-600 sm:text-lg">
                O SOCIMOB reúne atendimento, imóveis, financeiro, locação, compra e venda, marketing, portais,
                integrações e automações em planos claros, com cobrança mensal por base, usuários e módulos.
              </p>

              <div className="mt-6 flex flex-wrap gap-3">
                {["A partir de R$ 349/mês", "Planos com 2, 5 ou 10 usuários", "Módulos avulsos para escalar"].map((item) => (
                  <div
                    key={item}
                    className="inline-flex items-center gap-2 rounded-full border border-[#ead9c2] bg-white px-4 py-2 text-sm text-slate-700"
                  >
                    <Check size={14} className="text-slate-900" />
                    <span>{item}</span>
                  </div>
                ))}
              </div>

              <div className="mt-8 grid gap-3 sm:grid-cols-2">
                {[
                  "CRM completo com leads, agenda, funil e atendimento",
                  "Contas a pagar e receber, repasses e operação imobiliária",
                  "Contratos, vistorias, assinaturas e controle operacional",
                  "Portal, anúncios, WhatsApp, IA e integrações personalizadas",
                ].map((item) => (
                  <div
                    key={item}
                    className="flex items-start gap-3 rounded-[22px] border border-[#eadcc8] bg-[#fcf7ef] px-4 py-4 text-sm leading-6 text-slate-700"
                  >
                    <span className="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white">
                      <Check size={12} />
                    </span>
                    <span>{item}</span>
                  </div>
                ))}
              </div>

              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <a
                  href={getWhatsappUrl("Olá! Quero uma proposta do SOCIMOB para a minha imobiliária.")}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                  Pedir proposta agora
                  <ArrowRight size={16} />
                </a>
                <a
                  href="#planos"
                  className="inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-100"
                >
                  Ver planos e módulos
                  <MessageSquareMore size={16} />
                </a>
              </div>
            </div>

            <div className="grid gap-6">
              <article className="relative overflow-hidden rounded-[36px] border border-white/60 bg-slate-900 p-5 shadow-[0_30px_80px_rgba(6,16,28,0.18)]">
                <div
                  className="relative min-h-[360px] overflow-hidden rounded-[28px] bg-cover bg-center"
                  style={{
                    backgroundImage: `linear-gradient(180deg, rgba(15,23,42,0.12) 0%, rgba(15,23,42,0.72) 100%), url(${heroDealImage})`,
                  }}
                >
                  <div className="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                    <div className="max-w-md rounded-[24px] border border-white/12 bg-slate-900/72 p-5 text-white backdrop-blur-md">
                      <p className="text-[11px] uppercase tracking-[0.22em] text-slate-300">Operação completa</p>
                      <p className="mt-2 text-3xl font-semibold leading-tight">
                        Do primeiro lead ao fechamento e ao pós-venda.
                      </p>
                      <p className="mt-3 text-sm leading-6 text-slate-200">
                        CRM, contratos, financeiro, anúncios, portais, WhatsApp e automações em um mesmo fluxo.
                      </p>
                    </div>
                  </div>
                </div>
              </article>

              <article className="rounded-[34px] border border-[#e3d4bf] bg-[#fffaf3] p-6 shadow-[0_18px_40px_rgba(32,23,6,0.06)]">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Como a cobrança funciona</p>
                <div className="mt-4 grid gap-3">
                  {[
                    "1. Escolha o plano base da operação",
                    "2. Ajuste os usuários ativos da equipe",
                    "3. Ative apenas os módulos que fazem sentido agora",
                  ].map((item) => (
                    <div
                      key={item}
                      className="rounded-[22px] border border-[#eadcc8] bg-white px-4 py-4 text-sm font-semibold text-slate-800"
                    >
                      {item}
                    </div>
                  ))}
                </div>
                <div className="mt-6 rounded-[24px] bg-slate-900 px-5 py-5 text-white">
                  <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">Comece por</p>
                  <p className="mt-2 text-4xl font-semibold tracking-[-0.05em]">R$ 349</p>
                  <p className="mt-2 text-sm leading-6 text-slate-300">
                    Usuários extras e módulos ampliam a estrutura conforme a operação cresce.
                  </p>
                </div>
              </article>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <div className="rounded-[36px] border border-[#d8c6af] bg-[#f8f1e7] p-8 shadow-[0_18px_40px_rgba(32,23,6,0.06)] sm:p-10">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Frentes do produto</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  O SOCIMOB cobre a rotina imobiliária que realmente pesa no dia a dia.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                A oferta foi organizada pelas frentes que mais impactam resultado, controle e produtividade.
              </p>
            </div>
            <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {pillars.map((pillar) => (
                <article key={pillar.title} className="rounded-[28px] border border-[#e5d7c3] bg-white p-6">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-slate-100">
                    {pillar.icon}
                  </div>
                  <h3 className="mt-5 text-2xl font-semibold tracking-[-0.035em] text-slate-950">{pillar.title}</h3>
                  <p className="mt-3 text-sm leading-7 text-slate-600">{pillar.description}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="planos" className="border-y border-[#e7dbc8] bg-[#fbf6ee]">
          <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Planos mensais</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Três pontos de entrada para contratar do jeito certo.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                Cada plano mostra mensalidade, usuários inclusos, valor por usuário extra e a lógica de evolução do produto.
              </p>
            </div>

            <div className="mt-10 grid gap-6 xl:grid-cols-3">
              {plans.map((plan) => {
                const highlighted = Boolean(plan.spotlight);

                return (
                  <article
                    key={plan.id}
                    className={`rounded-[34px] border p-7 ${
                      highlighted
                        ? "border-slate-700 bg-slate-900 text-white shadow-[0_28px_70px_rgba(15,23,42,0.26)]"
                        : "border-slate-200 bg-white text-slate-950 shadow-[0_18px_40px_rgba(15,23,42,0.06)]"
                    }`}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div className={`inline-flex rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${highlighted ? "bg-white/10 text-slate-200" : "bg-slate-200 text-slate-700"}`}>
                          {plan.name}
                        </div>
                        <h3 className="mt-4 text-4xl font-semibold tracking-[-0.05em]">{formatCurrency(plan.monthlyPrice)}</h3>
                        <p className={`mt-2 text-sm ${highlighted ? "text-slate-200" : "text-slate-600"}`}>por mês</p>
                      </div>
                      {highlighted ? (
                        <div className="rounded-full border border-white/12 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.16em] text-white">
                          Mais indicado
                        </div>
                      ) : null}
                    </div>

                    <p className={`mt-5 text-sm leading-7 ${highlighted ? "text-slate-200" : "text-slate-600"}`}>{plan.subtitle}</p>

                    <div className={`mt-6 rounded-[24px] p-4 ${highlighted ? "bg-white/7" : "bg-[#f8f2e9]"}`}>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                          <p className="text-2xl font-semibold">{plan.includedUsers}</p>
                          <p className={`text-xs ${highlighted ? "text-slate-300" : "text-slate-600"}`}>usuários inclusos</p>
                        </div>
                        <div>
                          <p className="text-2xl font-semibold">{formatCurrency(plan.extraUserPrice)}</p>
                          <p className={`text-xs ${highlighted ? "text-slate-300" : "text-slate-600"}`}>por usuário extra</p>
                        </div>
                      </div>
                    </div>

                    <ul className="mt-6 space-y-3">
                      {plan.highlights.map((highlight) => (
                        <li key={highlight} className="flex items-start gap-3">
                          <span className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${highlighted ? "bg-white/10 text-slate-200" : "bg-slate-200 text-slate-700"}`}>
                            <Check size={14} />
                          </span>
                          <span className={`text-sm leading-6 ${highlighted ? "text-slate-100" : "text-slate-700"}`}>{highlight}</span>
                        </li>
                      ))}
                    </ul>

                    <p className={`mt-6 text-sm leading-6 ${highlighted ? "text-slate-200" : "text-slate-700"}`}>{plan.idealFor}</p>

                    <div className="mt-7 flex flex-col gap-3">
                      <a
                        href={getWhatsappUrl(`Olá! Quero avaliar o plano ${plan.name} do SOCIMOB.`)}
                        target="_blank"
                        rel="noreferrer"
                        className={`inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition ${highlighted ? "bg-white text-slate-950 hover:bg-slate-100" : "bg-slate-900 text-white hover:bg-slate-800"}`}
                      >
                        Falar sobre o {plan.name}
                        <ArrowRight size={16} />
                      </a>
                      <button
                        type="button"
                        onClick={() => {
                          setSelectedPlanId(plan.id);
                          setUserCount(plan.includedUsers);
                          document.getElementById("calculadora")?.scrollIntoView({ behavior: "smooth", block: "start" });
                        }}
                        className={`rounded-full border px-5 py-3 text-sm font-semibold transition ${highlighted ? "border-white/14 text-white hover:bg-white/8" : "border-slate-300 text-slate-700 hover:bg-slate-100"}`}
                      >
                        Simular este plano
                      </button>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </section>

        <section id="modulos" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Módulos mensais</p>
              <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                Expanda o sistema pela prioridade de negócio.
              </h2>
            </div>
            <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
              O módulo avulso ajuda a fechar a proposta no tamanho certo: só entra no preço o que realmente gera valor para a operação.
            </p>
          </div>

          <div className="mt-10 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
            {modules.map((moduleItem) => (
              <article key={moduleItem.id} className="rounded-[30px] border border-[#e1d2bf] bg-white p-6 shadow-[0_18px_40px_rgba(32,23,6,0.06)]">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-slate-100">
                    {moduleIcons[moduleItem.id]}
                  </div>
                  <div className="rounded-full bg-slate-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700">
                    +{formatCurrency(moduleItem.monthlyPrice)}/mês
                  </div>
                </div>

                <h3 className="mt-5 text-2xl font-semibold tracking-[-0.035em] text-slate-950">{moduleItem.name}</h3>
                <p className="mt-3 text-sm leading-7 text-slate-600">{moduleItem.description}</p>

                <ul className="mt-5 space-y-3">
                  {moduleItem.items.map((item) => (
                    <li key={item} className="flex items-start gap-3 text-sm leading-6 text-slate-700">
                      <span className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-slate-700" />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </section>

        <section id="comparativo" className="border-y border-[#eadcc8] bg-[#fffaf3]">
          <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Comparativo rápido</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  O que já entra em cada plano.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                A tabela abaixo transforma a conversa comercial em algo objetivo: o cliente entende o que já está dentro da mensalidade e o que pode ativar depois.
              </p>
            </div>

            <div className="mt-10 overflow-hidden rounded-[32px] border border-[#e4d6c2] bg-white shadow-[0_18px_40px_rgba(32,23,6,0.06)]">
              <div className="grid grid-cols-[1.4fr_repeat(3,minmax(0,1fr))] border-b border-[#efe3d2] bg-[#fcf7ef] px-4 py-4 text-sm font-semibold text-slate-700 sm:px-6">
                <div>Frente</div>
                {plans.map((plan) => <div key={plan.id} className="text-center">{plan.name}</div>)}
              </div>

              {compareRows.map((row) => (
                <div key={row.label} className="grid grid-cols-[1.4fr_repeat(3,minmax(0,1fr))] items-center border-b border-[#f4e8d7] px-4 py-4 text-sm sm:px-6">
                  <div className="pr-4 text-slate-700">{row.label}</div>
                  {plans.map((plan) => {
                    const included = row.moduleId ? plan.includedModules.includes(row.moduleId) : true;

                    return (
                      <div key={plan.id} className="flex justify-center">
                        <span className={`inline-flex min-w-[104px] items-center justify-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] ${included ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-500"}`}>
                          {included ? "Incluído" : "Opcional"}
                        </span>
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </div>
        </section>

        <section id="calculadora" className="bg-slate-900 py-16 text-white">
          <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.88fr_1.12fr] lg:px-8">
            <div>
              <p className="text-[11px] uppercase tracking-[0.22em] text-slate-300">Simulação comercial</p>
              <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-white">
                Monte uma estimativa mensal e leve a conversa direto para o WhatsApp.
              </h2>
              <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                A conta considera plano base, usuários extras e módulos ainda não incluídos no plano escolhido.
              </p>

              <div className="mt-8 rounded-[28px] border border-white/10 bg-white/[0.05] p-6">
                <div className="grid gap-4 sm:grid-cols-3">
                  <div><p className="text-sm text-slate-300">Plano</p><p className="mt-1 text-2xl font-semibold">{selectedPlan.name}</p></div>
                  <div><p className="text-sm text-slate-300">Usuários</p><p className="mt-1 text-2xl font-semibold">{userCount}</p></div>
                  <div><p className="text-sm text-slate-300">Total</p><p className="mt-1 text-2xl font-semibold">{formatCurrency(monthlyTotal)}</p></div>
                </div>

                <div className="mt-6 space-y-3 border-t border-white/10 pt-6 text-sm text-slate-300">
                  <div className="flex items-center justify-between gap-4"><span>Plano base</span><span>{formatCurrency(selectedPlan.monthlyPrice)}</span></div>
                  <div className="flex items-center justify-between gap-4"><span>Usuários extras ({extraUsers})</span><span>{formatCurrency(extraUsersTotal)}</span></div>
                  <div className="flex items-center justify-between gap-4"><span>Módulos adicionais</span><span>{formatCurrency(billableModuleTotal)}</span></div>
                  <div className="flex items-center justify-between gap-4 border-t border-white/10 pt-3 text-base font-semibold text-white"><span>Total mensal estimado</span><span>{formatCurrency(monthlyTotal)}</span></div>
                </div>

                <a
                  href={getWhatsappUrl(calculatorMessage)}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-[#f59e0b] px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-[#fbbf24]"
                >
                  Enviar simulação e pedir proposta
                  <ArrowRight size={16} />
                </a>
              </div>
            </div>

            <div className="rounded-[34px] border border-white/10 bg-white/[0.05] p-6 sm:p-8">
              <div className="grid gap-3 sm:grid-cols-3">
                {plans.map((plan) => (
                  <button key={plan.id} type="button" onClick={() => { setSelectedPlanId(plan.id); setUserCount((current) => Math.max(plan.includedUsers, current)); }} className={`rounded-[24px] border px-4 py-4 text-left transition ${selectedPlanId === plan.id ? "border-slate-400 bg-white/10" : "border-white/10 bg-black/10 hover:bg-white/[0.06]"}`}>
                    <p className="text-sm font-semibold">{plan.name}</p>
                    <p className="mt-2 text-2xl font-semibold">{formatCurrency(plan.monthlyPrice)}</p>
                    <p className="mt-1 text-xs text-slate-300">{plan.includedUsers} usuários inclusos</p>
                  </button>
                ))}
              </div>

              <div className="mt-8 rounded-[24px] border border-white/10 bg-black/10 p-5">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <p className="text-lg font-semibold">Equipe ativa</p>
                    <p className="text-sm text-slate-300">
                      Este plano inclui {selectedPlan.includedUsers} usuário(s). Cada extra custa {formatCurrency(selectedPlan.extraUserPrice)}.
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <button type="button" onClick={() => setUserCount((current) => Math.max(1, current - 1))} className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-lg font-semibold transition hover:bg-white/10">-</button>
                    <div className="min-w-[4.5rem] text-center text-2xl font-semibold">{userCount}</div>
                    <button type="button" onClick={() => setUserCount((current) => current + 1)} className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-lg font-semibold transition hover:bg-white/10">+</button>
                  </div>
                </div>
              </div>

              <div className="mt-8 grid gap-4">
                {modules.map((moduleItem) => {
                  const included = includedModules.has(moduleItem.id);
                  const selected = included || selectedModules.includes(moduleItem.id);

                  return (
                    <button key={moduleItem.id} type="button" disabled={included} onClick={() => toggleModule(moduleItem.id)} className={`rounded-[24px] border p-5 text-left transition ${selected ? "border-slate-400 bg-white/10" : "border-white/10 bg-black/10 hover:bg-white/[0.06]"} ${included ? "cursor-default" : ""}`}>
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                          <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-white/10 text-slate-100">{moduleIcons[moduleItem.id]}</div>
                            <h3 className="text-lg font-semibold">{moduleItem.name}</h3>
                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] ${included ? "bg-emerald-400/16 text-emerald-100" : selected ? "bg-white text-slate-950" : "bg-white/10 text-slate-200"}`}>
                              {included ? "Incluído" : selected ? "Selecionado" : "Opcional"}
                            </span>
                          </div>
                          <p className="mt-2 text-sm leading-6 text-slate-300">{moduleItem.description}</p>
                        </div>
                        <div className="text-right">
                          <p className="text-xl font-semibold">{included ? "R$ 0" : `+${formatCurrency(moduleItem.monthlyPrice)}`}</p>
                          <p className="text-xs text-slate-300">{included ? "já dentro do plano" : "por mês"}</p>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </section>

        <section className="border-y border-[#e8dbc7] bg-[#fbf7f0]">
          <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Objeções frequentes</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Perguntas que travam a contratação e já precisam de resposta.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                A página fecha com respostas curtas e comerciais para diminuir ruído na negociação.
              </p>
            </div>

            <div className="mt-10 grid gap-5 lg:grid-cols-2">
              {faqItems.map((item) => (
                <article key={item.question} className="rounded-[30px] border border-[#e1d2bf] bg-white p-6 shadow-[0_18px_40px_rgba(32,23,6,0.05)]">
                  <h3 className="text-xl font-semibold tracking-[-0.03em] text-slate-950">{item.question}</h3>
                  <p className="mt-3 text-sm leading-7 text-slate-600">{item.answer}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="contato" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="rounded-[38px] border border-slate-900 bg-slate-900 p-8 text-white shadow-[0_28px_80px_rgba(15,23,42,0.24)] sm:p-10">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr] lg:items-end">
              <div>
                <p className="text-[11px] uppercase tracking-[0.24em] text-slate-300">Fechamento</p>
                <h2 className="mt-4 max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-white">
                  Se a sua operação precisa de um sistema mais completo, o próximo passo é montar a proposta certa.
                </h2>
                <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                  Me chame no WhatsApp para alinhar número de usuários, módulos, implantação e integrações especiais.
                </p>
              </div>

              <div className="rounded-[28px] border border-white/10 bg-white/[0.05] p-6">
                <div className="space-y-4">
                  <div className="rounded-[22px] border border-white/10 bg-black/10 px-4 py-4">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-slate-300">Contato comercial</p>
                    <p className="mt-2 text-2xl font-semibold">wa.me/{whatsappPhone}</p>
                  </div>
                  <div className="rounded-[22px] border border-white/10 bg-black/10 px-4 py-4">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-slate-300">Acesso ao sistema</p>
                    <p className="mt-2 text-base font-semibold">{appUrl}</p>
                  </div>
                </div>

                <div className="mt-6 flex flex-col gap-3">
                  <a href={getWhatsappUrl("Olá! Quero fechar uma proposta do SOCIMOB para a minha operação.")} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-full bg-[#f59e0b] px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-[#fbbf24]">
                    Falar no WhatsApp agora
                    <ArrowRight size={16} />
                  </a>
                  <a href={appUrl} className="inline-flex items-center justify-center gap-2 rounded-full border border-white/12 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/8">
                    Entrar no app
                    <ArrowRight size={16} />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}
