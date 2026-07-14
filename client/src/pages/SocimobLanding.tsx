import { useEffect, useMemo, useState, type FormEvent, type ReactElement } from "react";
import {
  ArrowRight,
  BarChart3,
  Building2,
  CalendarDays,
  Check,
  CheckCircle2,
  ClipboardCheck,
  FileSignature,
  KeyRound,
  Menu,
  MessageCircleMore,
  ShieldCheck,
  Sparkles,
  Users,
  Wallet,
  X,
} from "lucide-react";

type PlanId = "basico" | "gestao" | "pro";
type ModuleId =
  | "locacao_financeiro"
  | "compra_venda"
  | "vistorias_assinaturas"
  | "marketing_ads"
  | "portal_proprietario"
  | "integracoes_automacoes";

type Plan = {
  id: PlanId;
  name: string;
  price: number;
  users: number;
  extraUser: number;
  idealFor: string;
  highlights: string[];
  includedModules: ModuleId[];
  featured?: boolean;
};

type ProductArea = {
  eyebrow: string;
  title: string;
  description: string;
  outcome: string;
  icon: ReactElement;
  items: string[];
  preview: "crm" | "properties" | "finance" | "operations";
};

const appUrl = "https://app.socimob.com/login";
const whatsappPhone = "5592992287144";

const plans: Plan[] = [
  {
    id: "basico",
    name: "Básico",
    price: 349,
    users: 2,
    extraUser: 59,
    idealFor: "Para organizar atendimento, leads e imóveis em uma base única.",
    highlights: ["CRM, leads e agenda", "Cadastro de imóveis e documentos", "Portal público imobiliário"],
    includedModules: [],
  },
  {
    id: "gestao",
    name: "Gestão",
    price: 749,
    users: 5,
    extraUser: 49,
    idealFor: "Para operações que também administram carteira, contratos e financeiro.",
    highlights: ["Tudo do Básico", "Locação, cobranças e repasses", "Compra, venda e portais de relacionamento"],
    includedModules: ["locacao_financeiro", "compra_venda", "portal_proprietario"],
    featured: true,
  },
  {
    id: "pro",
    name: "Pró",
    price: 1290,
    users: 10,
    extraUser: 39,
    idealFor: "Para equipes que precisam reunir gestão, operação, marketing e automação.",
    highlights: ["Tudo do Gestão", "Vistorias, assinaturas e chaves", "Marketing, analytics, integrações e automações"],
    includedModules: [
      "locacao_financeiro",
      "compra_venda",
      "vistorias_assinaturas",
      "marketing_ads",
      "portal_proprietario",
      "integracoes_automacoes",
    ],
  },
];

const modules: Array<{ id: ModuleId; name: string; price: number; description: string }> = [
  { id: "locacao_financeiro", name: "Locação & Financeiro", price: 249, description: "Cobranças, repasses, contas e acompanhamento da carteira." },
  { id: "compra_venda", name: "Compra & Venda", price: 179, description: "Negociação, documentação e contratos em um fluxo organizado." },
  { id: "vistorias_assinaturas", name: "Vistorias & Assinaturas", price: 149, description: "Vistorias, contestações, assinaturas e controle de chaves." },
  { id: "marketing_ads", name: "Marketing & Anúncios", price: 199, description: "Divulgação de imóveis, campanhas e leitura de desempenho." },
  { id: "portal_proprietario", name: "Portais de Relacionamento", price: 129, description: "Experiências próprias para clientes e proprietários." },
  { id: "integracoes_automacoes", name: "Integrações & Automações", price: 249, description: "Conexões e rotinas especiais conforme a operação contratada." },
];

