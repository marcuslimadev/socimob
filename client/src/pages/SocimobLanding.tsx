import { useEffect, useState } from "react";
import {
  ArrowRight,
  BarChart3,
  Building2,
  Check,
  CircleDollarSign,
  ClipboardCheck,
  FileSignature,
  Globe,
  KeyRound,
  LineChart,
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
  spotlight?: boolean;
  notes: string;
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
    subtitle: "Para estruturar o comercial e publicar com velocidade.",
    monthlyPrice: 349,
    includedUsers: 2,
    extraUserPrice: 59,
    notes: "Implantação simples para operação comercial enxuta.",
    includedModules: [],
    highlights: [
      "Dashboard, agenda e visão operacional",
      "CRM, leads, chat e gestão de pessoas",
      "Cadastro de imóveis, documentos e portal público",
      "Login por perfil e base pronta para crescer",
    ],
  },
  {
    id: "gestao",
    name: "Gestão",
    subtitle: "Para imobiliárias que já operam venda, locação e pós-venda.",
    monthlyPrice: 749,
    includedUsers: 5,
    extraUserPrice: 49,
    spotlight: true,
    notes: "É o melhor ponto entre operação, financeiro e portal.",
    includedModules: ["locacao_financeiro", "compra_venda", "portal_proprietario"],
    highlights: [
      "Tudo do Básico",
      "Financeiro, contas a pagar/receber e operação de locação",
      "Compra e venda com templates contratuais",
      "Portal do cliente e do proprietário",
    ],
  },
  {
    id: "pro",
    name: "Pró",
    subtitle: "Para times que querem centralizar operação, marketing e automação.",
    monthlyPrice: 1290,
    includedUsers: 10,
    extraUserPrice: 39,
    notes: "Entrega a operação mais completa do SOCIMOB.",
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
      "Propaganda, anúncios e analytics",
      "Integrações, automações e operação assistida por IA/WhatsApp",
    ],
  },
];

const modules: ModuleDefinition[] = [
  {
    id: "locacao_financeiro",
    name: "Locação & Financeiro",
    monthlyPrice: 249,
    description: "Controle financeiro e administrativo da carteira recorrente.",
    items: [
      "Financeiro geral e contas a pagar/receber",
      "Operação de locação",
      "Cobranças, repasses e rotinas da carteira",
    ],
  },
  {
    id: "compra_venda",
    name: "Compra & Venda",
    monthlyPrice: 179,
    description: "Fluxo dedicado para negócios de compra e venda.",
    items: [
      "Gestão de compra e venda",
      "Templates de contrato",
      "Acompanhamento comercial e documental",
    ],
  },
  {
    id: "vistorias_assinaturas",
    name: "Vistorias & Assinaturas",
    monthlyPrice: 149,
    description: "Formalização e operação de campo no mesmo sistema.",
    items: [
      "Vistorias, solicitações e contestações",
      "Assinaturas eletrônicas",
      "Controle de chaves",
    ],
  },
  {
    id: "marketing_ads",
    name: "Marketing & Anúncios",
    monthlyPrice: 199,
    description: "Distribuição e gestão de captação com mídia e acompanhamento.",
    items: [
      "Propaganda de imóveis",
      "Ads automation e leads captados por anúncios",
      "Analytics do funil e do portal",
    ],
  },
  {
    id: "portal_proprietario",
    name: "Portais de Relacionamento",
    monthlyPrice: 129,
    description: "Acesso para cliente e proprietário sem depender do time interno.",
    items: [
      "Portal público com catálogo",
      "Portal financeiro do cliente",
      "Portal do proprietário com contratos, repasses e cobranças",
    ],
  },
  {
    id: "integracoes_automacoes",
    name: "Integrações & Automações",
    monthlyPrice: 249,
    description: "Conecta operação, canais e parceiros em um fluxo só.",
    items: [
      "WhatsApp, atendimento automático e IA",
      "ImobiBrasil e integrações de publicação",
      "Configurações avançadas e automações sob medida",
    ],
  },
];

const solutionPillars = [
  {
    icon: <Users size={18} />,
    title: "CRM e atendimento",
    description: "Captação, pré-atendimento, follow-up e agenda comercial sem perder lead no caminho.",
  },
  {
    icon: <Building2 size={18} />,
    title: "Imóveis e portal",
    description: "Cadastro, catálogo, documentos e experiência pública prontos para gerar mais oportunidades.",
  },
  {
    icon: <Wallet size={18} />,
    title: "Financeiro imobiliário",
    description: "Locação, repasses, contas e visão financeira centralizada para a operação rodar sem ruído.",
  },
  {
    icon: <ClipboardCheck size={18} />,
    title: "Operação e compliance",
    description: "Vistorias, contratos, assinaturas e chaves no mesmo fluxo, sem retrabalho manual.",
  },
  {
    icon: <Zap size={18} />,
    title: "Anúncios e automação",
    description: "Propaganda, automação, analytics e campanhas com leitura clara de resultado.",
  },
  {
    icon: <Globe size={18} />,
    title: "Portais e relacionamento",
    description: "Cliente, proprietário e time interno acessam a mesma operação com contexto e transparência.",
  },
];

