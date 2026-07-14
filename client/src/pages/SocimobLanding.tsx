import { useEffect, useState, type ReactElement } from "react";
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
import SocimobLeadChatbot from "@/components/SocimobLeadChatbot";

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
const socimobPalette = {
  navy: "#0d2950",
  blue: "#2f6ea8",
  red: "#f1132b",
  yellow: "#f9bf0a",
  black: "#050308",
  white: "#f2f2f2",
  gray: "#8c8c8c",
};

const planPalette: Record<PlanId, { card: string; strip: string; chipBg: string; chipText: string; text: string }> = {
  basico: {
    card: "rgba(47, 110, 168, 0.94)",
    strip: "#f1132b",
    chipBg: "#f9bf0a",
    chipText: "#050308",
    text: "#f2f2f2",
  },
  gestao: {
    card: "rgba(5, 3, 8, 0.96)",
    strip: "#f9bf0a",
    chipBg: "#f1132b",
    chipText: "#f2f2f2",
    text: "#f2f2f2",
  },
  pro: {
    card: "rgba(13, 41, 80, 0.96)",
    strip: "#f1132b",
    chipBg: "#8c8c8c",
    chipText: "#050308",
    text: "#f2f2f2",
  },
};

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

const moduleIcons: Record<ModuleId, ReactElement> = {
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
    question: "Implantação e integrações estão incluídas nesses valores?",
    answer: "Não. Os valores desta página são mensais. Implantação, migração e integrações especiais entram na proposta comercial.",
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
    const title = "SOCIMOB | Sistema imobiliário com CRM e gestão completa";
    const description = "Sistema imobiliário completo com CRM, gestão de imóveis, locação, financeiro, contratos, portais, anúncios, WhatsApp e automações.";
    const canonicalUrl = `https://socimob.com${window.location.pathname === "/" ? "/" : window.location.pathname}`;
    const setMeta = (selector: string, attribute: "name" | "property", key: string, content: string) => {
      const element = document.head.querySelector<HTMLMetaElement>(selector) || document.createElement("meta");
      element.setAttribute(attribute, key);
      element.setAttribute("content", content);
      if (!element.parentNode) document.head.appendChild(element);
    };

    document.title = title;
    setMeta("meta[name='description']", "name", "description", description);
    setMeta("meta[property='og:title']", "property", "og:title", title);
    setMeta("meta[property='og:description']", "property", "og:description", description);
    setMeta("meta[property='og:url']", "property", "og:url", canonicalUrl);
    setMeta("meta[name='twitter:title']", "name", "twitter:title", title);
    setMeta("meta[name='twitter:description']", "name", "twitter:description", description);

    const canonical = document.head.querySelector<HTMLLinkElement>("link[rel='canonical']") || document.createElement("link");
    canonical.setAttribute("rel", "canonical");
    canonical.setAttribute("href", canonicalUrl);
    if (!canonical.parentNode) document.head.appendChild(canonical);

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
    <div
      className="relative min-h-screen overflow-x-hidden text-slate-950"
      style={{
        backgroundImage: `linear-gradient(155deg, rgba(13,41,80,0.92) 0%, rgba(47,110,168,0.86) 52%, rgba(13,41,80,0.94) 100%), url(${heroDealImage})`,
        backgroundSize: "cover",
        backgroundPosition: "center",
      }}
    >
      <div className="absolute inset-x-0 top-0 -z-10 h-[860px] bg-[radial-gradient(circle_at_top_left,_rgba(241,19,43,0.30),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(249,191,10,0.24),_transparent_34%)]" />

      <header className="sticky top-0 z-30 border-b border-white/15 backdrop-blur-xl" style={{ backgroundColor: "rgba(5, 3, 8, 0.88)" }}>
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <a href="/" className="flex items-center gap-3 text-white">
            <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl border border-white/20" style={{ backgroundColor: "rgba(47, 110, 168, 0.4)" }}>
              <img src="/assets/logo-socimob.svg" alt="Logo SOCIMOB" className="h-8 w-8 object-contain" />
            </div>
            <div>
              <p className="text-[12px] font-semibold uppercase tracking-[0.32em] text-white">SOCIMOB</p>
              <p className="text-sm" style={{ color: socimobPalette.gray }}>Sistema imobiliário modular</p>
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
            className="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-950 transition"
            style={{ backgroundColor: socimobPalette.yellow }}
          >
            Falar no WhatsApp
            <ArrowRight size={16} />
          </a>
        </div>
      </header>

      <main>
        <section className="mx-auto max-w-7xl px-4 pb-12 pt-8 sm:px-6 lg:px-8 lg:pt-10">
          <div className="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div className="rounded-[36px] border border-white/70 p-6 shadow-[0_30px_80px_rgba(5,3,8,0.28)] backdrop-blur-2xl sm:p-8 lg:p-10" style={{ backgroundColor: "rgba(242, 242, 242, 0.92)" }}>
              <div className="inline-flex items-center gap-2 rounded-full border bg-white px-4 py-2 text-xs uppercase tracking-[0.24em] text-slate-700" style={{ borderColor: "rgba(47,110,168,0.35)" }}>
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
                    className="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/52 px-4 py-2 text-sm text-slate-800 backdrop-blur-xl"
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
                    className="flex items-start gap-3 rounded-[22px] border border-white/55 bg-white/52 px-4 py-4 text-sm leading-6 text-slate-800 backdrop-blur-xl"
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
                  className="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white transition"
                  style={{ backgroundColor: socimobPalette.red }}
                >
                  Pedir proposta agora
                  <ArrowRight size={16} />
                </a>
                <a
                  href="#planos"
                  className="inline-flex items-center justify-center gap-2 rounded-full border px-6 py-3 text-sm font-semibold transition"
                  style={{ borderColor: "rgba(47,110,168,0.5)", color: socimobPalette.navy, backgroundColor: "rgba(255,255,255,0.6)" }}
                >
                  Ver planos e módulos
                  <MessageSquareMore size={16} />
                </a>
              </div>
            </div>

            <div className="grid gap-6">
              <SocimobLeadChatbot whatsappPhone={whatsappPhone} />

              <article className="rounded-[34px] border border-white/60 bg-white/52 p-6 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-2xl">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Como a cobrança funciona</p>
                <div className="mt-4 grid gap-3">
                  {[
                    "1. Escolha o plano base da operação",
                    "2. Ajuste os usuários ativos da equipe",
                    "3. Ative apenas os módulos que fazem sentido agora",
                  ].map((item) => (
                    <div
                      key={item}
                      className="rounded-[22px] border border-white/60 bg-white/60 px-4 py-4 text-sm font-semibold text-slate-800 backdrop-blur-xl"
                    >
                      {item}
                    </div>
                  ))}
                </div>
                <div className="mt-6 rounded-[24px] border border-white/20 bg-slate-950/72 px-5 py-5 text-white backdrop-blur-xl">
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
          <div className="rounded-[36px] border border-white/60 bg-white/50 p-8 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-2xl sm:p-10">
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
                <article key={pillar.title} className="rounded-[28px] border border-white/60 bg-white/58 p-6 backdrop-blur-xl">
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

        <section id="planos" className="border-y border-white/45 bg-white/26 backdrop-blur-[2px]">
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
                const currentPalette = planPalette[plan.id];

                return (
                  <article
                    key={plan.id}
                    className="relative overflow-hidden rounded-[34px] border border-white/25 p-7 text-white shadow-[0_30px_75px_rgba(5,3,8,0.36)] backdrop-blur-2xl"
                    style={{ backgroundColor: currentPalette.card }}
                  >
                    <div className="absolute inset-x-0 top-0 h-2" style={{ backgroundColor: currentPalette.strip }} />

                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div
                          className="inline-flex rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em]"
                          style={{ backgroundColor: currentPalette.chipBg, color: currentPalette.chipText }}
                        >
                          {plan.name}
                        </div>
                        <h3 className="mt-4 text-4xl font-semibold tracking-[-0.05em]">{formatCurrency(plan.monthlyPrice)}</h3>
                        <p className="mt-2 text-sm" style={{ color: "rgba(242,242,242,0.82)" }}>por mês</p>
                      </div>
                      {highlighted ? (
                        <div className="rounded-full border px-3 py-1 text-[11px] uppercase tracking-[0.16em]" style={{ borderColor: "rgba(242,242,242,0.35)", backgroundColor: "rgba(242,242,242,0.12)", color: socimobPalette.white }}>
                          Mais indicado
                        </div>
                      ) : null}
                    </div>

                    <p className="mt-5 text-sm leading-7" style={{ color: "rgba(242,242,242,0.84)" }}>{plan.subtitle}</p>

                    <div className="mt-6 rounded-[24px] border p-4" style={{ borderColor: "rgba(242,242,242,0.24)", backgroundColor: "rgba(5,3,8,0.22)" }}>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                          <p className="text-2xl font-semibold">{plan.includedUsers}</p>
                          <p className="text-xs" style={{ color: "rgba(242,242,242,0.76)" }}>usuários inclusos</p>
                        </div>
                        <div>
                          <p className="text-2xl font-semibold">{formatCurrency(plan.extraUserPrice)}</p>
                          <p className="text-xs" style={{ color: "rgba(242,242,242,0.76)" }}>por usuário extra</p>
                        </div>
                      </div>
                    </div>

                    <ul className="mt-6 space-y-3">
                      {plan.highlights.map((highlight) => (
                        <li key={highlight} className="flex items-start gap-3">
                          <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" style={{ backgroundColor: "rgba(242,242,242,0.18)", color: currentPalette.text }}>
                            <Check size={14} />
                          </span>
                          <span className="text-sm leading-6" style={{ color: "rgba(242,242,242,0.92)" }}>{highlight}</span>
                        </li>
                      ))}
                    </ul>

                    <p className="mt-6 text-sm leading-6" style={{ color: "rgba(242,242,242,0.88)" }}>{plan.idealFor}</p>

                    <div className="mt-7 flex flex-col gap-3">
                      <a
                        href={getWhatsappUrl(`Olá! Quero avaliar o plano ${plan.name} do SOCIMOB.`)}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition"
                        style={{ backgroundColor: socimobPalette.yellow, color: socimobPalette.black }}
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
                        className="rounded-full border px-5 py-3 text-sm font-semibold transition"
                        style={{ borderColor: "rgba(242,242,242,0.45)", color: socimobPalette.white, backgroundColor: "rgba(242,242,242,0.08)" }}
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
              <article key={moduleItem.id} className="rounded-[30px] border border-white/60 bg-white/56 p-6 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-xl">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl text-slate-100" style={{ backgroundColor: socimobPalette.navy }}>
                    {moduleIcons[moduleItem.id]}
                  </div>
                  <div className="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]" style={{ backgroundColor: socimobPalette.yellow, color: socimobPalette.black }}>
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

        <section id="comparativo" className="border-y border-white/45 bg-white/28 backdrop-blur-[2px]">
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

            <div className="mt-10 overflow-hidden rounded-[32px] border border-white/60 bg-white/56 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-xl">
              <div className="grid grid-cols-[1.4fr_repeat(3,minmax(0,1fr))] border-b border-white/60 bg-white/48 px-4 py-4 text-sm font-semibold text-slate-700 sm:px-6">
                <div>Frente</div>
                {plans.map((plan) => <div key={plan.id} className="text-center">{plan.name}</div>)}
              </div>

              {compareRows.map((row) => (
                <div key={row.label} className="grid grid-cols-[1.4fr_repeat(3,minmax(0,1fr))] items-center border-b border-white/50 px-4 py-4 text-sm sm:px-6">
                  <div className="pr-4 text-slate-700">{row.label}</div>
                  {plans.map((plan) => {
                    const included = row.moduleId ? plan.includedModules.includes(row.moduleId) : true;

                    return (
                      <div key={plan.id} className="flex justify-center">
                        <span
                          className="inline-flex min-w-[104px] items-center justify-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em]"
                          style={included ? { backgroundColor: socimobPalette.yellow, color: socimobPalette.black } : { backgroundColor: socimobPalette.gray, color: socimobPalette.white }}
                        >
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

        <section id="calculadora" className="py-16 text-white" style={{ backgroundColor: socimobPalette.navy }}>
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
                  className="mt-6 inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-slate-950 transition"
                  style={{ backgroundColor: socimobPalette.yellow }}
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

        <section className="border-y border-white/45 bg-white/26 backdrop-blur-[2px]">
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
                <article key={item.question} className="rounded-[30px] border border-white/60 bg-white/56 p-6 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-xl">
                  <h3 className="text-xl font-semibold tracking-[-0.03em] text-slate-950">{item.question}</h3>
                  <p className="mt-3 text-sm leading-7 text-slate-600">{item.answer}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="contato" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="rounded-[38px] border border-white/20 p-8 text-white shadow-[0_28px_80px_rgba(5,3,8,0.40)] backdrop-blur-2xl sm:p-10" style={{ backgroundColor: "rgba(5, 3, 8, 0.82)" }}>
            <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr] lg:items-end">
              <div>
                <p className="text-[11px] uppercase tracking-[0.24em] text-slate-300">Fechamento</p>
                <h2 className="mt-4 max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-white">
                  Se a sua operação precisa de um sistema mais completo, o próximo passo é montar a proposta certa.
                </h2>
                <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                  Me chame no WhatsApp para alinharmos número de usuários, módulos, implantação e integrações especiais.
                </p>
              </div>

              <div className="rounded-[28px] border border-white/18 bg-white/[0.08] p-6 backdrop-blur-xl">
                <div className="space-y-4">
                  <div className="rounded-[22px] border border-white/20 bg-black/24 px-4 py-4 backdrop-blur-md">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-slate-300">Contato comercial</p>
                    <p className="mt-2 text-2xl font-semibold">wa.me/{whatsappPhone}</p>
                  </div>
                  <div className="rounded-[22px] border border-white/20 bg-black/24 px-4 py-4 backdrop-blur-md">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-slate-300">Acesso ao sistema</p>
                    <p className="mt-2 text-base font-semibold">{appUrl}</p>
                  </div>
                </div>

                <div className="mt-6 flex flex-col gap-3">
                  <a href={getWhatsappUrl("Olá! Quero fechar uma proposta do SOCIMOB para a minha operação.")} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-slate-950 transition" style={{ backgroundColor: socimobPalette.yellow }}>
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
