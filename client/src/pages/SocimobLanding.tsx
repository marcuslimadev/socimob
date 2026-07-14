import { useEffect } from "react";
import {
  ArrowRight,
  Building2,
  CalendarDays,
  Check,
  MessageCircleMore,
  ShieldCheck,
  Users,
  Wallet,
} from "lucide-react";

const appUrl = "https://app.socimob.com/login";
const whatsappPhone = "5592992287144";
const whatsappUrl = `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(
  "Olá! Quero conhecer o SOCIMOB e agendar uma demonstração para minha imobiliária.",
)}`;

export default function SocimobLanding() {
  useEffect(() => {
    const title = "SOCIMOB | Toda a gestão da sua imobiliária em um único sistema";
    const description =
      "Centralize CRM, imóveis, locação, financeiro, contratos, vistorias e atendimento da sua imobiliária no SOCIMOB.";
    const canonicalUrl = "https://socimob.com/";
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
    const canonical = document.head.querySelector<HTMLLinkElement>("link[rel='canonical']") || document.createElement("link");
    canonical.rel = "canonical";
    canonical.href = canonicalUrl;
    if (!canonical.parentNode) document.head.appendChild(canonical);
    delete document.body.dataset.sidebar;
    delete document.body.dataset.sectionTabs;
  }, []);

  return (
    <div className="relative min-h-[100dvh] overflow-hidden bg-[#06162b] text-white">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_78%_35%,rgba(47,110,168,.30),transparent_32%),radial-gradient(circle_at_18%_12%,rgba(249,191,10,.09),transparent_25%)]" />
      <div className="pointer-events-none absolute -right-24 top-20 h-80 w-80 rounded-full border border-white/[0.06]" />
      <div className="pointer-events-none absolute -right-8 top-36 h-52 w-52 rounded-full border border-white/[0.06]" />

      <div className="relative mx-auto flex min-h-[100dvh] max-w-7xl flex-col px-5 sm:px-7 lg:px-10">
        <header className="flex h-20 shrink-0 items-center justify-between lg:h-24">
          <a href="/" className="flex items-center gap-3" aria-label="SOCIMOB - início">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.07]">
              <img src="/assets/logo-socimob.svg" alt="" className="h-6 w-6" />
            </span>
            <div>
              <p className="text-sm font-bold tracking-[0.23em]" style={{ fontFamily: "'Bauhaus Modern', 'Constructium', sans-serif" }}><span className="text-white">SOC</span><span className="text-[#f1132b]">IMOB</span></p>
              <p className="mt-0.5 text-[10px] tracking-wide text-slate-400">GESTÃO IMOBILIÁRIA</p>
            </div>
          </a>

          <div className="flex items-center gap-2 sm:gap-4">
            <a href={appUrl} className="hidden px-3 py-2 text-sm font-medium text-slate-300 transition hover:text-white sm:block">
              Já sou cliente
            </a>
            <a
              href={whatsappUrl}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.07] px-4 py-2.5 text-sm font-semibold transition hover:bg-white/[0.12]"
            >
              <MessageCircleMore size={17} />
              <span className="hidden sm:inline">Falar com especialista</span>
              <span className="sm:hidden">WhatsApp</span>
            </a>
          </div>
        </header>

        <main className="grid flex-1 items-center gap-8 py-7 lg:grid-cols-[0.92fr_1.08fr] lg:gap-14 lg:py-4">
          <section className="relative z-10 max-w-2xl">
            <div className="inline-flex items-center gap-2 rounded-full border border-blue-300/20 bg-blue-300/[0.07] px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.15em] text-blue-100">
              <ShieldCheck size={14} /> Sua operação, conectada
            </div>

            <h1 className="mt-6 text-[clamp(2.65rem,4.5vw,4.45rem)] font-semibold leading-[0.96] tracking-[-0.06em]">
              Toda a gestão<br />da sua imobiliária<br />
              <span className="text-[#f9bf0a]">em um único sistema!</span>
            </h1>

            <p className="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
              Centralize atendimento, imóveis, contratos e financeiro para sua equipe trabalhar com mais clareza e menos controles separados.
            </p>

            <div className="mt-7 grid max-w-xl grid-cols-1 gap-2.5 text-sm text-slate-200 sm:grid-cols-3">
              {["CRM e agenda", "Imóveis e contratos", "Locação e financeiro"].map((item) => (
                <div key={item} className="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-white/[0.04] px-3 py-3">
                  <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#f9bf0a] text-[#06162b]">
                    <Check size={12} strokeWidth={3} />
                  </span>
                  {item}
                </div>
              ))}
            </div>

            <div className="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
              <a
                href={whatsappUrl}
                target="_blank"
                rel="noreferrer"
                className="group inline-flex w-full items-center justify-center gap-3 rounded-full bg-[#f1132b] px-7 py-4 text-base font-bold text-white shadow-[0_18px_45px_rgba(241,19,43,.30)] transition hover:-translate-y-0.5 hover:bg-[#dc1026] sm:w-auto"
              >
                Agendar demonstração
                <ArrowRight size={19} className="transition group-hover:translate-x-1" />
              </a>
              <p className="text-xs leading-5 text-slate-400">
                Atendimento direto pelo WhatsApp<br />
                <span className="font-semibold text-slate-300">+55 92 99228-7144</span>
              </p>
            </div>
          </section>

          <section className="relative hidden items-center justify-center md:flex" aria-label="Visão integrada do SOCIMOB">
            <div className="absolute inset-8 rounded-[48px] bg-blue-500/15 blur-3xl" />
            <div className="relative w-full max-w-[620px] rotate-[1deg] rounded-[30px] border border-white/15 bg-white p-2.5 shadow-[0_35px_90px_rgba(0,0,0,.45)]">
              <div className="flex items-center justify-between rounded-t-[22px] bg-slate-50 px-5 py-3.5 text-slate-800">
                <div className="flex gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-red-400" /><span className="h-2.5 w-2.5 rounded-full bg-yellow-400" /><span className="h-2.5 w-2.5 rounded-full bg-emerald-400" /></div>
                <span className="text-[11px] font-semibold text-slate-400">Visão geral da operação</span>
              </div>

              <div className="grid grid-cols-[132px_1fr] gap-3 rounded-b-[22px] bg-[#edf2f7] p-3 text-slate-900">
                <aside className="rounded-2xl bg-[#0d2950] p-3 text-white">
                  <p className="px-2 text-[10px] font-bold tracking-[0.18em] text-blue-200">SOCIMOB</p>
                  <div className="mt-5 space-y-1.5">
                    {["Visão geral", "Atendimento", "Imóveis", "Financeiro", "Agenda"].map((item, index) => (
                      <div key={item} className={`rounded-lg px-2.5 py-2 text-[10px] ${index === 0 ? "bg-white/15 font-semibold" : "text-white/55"}`}>{item}</div>
                    ))}
                  </div>
                </aside>

                <div className="space-y-3">
                  <div className="grid grid-cols-3 gap-2">
                    {[
                      [Users, "Leads", "Atendimento"],
                      [Building2, "Imóveis", "Carteira"],
                      [Wallet, "Financeiro", "Gestão"],
                    ].map(([Icon, title, text]) => {
                      const IconComponent = Icon as typeof Users;
                      return (
                        <div key={title as string} className="rounded-xl bg-white p-3 shadow-sm">
                          <IconComponent size={16} className="text-[#2f6ea8]" />
                          <p className="mt-3 text-xs font-bold">{title as string}</p>
                          <p className="mt-0.5 text-[9px] text-slate-400">{text as string}</p>
                        </div>
                      );
                    })}
                  </div>

                  <div className="rounded-2xl bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                      <div><p className="text-xs font-bold">Acompanhamento comercial</p><p className="mt-1 text-[9px] text-slate-400">Movimentação da equipe</p></div>
                      <CalendarDays size={17} className="text-[#2f6ea8]" />
                    </div>
                    <div className="mt-4 flex h-28 items-end gap-2">
                      {[42, 62, 53, 79, 68, 91, 75, 86].map((height, index) => (
                        <div key={index} className="flex-1 rounded-t bg-gradient-to-t from-[#1f568b] to-[#65a5df]" style={{ height: `${height}%` }} />
                      ))}
                    </div>
                  </div>

                  <div className="grid grid-cols-[1fr_auto] gap-2">
                    <div className="rounded-xl bg-white p-3 shadow-sm"><p className="text-[10px] font-bold">Próximas atividades</p><div className="mt-2 flex gap-1.5"><span className="h-1.5 flex-1 rounded bg-slate-200" /><span className="h-1.5 w-12 rounded bg-blue-200" /></div></div>
                    <div className="flex items-center gap-2 rounded-xl bg-[#f9bf0a] px-4 text-[10px] font-bold"><Check size={14} /> Tudo conectado</div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>

        <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-white/[0.08] py-5 text-[11px] text-slate-500">
          <p>CRM, imóveis, locação, financeiro e operação imobiliária.</p>
          <p>Conheça apenas o que faz sentido para a sua equipe.</p>
        </footer>
      </div>
    </div>
  );
}