const proofPoints = [
  "CRM, portal, financeiro e operação no mesmo produto",
  "Planos simples para acelerar decisão comercial",
  "Módulos para crescer sem trocar de sistema",
];

const conversionBlocks = [
  {
    title: "Pare de costurar ferramentas",
    description:
      "O SOCIMOB substitui a bagunça de CRM separado, portal isolado, financeiro paralelo e processos manuais dispersos.",
  },
  {
    title: "Venda com clareza comercial",
    description:
      "A landing mostra preço base, usuários e módulos de forma simples para acelerar decisão e reduzir atrito na proposta.",
  },
  {
    title: "Cresça sem trocar a base",
    description:
      "Você começa no tamanho certo e adiciona locação, compra e venda, vistorias, marketing ou automações quando fizer sentido.",
  },
];

const commercialHighlights = [
  "Plano inicial a partir de R$ 349/mês",
  "Implantação e desenho comercial sob consulta",
  "Proposta rápida por WhatsApp com simulação pronta",
];

const premiumSignals = [
  "Imagem mais profissional para a sua operação",
  "Mais clareza comercial na hora de vender o sistema",
  "Escala sem remendo entre ferramentas",
];

const conciseBenefits = [
  "Atendimento e CRM",
  "Portal e imóveis",
  "Locação e financeiro",
  "Anúncios e automações",
];

const heroCapabilities = [
  "CRM completo com funil, leads, agenda e atendimento",
  "Contas a pagar e a receber, repasses e financeiro imobiliário",
  "Emissão de nota fiscal, contratos de aluguel e compra e venda",
  "Atendimento com IA, WhatsApp, portais e integrações sob medida",
];

const heroIntegrationCopy =
  "Personalizamos o SOCIMOB com todas as integrações que o negócio precisa, inclusive emissão de nota fiscal, geração de contrato de aluguel e compra e venda, contas a pagar e a receber, CRM completo, atendimento IA e muito mais.";

const trustBlocks = [
  {
    eyebrow: "Percepção de valor",
    title: "Oferta apresentada com clareza executiva",
    description:
      "Planos, módulos e estimativa mensal aparecem de forma objetiva para reduzir atrito, comparação confusa e negociação desalinhada.",
  },
  {
    eyebrow: "Aderência operacional",
    title: "Pensado para rotina imobiliária real",
    description:
      "A proposta conversa com operação comercial, locação, financeiro, contratos, vistorias, portais e anúncios no mesmo ambiente.",
  },
  {
    eyebrow: "Crescimento sustentável",
    title: "Você não precisa trocar de base ao crescer",
    description:
      "A estrutura modular ajuda a começar com foco e expandir o sistema quando a operação exigir mais profundidade.",
  },
];

const faqItems = [
  {
    question: "Preciso contratar tudo de uma vez?",
    answer:
      "Não. A lógica da página já comunica uma entrada mais simples, com evolução por módulos conforme sua operação amadurece ou ganha novas frentes.",
  },
  {
    question: "Como funciona a implantação?",
    answer:
      "Implantação, migração de dados e integrações especiais entram na avaliação comercial. A ideia é desenhar uma proposta aderente ao seu cenário real, não empurrar um pacote genérico.",
  },
  {
    question: "Serve para foco em vendas e também para locação?",
    answer:
      "Sim. A base cobre o comercial e os módulos permitem acrescentar locação, compra e venda, vistorias, relacionamento com proprietário e automações conforme a estratégia da imobiliária.",
  },
  {
    question: "Posso usar como argumento comercial com a minha equipe ou sócio?",
    answer:
      "Sim. A calculadora e a apresentação por blocos ajudam a justificar investimento, comparar cenários e levar uma conversa mais racional para decisão interna.",
  },
];

const getWhatsappUrl = (message: string) =>
  `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(message)}`;