const productAreas: ProductArea[] = [
  {
    eyebrow: "CRM e atendimento",
    title: "O histórico do lead acompanha a negociação.",
    description: "Centralize contatos, mensagens, agenda, interesses e etapas comerciais para o corretor saber o próximo passo.",
    outcome: "Menos oportunidade esquecida e mais continuidade no atendimento.",
    icon: <Users size={20} />,
    items: ["Leads e perfis", "Agenda e follow-up", "Atendimento e funil"],
    preview: "crm",
  },
  {
    eyebrow: "Imóveis e portal",
    title: "Cadastre uma vez e trabalhe o imóvel em toda a operação.",
    description: "Organize dados, fotos e documentos e apresente o catálogo em um portal público ligado ao atendimento.",
    outcome: "Imóvel, divulgação e interesse deixam de viver em sistemas separados.",
    icon: <Building2 size={20} />,
    items: ["Cadastro completo", "Galeria e documentos", "Portal por imobiliária"],
    preview: "properties",
  },
  {
    eyebrow: "Locação e financeiro",
    title: "A carteira fica visível do contrato ao repasse.",
    description: "Acompanhe contratos, cobranças, contas, movimentações e rotinas administrativas em um ambiente integrado.",
    outcome: "Mais previsibilidade e menos controle paralelo em planilhas.",
    icon: <Wallet size={20} />,
    items: ["Contas e cobranças", "Locações e repasses", "Visão financeira"],
    preview: "finance",
  },
  {
    eyebrow: "Operação imobiliária",
    title: "Formalização e execução continuam no mesmo fluxo.",
    description: "Conecte contratos, vistorias, assinaturas e controle de chaves às pessoas e aos imóveis envolvidos.",
    outcome: "A equipe encontra contexto e evidências sem procurar em vários lugares.",
    icon: <ClipboardCheck size={20} />,
    items: ["Contratos", "Vistorias e contestações", "Assinaturas e chaves"],
    preview: "operations",
  },
];

const formatCurrency = (value: number) => `R$ ${value.toLocaleString("pt-BR")}`;
const whatsappUrl = (message: string) => `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(message)}`;