export default function SocimobLanding() {
  const [selectedPlanId, setSelectedPlanId] = useState<PlanId>("gestao");
  const [userCount, setUserCount] = useState(5);
  const [selectedModules, setSelectedModules] = useState<ModuleId[]>([
    "vistorias_assinaturas",
    "marketing_ads",
  ]);

  useEffect(() => {
    document.title = "SOCIMOB | Sistema imobiliário com CRM, financeiro, portal e operação";

    const description = document.querySelector("meta[name='description']") || document.createElement("meta");
    description.setAttribute("name", "description");
    description.setAttribute(
      "content",
      "Sistema imobiliário com CRM, imóveis, portal, financeiro, contratos, vistorias, anúncios e automações em planos modulares."
    );

    if (!description.parentNode) {
      document.head.appendChild(description);
    }

    delete document.body.dataset.sidebar;
    delete document.body.dataset.sectionTabs;
  }, []);

  const selectedPlan = plans.find((plan) => plan.id === selectedPlanId) || plans[1];
  const includedModules = new Set(selectedPlan.includedModules);
  const billableModuleTotal = modules.reduce((sum, moduleItem) => {
    if (!selectedModules.includes(moduleItem.id) || includedModules.has(moduleItem.id)) {
      return sum;
    }

    return sum + moduleItem.monthlyPrice;
  }, 0);
  const extraUsers = Math.max(0, userCount - selectedPlan.includedUsers);
  const extraUsersTotal = extraUsers * selectedPlan.extraUserPrice;
  const monthlyTotal = selectedPlan.monthlyPrice + billableModuleTotal + extraUsersTotal;

  const toggleModule = (moduleId: ModuleId) => {
    setSelectedModules((current) =>
      current.includes(moduleId) ? current.filter((item) => item !== moduleId) : [...current, moduleId]
    );
  };

  const calculatorMessage = [
    `Olá! Quero uma proposta do SOCIMOB.`,
    `Plano: ${selectedPlan.name}`,
    `Usuários: ${userCount}`,
    `Módulos extras: ${
      modules
        .filter((moduleItem) => selectedModules.includes(moduleItem.id) && !includedModules.has(moduleItem.id))
        .map((moduleItem) => moduleItem.name)
        .join(", ") || "nenhum"
    }`,
    `Estimativa mensal: R$ ${monthlyTotal.toLocaleString("pt-BR")}`,
  ].join(" ");

  return (
    <div className="min-h-screen overflow-x-hidden bg-[#f3f4f6] text-slate-950">
      <div className="absolute inset-x-0 top-0 -z-10 h-[760px] bg-[radial-gradient(circle_at_top_left,_rgba(148,163,184,0.16),_transparent_32%),radial-gradient(circle_at_top_right,_rgba(71,85,105,0.14),_transparent_28%),linear-gradient(180deg,_#e5e7eb_0%,_#f1f5f9_42%,_#f3f4f6_100%)]" />

      <header className="sticky top-0 z-30 border-b border-white/10 bg-[#111827]/92 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <a href="/" className="flex items-center gap-3 text-white">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/20 bg-white/12 shadow-[0_18px_36px_rgba(2,6,23,0.3)] ring-1 ring-white/10">
              <Sparkles size={18} />
            </div>
            <div>
              <p className="text-[12px] font-semibold uppercase tracking-[0.32em] text-white">SOCIMOB</p>
              <p className="text-sm font-medium text-slate-300">Sistema imobiliário modular</p>
            </div>
          </a>

          <nav className="hidden items-center gap-6 text-sm text-white/78 md:flex">
            <a href="#modulos" className="hover:text-white">Módulos</a>
            <a href="#planos" className="hover:text-white">Planos</a>
            <a href="#calculadora" className="hover:text-white">Simulação</a>
            <a href="#contato" className="hover:text-white">Contato</a>
          </nav>

          <div className="flex items-center gap-3">
            <a
              href={appUrl}
              className="hidden rounded-full border border-white/15 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 sm:inline-flex"
            >
              Entrar no app
            </a>
            <a
              href={getWhatsappUrl("Olá! Quero apresentar o SOCIMOB para a minha operação.")}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 shadow-[0_14px_28px_rgba(15,23,42,0.16)] transition hover:bg-slate-100"
            >
              Falar no WhatsApp
              <ArrowRight size={16} />
            </a>
          </div>
        </div>
      </header>

      <main>
        <section className="mx-auto max-w-7xl px-4 pb-10 pt-8 sm:px-6 lg:px-8 lg:pb-12 lg:pt-10">
          <div className="grid gap-6 lg:min-h-[calc(100vh-112px)] lg:grid-cols-[0.94fr_1.06fr] lg:grid-rows-[auto_auto]">
            <div className="rounded-[34px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.96)_0%,rgba(255,248,239,0.92)_100%)] p-6 shadow-[0_30px_80px_rgba(41,33,20,0.08)] sm:p-8 lg:p-8">
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-xs uppercase tracking-[0.22em] text-slate-700">
              <ShieldCheck size={15} />
              Landing de venda para entender, comparar e fechar rápido
            </div>

            <h1 className="max-w-3xl text-4xl font-semibold leading-[0.96] tracking-[-0.05em] text-slate-950 sm:text-5xl lg:text-[3.4rem]">
              Os 3 planos, o que está incluso e o próximo passo para contratar o SOCIMOB.
            </h1>

            <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
              Plataforma imobiliária completa para operação comercial, locação, compra e venda, financeiro, atendimento com IA e integrações personalizadas.
            </p>

            <div className="mt-5 flex flex-wrap gap-3">
              {proofPoints.map((item) => (
                <div
                  key={item}
                  className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700"
                >
                  <Check size={14} className="text-slate-700" />
                  <span>{item}</span>
                </div>
              ))}
            </div>

            <div className="mt-6 rounded-[28px] border border-slate-200 bg-white p-5">
              <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Personalização e integrações</p>
              <p className="mt-3 text-sm leading-7 text-slate-700">{heroIntegrationCopy}</p>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              {heroCapabilities.map((item) => (
                <div key={item} className="flex items-start gap-3 rounded-[20px] border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700">
                  <span className="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white">
                    <Check size={12} />
                  </span>
                  <span>{item}</span>
                </div>
              ))}
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <a
                href={getWhatsappUrl("Olá! Quero contratar o SOCIMOB e preciso de uma proposta com os 3 planos e integrações." )}
                className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
              >
                Pedir proposta agora
                <ArrowRight size={16} />
              </a>
              <a
                href={getWhatsappUrl("Olá! Quero uma demonstração do SOCIMOB para a minha imobiliária.")}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-100"
              >
                Falar no WhatsApp
                <MessageSquareMore size={16} />
              </a>
            </div>
            </div>

            <article className="relative overflow-hidden rounded-[34px] border border-white/60 bg-slate-900 p-5 shadow-[0_30px_80px_rgba(6,16,28,0.18)] sm:p-6 lg:row-span-1">
              <div
                className="relative min-h-[420px] overflow-hidden rounded-[28px] bg-[linear-gradient(180deg,rgba(9,27,39,0.12)_0%,rgba(9,27,39,0.64)_100%),linear-gradient(135deg,#2d4f5d_0%,#173241_100%)] bg-cover bg-center lg:min-h-[100%]"
                style={{ backgroundImage: `linear-gradient(180deg, rgba(9,27,39,0.08) 0%, rgba(9,27,39,0.58) 100%), url(${heroDealImage})` }}
              >
                <div className="absolute inset-x-0 bottom-0 flex flex-col gap-4 p-6 text-white sm:p-8">
                  <div className="max-w-md rounded-[24px] border border-white/16 bg-slate-900/72 p-5 backdrop-blur-md">
                    <p className="text-[11px] uppercase tracking-[0.22em] text-slate-300">Fechamento comercial</p>
                    <p className="mt-2 text-3xl font-semibold leading-tight">Do lead ao fechamento, tudo em um só sistema.</p>
                    <p className="mt-3 text-sm leading-6 text-slate-200">
                      CRM completo, atendimento IA, nota fiscal, contratos, contas a pagar e a receber, portais e automações sob medida.
                    </p>
                  </div>

                  <div className="inline-flex w-fit items-end gap-4 rounded-[24px] bg-white px-5 py-4 text-slate-950 shadow-[0_18px_40px_rgba(6,16,28,0.18)]">
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">A partir de</p>
                      <p className="mt-1 text-4xl font-semibold tracking-[-0.05em]">R$ 349</p>
                    </div>
                    <p className="max-w-[180px] text-sm leading-5 text-slate-600">Plano base, usuários extras e módulos conforme a operação.</p>
                  </div>
                </div>
              </div>
            </article>

            <div className="rounded-[30px] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] lg:col-span-2">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                  <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">3 planos na primeira dobra</p>
                  <h2 className="mt-2 text-2xl font-semibold tracking-[-0.04em] text-slate-950">Escolha o tamanho da operação e avance para a proposta.</h2>
                </div>
                <p className="max-w-2xl text-sm leading-6 text-slate-600">
                  Os três planos aparecem aqui para comparação rápida. A personalização entra depois com os módulos e integrações que sua imobiliária precisa.
                </p>
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-3">
                {plans.map((plan) => {
                  const highlighted = Boolean(plan.spotlight);

                  return (
                    <article
                      key={plan.id}
                      className={`rounded-[26px] border p-5 ${
                        highlighted
                          ? "border-slate-700 bg-slate-900 text-white shadow-[0_18px_40px_rgba(15,23,42,0.18)]"
                          : "border-slate-200 bg-slate-50 text-slate-950"
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className={`text-[11px] uppercase tracking-[0.16em] ${highlighted ? "text-cyan-100" : "text-slate-500"}`}>{plan.name}</p>
                          <p className="mt-2 text-4xl font-semibold tracking-[-0.05em]">R$ {plan.monthlyPrice.toLocaleString("pt-BR")}</p>
                          <p className={`mt-1 text-sm ${highlighted ? "text-slate-200" : "text-slate-600"}`}>por mês</p>
                        </div>
                        {highlighted ? (
                          <div className="rounded-full bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white">
                            Mais vendido
                          </div>
                        ) : null}
                      </div>

                      <p className={`mt-4 text-sm leading-6 ${highlighted ? "text-slate-200" : "text-slate-700"}`}>{plan.subtitle}</p>

                        <div className={`mt-4 rounded-[20px] px-4 py-3 ${highlighted ? "bg-white/8" : "bg-white"}`}>
                        <p className="text-sm font-semibold">{plan.includedUsers} usuários inclusos</p>
                        <p className={`mt-1 text-xs ${highlighted ? "text-slate-300" : "text-slate-500"}`}>R$ {plan.extraUserPrice.toLocaleString("pt-BR")} por usuário extra</p>
                      </div>

                      <ul className="mt-4 space-y-2">
                        {plan.highlights.slice(0, 3).map((highlight) => (
                          <li key={highlight} className="flex items-start gap-2 text-sm leading-6">
                            <span className={`mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full ${highlighted ? "bg-white/12 text-slate-200" : "bg-slate-200 text-slate-700"}`}>
                              <Check size={10} />
                            </span>
                            <span className={highlighted ? "text-slate-100" : "text-slate-700"}>{highlight}</span>
                          </li>
                        ))}
                      </ul>

                      <a
                        href={getWhatsappUrl(`Olá! Quero avaliar o plano ${plan.name} do SOCIMOB com integrações personalizadas para a minha operação.`)}
                        target="_blank"
                        rel="noreferrer"
                        className={`mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full px-4 py-3 text-sm font-semibold transition ${
                          highlighted ? "bg-white text-slate-950 hover:bg-slate-100" : "bg-slate-900 text-white hover:bg-slate-800"
                        }`}
                      >
                        Pedir proposta deste plano
                        <ArrowRight size={16} />
                      </a>
                    </article>
                  );
                })}
              </div>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
          <div className="grid gap-5 lg:grid-cols-3">
            {conversionBlocks.map((block) => (
              <article
                key={block.title}
                className="rounded-[30px] border border-[#dcccb6] bg-[linear-gradient(180deg,#fffaf3_0%,#f7efe3_100%)] p-6 shadow-[0_18px_40px_rgba(32,23,6,0.06)]"
              >
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Porque vende</p>
                <h2 className="mt-4 text-2xl font-semibold tracking-[-0.04em] text-slate-950">{block.title}</h2>
                <p className="mt-3 text-sm leading-7 text-slate-600">{block.description}</p>
              </article>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <div className="rounded-[36px] border border-[#d8c6af] bg-[#f8f1e7] p-8 shadow-[0_18px_40px_rgba(32,23,6,0.06)] sm:p-10">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Elementos de confiança</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Uma página que vende melhor porque transmite segurança.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                Sem promessas artificiais. O foco aqui é deixar a oferta mais confiável, mais compreensível e mais fácil de defender comercialmente.
              </p>
            </div>

            <div className="mt-8 grid gap-5 lg:grid-cols-3">
              {trustBlocks.map((block) => (
                <article key={block.title} className="rounded-[28px] border border-[#e5d7c3] bg-white p-6">
                  <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">{block.eyebrow}</p>
                  <h3 className="mt-4 text-2xl font-semibold tracking-[-0.04em] text-slate-950">{block.title}</h3>
                  <p className="mt-3 text-sm leading-7 text-slate-600">{block.description}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="modulos" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Frentes que destravam crescimento</p>
              <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                Tudo que a imobiliária precisa para vender bem e operar melhor.
              </h2>
            </div>
            <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
              Em vez de vender uma lista genérica de funcionalidades, o SOCIMOB organiza o produto pelas frentes que mais
              impactam atendimento, gestão, produtividade e receita.
            </p>
          </div>

          <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {solutionPillars.map((pillar) => (
              <article
                key={pillar.title}
                className="rounded-[30px] border border-[#e2d4c0] bg-[#fffaf3] p-6 shadow-[0_18px_40px_rgba(32,23,6,0.06)]"
              >
                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-slate-100">
                  {pillar.icon}
                </div>
                <h3 className="mt-5 text-2xl font-semibold tracking-[-0.035em] text-slate-950">{pillar.title}</h3>
                <p className="mt-3 text-sm leading-7 text-slate-600">{pillar.description}</p>
              </article>
            ))}
          </div>
        </section>

        <section id="planos" className="border-y border-[#e7dbc8] bg-[#fbf6ee]">
          <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Planos mensais</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Três degraus claros para tirar a venda do campo da dúvida.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                O cliente entende rápido onde entra, o que está incluído e como evolui. Isso encurta negociação e melhora a percepção de valor.
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
                        <div className={`inline-flex rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em] ${
                          highlighted ? "bg-white/10 text-slate-200" : "bg-slate-200 text-slate-700"
                        }`}>
                          {plan.name}
                        </div>
                        <h3 className="mt-4 text-4xl font-semibold tracking-[-0.05em]">
                          R$ {plan.monthlyPrice.toLocaleString("pt-BR")}
                        </h3>
                        <p className={`mt-2 text-sm ${highlighted ? "text-slate-200" : "text-slate-600"}`}>por mês</p>
                      </div>
                      {highlighted ? (
                        <div className="rounded-full border border-white/12 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.16em] text-white">
                          Mais procurado
                        </div>
                      ) : null}
                    </div>

                    <p className={`mt-5 text-sm leading-7 ${highlighted ? "text-slate-200" : "text-slate-600"}`}>
                      {plan.subtitle}
                    </p>

                    <div className={`mt-6 rounded-[24px] p-4 ${highlighted ? "bg-white/7" : "bg-[#f8f2e9]"}`}>
                      <p className={`text-[11px] uppercase tracking-[0.18em] ${highlighted ? "text-cyan-100/80" : "text-slate-500"}`}>
                        Estrutura
                      </p>
                      <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                          <p className="text-2xl font-semibold">{plan.includedUsers}</p>
                          <p className={`text-xs ${highlighted ? "text-slate-300" : "text-slate-600"}`}>usuários inclusos</p>
                        </div>
                        <div>
                          <p className="text-2xl font-semibold">R$ {plan.extraUserPrice.toLocaleString("pt-BR")}</p>
                          <p className={`text-xs ${highlighted ? "text-slate-300" : "text-slate-600"}`}>por usuário extra</p>
                        </div>
                      </div>
                    </div>

                    <ul className="mt-6 space-y-3">
                      {plan.highlights.map((highlight) => (
                        <li key={highlight} className="flex items-start gap-3">
                          <span className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${
                            highlighted ? "bg-white/10 text-slate-200" : "bg-slate-200 text-slate-700"
                          }`}>
                            <Check size={14} />
                          </span>
                          <span className={`text-sm leading-6 ${highlighted ? "text-slate-100" : "text-slate-700"}`}>
                            {highlight}
                          </span>
                        </li>
                      ))}
                    </ul>

                    <p className={`mt-6 text-xs uppercase tracking-[0.14em] ${highlighted ? "text-slate-300" : "text-slate-500"}`}>
                      {plan.notes}
                    </p>

                    <div className={`mt-5 rounded-[22px] border px-4 py-3 text-sm ${highlighted ? "border-white/10 bg-white/7 text-slate-100" : "border-[#eadcc8] bg-[#fcf7ef] text-slate-700"}`}>
                      {plan.id === "basico" && "Indicado para validar processo comercial, organizar imóveis e começar com uma operação mais previsível."}
                      {plan.id === "gestao" && "Melhor equilíbrio entre venda, locação, portal e financeiro para quem já roda operação real."}
                      {plan.id === "pro" && "Pacote para times que querem concentrar comercial, operação, marketing e automações em um ambiente único."}
                    </div>

                    <div className="mt-7 flex flex-col gap-3">
                      <a
                        href={getWhatsappUrl(`Olá! Quero contratar ou avaliar o plano ${plan.name} do SOCIMOB.`)}
                        target="_blank"
                        rel="noreferrer"
                        className={`inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition ${
                          highlighted
                            ? "bg-white text-slate-950 hover:bg-slate-100"
                            : "bg-slate-900 text-white hover:bg-slate-800"
                        }`}
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
                        className={`rounded-full border px-5 py-3 text-sm font-semibold transition ${
                          highlighted
                            ? "border-white/14 text-white hover:bg-white/8"
                            : "border-slate-300 text-slate-700 hover:bg-slate-100"
                        }`}
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

        <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Módulos mensais</p>
              <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Expanda o sistema por prioridade de negócio.
              </h2>
            </div>
            <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
              A proposta fica mais convincente quando o cliente entende exatamente quanto custa ativar cada frente que gera resultado.
            </p>
          </div>

          <div className="mt-10 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
            {modules.map((moduleItem) => (
              <article
                key={moduleItem.id}
                className="rounded-[30px] border border-[#e1d2bf] bg-white p-6 shadow-[0_18px_40px_rgba(32,23,6,0.06)]"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-slate-100">
                    {moduleItem.id === "locacao_financeiro" && <Wallet size={18} />}
                    {moduleItem.id === "compra_venda" && <FileSignature size={18} />}
                    {moduleItem.id === "vistorias_assinaturas" && <ClipboardCheck size={18} />}
                    {moduleItem.id === "marketing_ads" && <Zap size={18} />}
                    {moduleItem.id === "portal_proprietario" && <Globe size={18} />}
                    {moduleItem.id === "integracoes_automacoes" && <KeyRound size={18} />}
                  </div>
                  <div className="rounded-full bg-slate-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700">
                    +R$ {moduleItem.monthlyPrice.toLocaleString("pt-BR")}/mês
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

        <section id="calculadora" className="bg-slate-900 py-16 text-white">
          <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
            <div>
              <p className="text-[11px] uppercase tracking-[0.22em] text-slate-300">Calculadora comercial</p>
              <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-white">
                Faça uma simulação enxuta e leve a conversa direto para o fechamento.
              </h2>
              <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                A estimativa considera plano, usuários e módulos extras. Depois disso, a negociação pode ir ao WhatsApp já com contexto e valor percebido.
              </p>

              <div className="mt-8 rounded-[28px] border border-white/10 bg-white/[0.05] p-6">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">Resumo da simulação</p>
                <div className="mt-5 grid gap-4 sm:grid-cols-3">
                  <div>
                    <p className="text-sm text-slate-300">Plano</p>
                    <p className="mt-1 text-2xl font-semibold">{selectedPlan.name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-slate-300">Usuários</p>
                    <p className="mt-1 text-2xl font-semibold">{userCount}</p>
                  </div>
                  <div>
                    <p className="text-sm text-slate-300">Total estimado</p>
                    <p className="mt-1 text-2xl font-semibold">R$ {monthlyTotal.toLocaleString("pt-BR")}</p>
                  </div>
                </div>

                <div className="mt-6 space-y-3 border-t border-white/10 pt-6 text-sm text-slate-300">
                  <div className="flex items-center justify-between gap-4">
                    <span>Plano base</span>
                    <span>R$ {selectedPlan.monthlyPrice.toLocaleString("pt-BR")}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4">
                    <span>Usuários extras ({extraUsers})</span>
                    <span>R$ {extraUsersTotal.toLocaleString("pt-BR")}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4">
                    <span>Módulos adicionais</span>
                    <span>R$ {billableModuleTotal.toLocaleString("pt-BR")}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4 border-t border-white/10 pt-3 text-base font-semibold text-white">
                    <span>Total mensal estimado</span>
                    <span>R$ {monthlyTotal.toLocaleString("pt-BR")}</span>
                  </div>
                </div>

                <a
                  href={getWhatsappUrl(calculatorMessage)}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100"
                >
                  Enviar simulação e pedir proposta
                  <ArrowRight size={16} />
                </a>
              </div>
            </div>

            <div className="rounded-[34px] border border-white/10 bg-white/[0.05] p-6 sm:p-8">
              <div>
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">1. Escolha o plano</p>
                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                  {plans.map((plan) => (
                    <button
                      key={plan.id}
                      type="button"
                      onClick={() => {
                        setSelectedPlanId(plan.id);
                        setUserCount((current) => Math.max(current, plan.includedUsers));
                      }}
                      className={`rounded-[24px] border px-4 py-4 text-left transition ${
                        selectedPlanId === plan.id
                          ? "border-slate-400 bg-white/10"
                          : "border-white/10 bg-black/10 hover:bg-white/[0.06]"
                      }`}
                    >
                      <p className="text-sm font-semibold">{plan.name}</p>
                      <p className="mt-2 text-2xl font-semibold">R$ {plan.monthlyPrice.toLocaleString("pt-BR")}</p>
                      <p className="mt-1 text-xs text-slate-300">{plan.includedUsers} usuários inclusos</p>
                    </button>
                  ))}
                </div>
              </div>

              <div className="mt-8">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">2. Defina os usuários</p>
                <div className="mt-4 rounded-[24px] border border-white/10 bg-black/10 p-5">
                  <div className="flex items-center justify-between gap-4">
                    <div>
                      <p className="text-lg font-semibold">Equipe ativa</p>
                      <p className="text-sm text-slate-300">
                        Este plano já inclui {selectedPlan.includedUsers} usuário(s). Extras custam R$ {selectedPlan.extraUserPrice.toLocaleString("pt-BR")} cada.
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => setUserCount((current) => Math.max(1, current - 1))}
                        className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-lg font-semibold transition hover:bg-white/10"
                      >
                        -
                      </button>
                      <div className="min-w-[4.5rem] text-center text-2xl font-semibold">{userCount}</div>
                      <button
                        type="button"
                        onClick={() => setUserCount((current) => current + 1)}
                        className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-lg font-semibold transition hover:bg-white/10"
                      >
                        +
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div className="mt-8">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">3. Ligue os módulos adicionais</p>
                <div className="mt-4 grid gap-4">
                  {modules.map((moduleItem) => {
                    const included = includedModules.has(moduleItem.id);
                    const selected = included || selectedModules.includes(moduleItem.id);

                    return (
                      <button
                        key={moduleItem.id}
                        type="button"
                        disabled={included}
                        onClick={() => toggleModule(moduleItem.id)}
                        className={`rounded-[24px] border p-5 text-left transition ${
                          selected
                            ? "border-slate-400 bg-white/10"
                            : "border-white/10 bg-black/10 hover:bg-white/[0.06]"
                        } ${included ? "cursor-default" : ""}`}
                      >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                          <div>
                            <div className="flex items-center gap-3">
                              <h3 className="text-lg font-semibold">{moduleItem.name}</h3>
                              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] ${
                                included
                                  ? "bg-emerald-400/16 text-emerald-100"
                                  : selected
                                    ? "bg-white text-slate-950"
                                    : "bg-white/10 text-slate-200"
                              }`}>
                                {included ? "Incluído" : selected ? "Selecionado" : "Opcional"}
                              </span>
                            </div>
                            <p className="mt-2 text-sm leading-6 text-slate-300">{moduleItem.description}</p>
                          </div>
                          <div className="text-right">
                            <p className="text-xl font-semibold">
                              {included ? "R$ 0" : `+R$ ${moduleItem.monthlyPrice.toLocaleString("pt-BR")}`}
                            </p>
                            <p className="text-xs text-slate-300">{included ? "já dentro do plano" : "por mês"}</p>
                          </div>
                        </div>
                      </button>
                    );
                  })}
                </div>
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
                  Respostas que ajudam a decisão avançar.
                </h2>
              </div>
              <p className="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                Essa seção reduz dúvida comercial e ajuda a transformar curiosidade em conversa séria de proposta.
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
          <div className="rounded-[36px] border border-[#e1d2bf] bg-[linear-gradient(135deg,#fff7ea_0%,#f7efe4_55%,#f0e7da_100%)] p-8 shadow-[0_22px_60px_rgba(32,23,6,0.08)] sm:p-10">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
              <div>
                <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Contato direto</p>
                <h2 className="mt-3 text-4xl font-semibold tracking-[-0.04em] text-slate-950">
                  Quando a oferta encaixa, o fechamento fica mais próximo.
                </h2>
                <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                  Me chame no WhatsApp e eu ajusto a proposta conforme sua operação, quantidade de usuários, módulos,
                  implantação e integrações específicas. O objetivo é sair com um desenho comercial claro, não com dúvida.
                </p>
              </div>

              <div className="rounded-[30px] bg-slate-900 p-6 text-white shadow-[0_22px_48px_rgba(15,23,42,0.24)]">
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-300">Canal principal</p>
                <p className="mt-3 text-3xl font-semibold tracking-[-0.04em]">wa.me/{whatsappPhone}</p>
                <p className="mt-3 text-sm leading-7 text-slate-300">
                  Atendimento comercial para demonstração, proposta, implantação e definição do pacote ideal.
                </p>

                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                  <a
                    href={getWhatsappUrl("Olá! Quero conversar sobre os planos do SOCIMOB.")}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100"
                  >
                    Pedir proposta no WhatsApp
                    <ArrowRight size={16} />
                  </a>
                  <a
                    href={appUrl}
                    className="inline-flex items-center justify-center gap-2 rounded-full border border-white/12 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/8"
                  >
                    Entrar no app
                    <ArrowRight size={16} />
                  </a>
                </div>

                <p className="mt-5 text-xs uppercase tracking-[0.14em] text-slate-400">
                  Valores mensais. Implantação e projetos especiais sob consulta.
                </p>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}