function ProductPreview({ type }: { type: ProductArea["preview"] }) {
  if (type === "crm") {
    return (
      <div className="rounded-[28px] border border-slate-200 bg-slate-950 p-4 text-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-white/10 pb-4">
          <div><p className="text-xs text-slate-400">Pipeline comercial</p><p className="mt-1 font-semibold">Negociações em andamento</p></div>
          <span className="rounded-full bg-blue-500/20 px-3 py-1 text-xs text-blue-200">CRM</span>
        </div>
        <div className="mt-4 grid grid-cols-3 gap-2 text-xs">
          {["Novo contato", "Visita", "Proposta"].map((stage, index) => (
            <div key={stage} className="rounded-2xl bg-white/[0.06] p-3">
              <p className="font-semibold text-slate-300">{stage}</p>
              {[0, 1].slice(0, index === 2 ? 1 : 2).map((item) => (
                <div key={item} className="mt-3 rounded-xl border border-white/10 bg-white/[0.08] p-3">
                  <div className="h-2 w-16 rounded bg-white/70" />
                  <div className="mt-2 h-1.5 w-10 rounded bg-white/20" />
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (type === "properties") {
    return (
      <div className="rounded-[28px] border border-slate-200 bg-white p-4 shadow-2xl">
        <div className="flex items-center justify-between"><div><p className="text-xs text-slate-500">Carteira de imóveis</p><p className="mt-1 font-semibold text-slate-950">Catálogo centralizado</p></div><Building2 className="text-blue-700" /></div>
        <div className="mt-4 grid grid-cols-2 gap-3">
          {["Apartamento", "Casa", "Comercial", "Terreno"].map((typeName, index) => (
            <div key={typeName} className="overflow-hidden rounded-2xl border border-slate-200">
              <div className={`h-20 ${index % 2 ? "bg-gradient-to-br from-amber-100 to-slate-300" : "bg-gradient-to-br from-blue-100 to-slate-300"}`} />
              <div className="p-3"><p className="text-xs font-semibold text-slate-900">{typeName}</p><p className="mt-1 text-[10px] text-slate-500">Dados, fotos e atendimento</p></div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (type === "finance") {
    return (
      <div className="rounded-[28px] border border-slate-200 bg-white p-5 shadow-2xl">
        <div className="flex items-center justify-between"><div><p className="text-xs text-slate-500">Gestão financeira</p><p className="mt-1 font-semibold text-slate-950">Visão da operação</p></div><Wallet className="text-emerald-600" /></div>
        <div className="mt-5 grid grid-cols-2 gap-3">
          <div className="rounded-2xl bg-emerald-50 p-4"><p className="text-xs text-emerald-700">Recebimentos</p><div className="mt-3 h-3 w-24 rounded bg-emerald-300" /></div>
          <div className="rounded-2xl bg-amber-50 p-4"><p className="text-xs text-amber-700">Compromissos</p><div className="mt-3 h-3 w-16 rounded bg-amber-300" /></div>
        </div>
        <div className="mt-4 flex h-32 items-end gap-2 rounded-2xl bg-slate-50 p-4">
          {[45, 72, 58, 86, 64, 92, 76].map((height, index) => <div key={index} className="flex-1 rounded-t bg-blue-600/80" style={{ height: `${height}%` }} />)}
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-[28px] border border-slate-200 bg-slate-950 p-5 text-white shadow-2xl">
      <div className="flex items-center justify-between"><div><p className="text-xs text-slate-400">Operação</p><p className="mt-1 font-semibold">Processos conectados</p></div><ClipboardCheck className="text-yellow-300" /></div>
      <div className="mt-5 space-y-3">
        {["Contrato preparado", "Vistoria vinculada", "Assinatura acompanhada", "Chave controlada"].map((item, index) => (
          <div key={item} className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.06] p-3 text-sm">
            <span className={`flex h-7 w-7 items-center justify-center rounded-full ${index < 3 ? "bg-emerald-400 text-slate-950" : "bg-white/10 text-white"}`}>{index < 3 ? <Check size={15} /> : index + 1}</span>
            {item}
          </div>
        ))}
      </div>
    </div>
  );
}

export default function SocimobLanding() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<PlanId>("gestao");
  const [name, setName] = useState("");
  const [company, setCompany] = useState("");
  const [phone, setPhone] = useState("");
  const [teamSize, setTeamSize] = useState("3 a 5 pessoas");

  useEffect(() => {
    const title = "SOCIMOB | Gestão imobiliária completa, do lead ao contrato";
    const description = "CRM imobiliário com gestão de imóveis, locação, financeiro, contratos, vistorias, portais, atendimento e automações em uma plataforma integrada.";
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
    canonical.rel = "canonical";
    canonical.href = canonicalUrl;
    if (!canonical.parentNode) document.head.appendChild(canonical);
    delete document.body.dataset.sidebar;
    delete document.body.dataset.sectionTabs;

    const routeTarget: Record<string, string> = { "/planos": "planos", "/modulos": "solucoes", "/contato": "demonstracao" };
    const target = routeTarget[window.location.pathname];
    if (target) requestAnimationFrame(() => document.getElementById(target)?.scrollIntoView());
  }, []);

  const currentPlan = plans.find((plan) => plan.id === selectedPlan) || plans[1];
  const planModules = useMemo(() => modules.filter((moduleItem) => currentPlan.includedModules.includes(moduleItem.id)), [currentPlan]);

  const submitDemo = (event: FormEvent) => {
    event.preventDefault();
    const message = [
      "Olá! Quero agendar uma demonstração do SOCIMOB.",
      `Nome: ${name || "não informado"}.`,
      `Imobiliária/operação: ${company || "não informada"}.`,
      `Telefone: ${phone || "não informado"}.`,
      `Equipe: ${teamSize}.`,
      `Plano de interesse: ${currentPlan.name}.`,
    ].join(" ");
    window.open(whatsappUrl(message), "_blank", "noopener,noreferrer");
  };

  return (
    <div className="min-h-screen bg-[#f7f8fb] text-slate-950">
      <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <a href="/" className="flex items-center gap-3" aria-label="SOCIMOB - página inicial">
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#0d2950]"><img src="/assets/logo-socimob.svg" alt="" className="h-7 w-7" /></span>
            <div><p className="text-sm font-bold tracking-[0.22em] text-[#0d2950]">SOCIMOB</p><p className="text-xs text-slate-500">Gestão imobiliária integrada</p></div>
          </a>
          <nav className="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex" aria-label="Navegação principal">
            <a href="#produto" className="transition hover:text-[#0d2950]">Produto</a>
            <a href="#solucoes" className="transition hover:text-[#0d2950]">Soluções</a>
            <a href="#implantacao" className="transition hover:text-[#0d2950]">Implantação</a>
            <a href="#planos" className="transition hover:text-[#0d2950]">Planos</a>
            <a href="#duvidas" className="transition hover:text-[#0d2950]">Dúvidas</a>
          </nav>
          <div className="hidden items-center gap-3 sm:flex">
            <a href={appUrl} className="rounded-full px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Entrar</a>
            <a href={whatsappUrl("Olá! Quero conhecer o SOCIMOB e agendar uma demonstração.")} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-full bg-[#f1132b] px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-600/20 transition hover:-translate-y-0.5">Falar direto no WhatsApp <ArrowRight size={16} /></a>
          </div>
          <button type="button" className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 lg:hidden" onClick={() => setMobileMenuOpen((open) => !open)} aria-label="Abrir menu">{mobileMenuOpen ? <X /> : <Menu />}</button>
        </div>
        {mobileMenuOpen && (
          <div className="border-t border-slate-200 bg-white px-4 py-5 lg:hidden">
            <div className="mx-auto grid max-w-7xl gap-3 text-sm font-semibold text-slate-700">
              {[['Produto', '#produto'], ['Soluções', '#solucoes'], ['Implantação', '#implantacao'], ['Planos', '#planos'], ['Dúvidas', '#duvidas']].map(([label, href]) => <a key={href} href={href} onClick={() => setMobileMenuOpen(false)} className="rounded-xl px-3 py-3 hover:bg-slate-50">{label}</a>)}
              <a href="#demonstracao" onClick={() => setMobileMenuOpen(false)} className="mt-2 rounded-xl bg-[#f1132b] px-4 py-3 text-center text-white">Agendar demonstração</a>
            </div>
          </div>
        )}
      </header>

      <main>
        <section className="relative overflow-hidden bg-[#071a33] text-white">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_15%,rgba(47,110,168,.34),transparent_30%),radial-gradient(circle_at_85%_10%,rgba(249,191,10,.16),transparent_25%)]" />
          <div className="relative mx-auto grid max-w-7xl gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[0.94fr_1.06fr] lg:items-center lg:px-8 lg:py-28">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.07] px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-100"><ShieldCheck size={15} /> Plataforma para operações imobiliárias</div>
              <h1 className="mt-7 max-w-3xl text-5xl font-semibold leading-[0.96] tracking-[-0.055em] sm:text-6xl lg:text-7xl">Gestão imobiliária completa, do primeiro contato ao contrato.</h1>
              <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300">O SOCIMOB reúne CRM, imóveis, locação, financeiro, contratos, vistorias, portais, atendimento e automações para sua equipe trabalhar com contexto em uma só plataforma.</p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href={whatsappUrl("Olá! Quero conhecer o SOCIMOB e agendar uma demonstração para minha imobiliária.")} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-full bg-[#f1132b] px-6 py-3.5 font-semibold text-white shadow-xl shadow-red-600/20 transition hover:-translate-y-0.5">Falar direto no WhatsApp <ArrowRight size={17} /></a>
                <a href="#produto" className="inline-flex items-center justify-center gap-2 rounded-full border border-white/20 bg-white/[0.06] px-6 py-3.5 font-semibold text-white transition hover:bg-white/10">Conhecer a plataforma</a>
              </div>
              <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-300">
                {["Planos por tamanho de equipe", "Módulos ativados conforme a operação", "Contato comercial pelo WhatsApp"].map((item) => <span key={item} className="inline-flex items-center gap-2"><CheckCircle2 size={16} className="text-[#f9bf0a]" />{item}</span>)}
              </div>
            </div>

            <div className="relative">
              <div className="absolute -inset-5 rounded-[42px] bg-gradient-to-br from-blue-500/20 via-transparent to-yellow-400/10 blur-2xl" />
              <div className="relative overflow-hidden rounded-[32px] border border-white/15 bg-white p-3 shadow-2xl shadow-black/40">
                <div className="flex items-center justify-between rounded-t-[24px] bg-slate-50 px-5 py-4 text-slate-900">
                  <div className="flex items-center gap-3"><span className="h-3 w-3 rounded-full bg-red-400" /><span className="h-3 w-3 rounded-full bg-yellow-400" /><span className="h-3 w-3 rounded-full bg-emerald-400" /></div>
                  <p className="text-xs font-semibold text-slate-500">Visão integrada da operação</p>
                </div>
                <div className="grid gap-3 bg-[#edf2f8] p-4 sm:grid-cols-[0.32fr_0.68fr]">
                  <div className="rounded-2xl bg-[#0d2950] p-4 text-white">
                    <p className="text-xs font-semibold tracking-[0.16em] text-blue-200">SOCIMOB</p>
                    <div className="mt-5 space-y-2">{["Visão geral", "Atendimento", "Imóveis", "Financeiro", "Agenda"].map((item, index) => <div key={item} className={`rounded-xl px-3 py-2 text-xs ${index === 0 ? "bg-white/15 text-white" : "text-white/65"}`}>{item}</div>)}</div>
                  </div>
                  <div className="space-y-3">
                    <div className="grid grid-cols-3 gap-2">{[["Leads", Users], ["Imóveis", Building2], ["Agenda", CalendarDays]].map(([label, Icon]) => { const IconComponent = Icon as typeof Users; return <div key={label as string} className="rounded-2xl bg-white p-3 text-slate-900 shadow-sm"><IconComponent size={17} className="text-blue-700" /><p className="mt-3 text-xs font-semibold">{label as string}</p><div className="mt-2 h-2 w-10 rounded bg-slate-200" /></div>; })}</div>
                    <div className="rounded-2xl bg-white p-4 shadow-sm"><div className="flex items-center justify-between"><p className="text-xs font-semibold text-slate-900">Acompanhamento comercial</p><BarChart3 size={17} className="text-blue-700" /></div><div className="mt-4 flex h-28 items-end gap-2">{[45, 68, 54, 82, 73, 92, 78, 88].map((height, index) => <div key={index} className="flex-1 rounded-t bg-gradient-to-t from-blue-700 to-blue-400" style={{ height: `${height}%` }} />)}</div></div>
                    <div className="grid grid-cols-2 gap-2"><div className="rounded-2xl bg-white p-4 shadow-sm"><p className="text-xs font-semibold text-slate-900">Próximas tarefas</p><div className="mt-3 space-y-2">{[1, 2, 3].map((item) => <div key={item} className="h-2 rounded bg-slate-200" />)}</div></div><div className="rounded-2xl bg-[#f9bf0a] p-4 text-slate-950"><p className="text-xs font-semibold">Operação conectada</p><p className="mt-3 text-xs leading-5">Pessoas, imóveis e processos no mesmo contexto.</p></div></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="border-b border-slate-200 bg-white">
          <div className="mx-auto grid max-w-7xl gap-5 px-4 py-8 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            {[
              ["CRM e atendimento", "Leads, agenda e histórico"],
              ["Imóveis e portal", "Catálogo e presença digital"],
              ["Gestão e financeiro", "Carteira, cobranças e repasses"],
              ["Operação", "Contratos, vistorias e chaves"],
            ].map(([title, text]) => <div key={title} className="border-l-2 border-[#2f6ea8] pl-4"><p className="font-semibold text-slate-950">{title}</p><p className="mt-1 text-sm text-slate-500">{text}</p></div>)}
          </div>
        </section>

        <section id="produto" className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
          <div className="mx-auto max-w-3xl text-center"><p className="text-xs font-bold uppercase tracking-[0.22em] text-[#2f6ea8]">Produto conectado</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">A informação entra uma vez e continua útil em toda a jornada.</h2><p className="mt-5 text-lg leading-8 text-slate-600">O SOCIMOB foi estruturado para relacionar atendimento, imóvel, pessoa, documento e rotina operacional — sem transformar cada etapa em uma ilha.</p></div>
          <div id="solucoes" className="mt-16 space-y-10">
            {productAreas.map((area, index) => (
              <article key={area.title} className="grid items-center gap-10 rounded-[36px] border border-slate-200 bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,0.07)] sm:p-9 lg:grid-cols-2 lg:p-12">
                <div className={index % 2 ? "lg:order-2" : ""}>
                  <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#0d2950] text-white">{area.icon}</div>
                  <p className="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-[#2f6ea8]">{area.eyebrow}</p>
                  <h3 className="mt-3 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">{area.title}</h3>
                  <p className="mt-5 text-base leading-8 text-slate-600">{area.description}</p>
                  <div className="mt-6 flex flex-wrap gap-2">{area.items.map((item) => <span key={item} className="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">{item}</span>)}</div>
                  <div className="mt-7 flex items-start gap-3 rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-blue-950"><CheckCircle2 className="mt-0.5 shrink-0 text-blue-700" size={18} /><span><strong>Resultado esperado:</strong> {area.outcome}</span></div>
                </div>
                <div className={index % 2 ? "lg:order-1" : ""}><ProductPreview type={area.preview} /></div>
              </article>
            ))}
          </div>
        </section>

        <section className="bg-[#0d2950] py-20 text-white lg:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid gap-12 lg:grid-cols-[0.72fr_1.28fr] lg:items-start">
              <div><p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">Da captação à gestão</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Uma sequência de trabalho, não uma coleção de telas.</h2><p className="mt-5 text-base leading-8 text-slate-300">Cada frente pode ser contratada conforme a necessidade, mas a plataforma preserva o contexto entre as etapas.</p></div>
              <div className="grid gap-4 sm:grid-cols-2">
                {[
                  ["01", "O lead chega", "O contato entra no CRM e fica associado à origem e ao interesse."],
                  ["02", "A equipe atende", "Conversas, agenda e acompanhamento orientam o próximo passo."],
                  ["03", "O imóvel entra no negócio", "Dados, fotos e documentos sustentam apresentação e negociação."],
                  ["04", "A operação continua", "Contratos, financeiro, vistorias e relacionamento mantêm o histórico."],
                ].map(([number, title, text]) => <div key={number} className="rounded-[28px] border border-white/10 bg-white/[0.06] p-6"><span className="text-sm font-bold text-[#f9bf0a]">{number}</span><h3 className="mt-5 text-xl font-semibold">{title}</h3><p className="mt-3 text-sm leading-7 text-slate-300">{text}</p></div>)}
              </div>
            </div>
          </div>
        </section>

        <section id="implantacao" className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
          <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div><p className="text-xs font-bold uppercase tracking-[0.22em] text-[#2f6ea8]">Implantação orientada</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">A contratação começa pelo desenho correto da operação.</h2><p className="mt-5 text-lg leading-8 text-slate-600">O escopo é definido conforme equipe, carteira e módulos necessários. Migração, integrações e configurações especiais são avaliadas na proposta.</p><a href="#demonstracao" className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#0d2950] px-6 py-3.5 font-semibold text-white">Conversar sobre implantação <ArrowRight size={17} /></a></div>
            <div className="grid gap-4 sm:grid-cols-2">
              {[
                [Users, "Diagnóstico", "Entendimento da equipe, dos fluxos e das prioridades."],
                [KeyRound, "Configuração", "Perfis, módulos, portal e estrutura inicial."],
                [FileSignature, "Dados e processos", "Avaliação de cadastros, documentos e migração possível."],
                [Sparkles, "Acompanhamento", "Orientação para colocar a equipe no fluxo contratado."],
              ].map(([Icon, title, text]) => { const IconComponent = Icon as typeof Users; return <div key={title as string} className="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm"><IconComponent className="text-[#2f6ea8]" /><h3 className="mt-5 text-lg font-semibold">{title as string}</h3><p className="mt-2 text-sm leading-7 text-slate-600">{text as string}</p></div>; })}
            </div>
          </div>
        </section>

        <section id="planos" className="border-y border-slate-200 bg-white py-20 lg:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="mx-auto max-w-3xl text-center"><p className="text-xs font-bold uppercase tracking-[0.22em] text-[#2f6ea8]">Planos mensais</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Comece com a estrutura adequada para sua operação.</h2><p className="mt-5 text-lg leading-8 text-slate-600">Usuários extras e módulos adicionais permitem ajustar a contratação. Implantação e integrações especiais são tratadas separadamente.</p></div>
            <div className="mt-14 grid gap-6 lg:grid-cols-3">
              {plans.map((plan) => (
                <article key={plan.id} className={`relative rounded-[32px] border p-7 ${plan.featured ? "border-[#2f6ea8] bg-[#0d2950] text-white shadow-2xl" : "border-slate-200 bg-[#f8fafc] text-slate-950"}`}>
                  {plan.featured && <span className="absolute right-6 top-6 rounded-full bg-[#f9bf0a] px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-slate-950">Mais completo para gestão</span>}
                  <p className={`text-sm font-semibold ${plan.featured ? "text-blue-200" : "text-slate-500"}`}>Plano {plan.name}</p>
                  <div className="mt-6 flex items-end gap-2"><p className="text-4xl font-semibold tracking-[-0.05em]">{formatCurrency(plan.price)}</p><span className={`pb-1 text-sm ${plan.featured ? "text-slate-300" : "text-slate-500"}`}>/mês</span></div>
                  <p className={`mt-4 min-h-20 text-sm leading-7 ${plan.featured ? "text-slate-300" : "text-slate-600"}`}>{plan.idealFor}</p>
                  <div className={`mt-6 rounded-2xl p-4 ${plan.featured ? "bg-white/[0.07]" : "bg-white"}`}><p className="font-semibold">{plan.users} usuários inclusos</p><p className={`mt-1 text-sm ${plan.featured ? "text-slate-300" : "text-slate-500"}`}>{formatCurrency(plan.extraUser)} por usuário extra</p></div>
                  <ul className="mt-6 space-y-3">{plan.highlights.map((item) => <li key={item} className="flex items-start gap-3 text-sm leading-6"><CheckCircle2 size={17} className={`mt-0.5 shrink-0 ${plan.featured ? "text-[#f9bf0a]" : "text-[#2f6ea8]"}`} />{item}</li>)}</ul>
                  <button type="button" onClick={() => { setSelectedPlan(plan.id); document.getElementById("demonstracao")?.scrollIntoView({ behavior: "smooth" }); }} className={`mt-8 w-full rounded-full px-5 py-3 font-semibold ${plan.featured ? "bg-[#f9bf0a] text-slate-950" : "bg-[#0d2950] text-white"}`}>Quero conhecer o {plan.name}</button>
                </article>
              ))}
            </div>
            <div className="mt-10 rounded-[28px] border border-slate-200 bg-slate-50 p-6"><div className="grid gap-5 md:grid-cols-3">{modules.map((moduleItem) => <div key={moduleItem.id}><p className="font-semibold text-slate-950">{moduleItem.name}</p><p className="mt-1 text-sm leading-6 text-slate-600">{moduleItem.description}</p><p className="mt-2 text-sm font-semibold text-[#2f6ea8]">+ {formatCurrency(moduleItem.price)}/mês</p></div>)}</div></div>
          </div>
        </section>

        <section id="demonstracao" className="bg-[#eef3f8] py-20 lg:py-28">
          <div className="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.82fr_1.18fr] lg:items-center lg:px-8">
            <div><p className="text-xs font-bold uppercase tracking-[0.22em] text-[#2f6ea8]">Demonstração comercial</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Veja o SOCIMOB aplicado à realidade da sua imobiliária.</h2><p className="mt-5 text-lg leading-8 text-slate-600">Informe o básico sobre sua operação. A conversa continua pelo WhatsApp para alinhar equipe, módulos, implantação e necessidades especiais.</p><div className="mt-7 space-y-3">{["Sem promessa de módulo não contratado", "Escopo definido antes da implantação", "Plano ajustado ao tamanho da equipe"].map((item) => <p key={item} className="flex items-center gap-3 text-sm font-medium text-slate-700"><CheckCircle2 size={18} className="text-[#2f6ea8]" />{item}</p>)}</div></div>
            <form onSubmit={submitDemo} className="rounded-[34px] border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-900/10 sm:p-9">
              <div className="grid gap-5 sm:grid-cols-2">
                <label className="text-sm font-semibold text-slate-700">Seu nome<input required value={name} onChange={(event) => setName(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Como podemos chamar você?" /></label>
                <label className="text-sm font-semibold text-slate-700">Imobiliária ou operação<input required value={company} onChange={(event) => setCompany(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Nome da empresa" /></label>
                <label className="text-sm font-semibold text-slate-700">WhatsApp com DDD<input required value={phone} onChange={(event) => setPhone(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="(00) 00000-0000" /></label>
                <label className="text-sm font-semibold text-slate-700">Tamanho da equipe<select value={teamSize} onChange={(event) => setTeamSize(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 font-normal outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"><option>1 a 2 pessoas</option><option>3 a 5 pessoas</option><option>6 a 10 pessoas</option><option>Mais de 10 pessoas</option></select></label>
              </div>
              <div className="mt-6 rounded-2xl bg-slate-50 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Interesse selecionado</p><p className="mt-1 font-semibold">Plano {currentPlan.name} · {currentPlan.users} usuários inclusos</p></div><div className="flex flex-wrap gap-2">{planModules.slice(0, 2).map((moduleItem) => <span key={moduleItem.id} className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-900">{moduleItem.name}</span>)}</div></div></div>
              <button type="submit" className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#f1132b] px-6 py-3.5 font-semibold text-white shadow-lg shadow-red-600/20">Enviar direto para meu WhatsApp <MessageCircleMore size={18} /></button>
              <p className="mt-4 text-center text-xs leading-5 text-slate-500">O envio abre uma conversa no WhatsApp com os dados preenchidos. Nenhuma contratação é concluída automaticamente.</p>
            </form>
          </div>
        </section>

        <section id="duvidas" className="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:py-28">
          <div className="text-center"><p className="text-xs font-bold uppercase tracking-[0.22em] text-[#2f6ea8]">Perguntas frequentes</p><h2 className="mt-4 text-4xl font-semibold tracking-[-0.045em]">O que precisa estar claro antes da proposta.</h2></div>
          <div className="mt-12 grid gap-4">
            {[
              ["O SOCIMOB atende venda e locação?", "Sim. A base reúne CRM e imóveis, e os planos e módulos ampliam os fluxos de compra e venda, locação, financeiro e operação."],
              ["Preciso contratar todos os módulos?", "Não. A contratação pode começar com o plano mais adequado e incorporar módulos conforme a necessidade da operação."],
              ["Migração e integrações estão incluídas na mensalidade?", "Não automaticamente. Migração, implantação e integrações especiais dependem de avaliação e entram no escopo comercial quando necessárias."],
              ["Existe teste grátis?", "A página não promete teste automático. O primeiro passo é uma demonstração e o alinhamento do escopo com a equipe comercial."],
              ["Como funciona o suporte?", "O formato de implantação e acompanhamento é apresentado na proposta conforme o plano e a complexidade da operação."],
            ].map(([question, answer]) => <details key={question} className="group rounded-2xl border border-slate-200 bg-white p-5"><summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-950">{question}<span className="text-2xl font-light text-slate-400 group-open:rotate-45">+</span></summary><p className="mt-4 max-w-3xl text-sm leading-7 text-slate-600">{answer}</p></details>)}
          </div>
        </section>

        <section className="bg-[#071a33] py-16 text-white">
          <div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-4 sm:px-6 lg:flex-row lg:items-center lg:px-8"><div><p className="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">Próximo passo</p><h2 className="mt-3 max-w-3xl text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">Organize sua operação imobiliária em uma plataforma que acompanha o trabalho real da equipe.</h2></div><a href={whatsappUrl("Olá! Quero conversar sobre o SOCIMOB para minha operação imobiliária.")} target="_blank" rel="noreferrer" className="inline-flex shrink-0 items-center gap-2 rounded-full bg-[#f9bf0a] px-6 py-3.5 font-semibold text-slate-950">Falar direto no WhatsApp <ArrowRight size={17} /></a></div>
        </section>
      </main>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8"><div><div className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d2950]"><img src="/assets/logo-socimob.svg" alt="" className="h-6 w-6" /></span><p className="font-bold tracking-[0.2em] text-[#0d2950]">SOCIMOB</p></div><p className="mt-4 max-w-xl text-sm leading-7 text-slate-500">Plataforma de gestão imobiliária com módulos para atendimento, imóveis, administração, operação e relacionamento.</p></div><div className="flex flex-wrap gap-5 text-sm font-medium text-slate-600"><a href="#produto">Produto</a><a href="#planos">Planos</a><a href="#demonstracao">Contato</a><a href={appUrl}>Entrar</a></div></div>
      </footer>
    </div>
  );
}
