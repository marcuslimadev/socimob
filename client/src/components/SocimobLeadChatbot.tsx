import { useRef, useState } from "react";
import ChatBot, { type Flow, type Settings, type Styles } from "react-chatbotify";
import { ArrowRight, Bot, Building2, MessageCircleMore, PhoneCall, Sparkles } from "lucide-react";

type LeadCaptureData = {
  name: string;
  company: string;
  goal: string;
  operationFocus: string;
  teamSize: string;
  challenge: string;
  whatsapp: string;
  email: string | null;
  urgency: string;
  recommendedPlan: string;
  whatsappUrl: string;
  leadId: number | null;
  submitError: string | null;
};

function sanitizePhone(value: string) {
  return value.replace(/\D/g, "");
}

function formatPhonePreview(value: string) {
  const digits = sanitizePhone(value).slice(0, 11);

  if (digits.length <= 2) return digits;
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function isValidName(value: string) {
  return value.trim().length >= 2;
}

function isValidPhone(value: string) {
  const digits = sanitizePhone(value);
  return digits.length >= 10 && digits.length <= 13;
}

function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function recommendPlan(goal: string, teamSize: string) {
  if (
    teamSize === "6 a 10 usuários" ||
    teamSize === "Mais de 10 usuários" ||
    goal === "Quero CRM, operação e automação no mesmo sistema"
  ) {
    return "Plano Pró";
  }

  if (
    teamSize === "3 a 5 usuários" ||
    goal === "Quero organizar locação, carteira e financeiro"
  ) {
    return "Plano Gestão";
  }

  return "Plano Básico";
}

function buildInterestSummary(data: LeadCaptureData) {
  return [
    "Lead capturado pela landing do SOCIMOB",
    `Nome: ${data.name}`,
    `Empresa/operação: ${data.company}`,
    `Objetivo principal: ${data.goal}`,
    `Frente prioritária: ${data.operationFocus}`,
    `Tamanho da equipe: ${data.teamSize}`,
    `Urgência: ${data.urgency}`,
    `Plano sugerido: ${data.recommendedPlan}`,
    `Dor principal: ${data.challenge}`,
  ].join(" | ");
}

function LeadSummaryCard({
  data,
  fallbackWhatsAppUrl,
}: {
  data: LeadCaptureData;
  fallbackWhatsAppUrl: string;
}) {
  const whatsappUrl = data.whatsappUrl || fallbackWhatsAppUrl;

  return (
    <div
      style={{
        marginTop: 12,
        borderRadius: 20,
        border: "1px solid rgba(13, 41, 80, 0.12)",
        background: "linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(247,245,239,0.98) 100%)",
        padding: 16,
        color: "#10233d",
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <div
          style={{
            display: "flex",
            height: 36,
            width: 36,
            alignItems: "center",
            justifyContent: "center",
            borderRadius: 12,
            background: "#0d2950",
            color: "#fff",
          }}
        >
          <Building2 size={18} />
        </div>
        <div>
          <p style={{ margin: 0, fontSize: 12, fontWeight: 700, letterSpacing: "0.14em", textTransform: "uppercase", color: "#617489" }}>
            Resumo da triagem
          </p>
          <p style={{ margin: "4px 0 0", fontSize: 18, fontWeight: 700 }}>
            {data.recommendedPlan}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 14, display: "grid", gap: 10 }}>
        <div style={{ borderRadius: 16, background: "#fff", padding: 12 }}>
          <p style={{ margin: 0, fontSize: 12, fontWeight: 700, color: "#617489" }}>Prioridade</p>
          <p style={{ margin: "4px 0 0", fontSize: 14 }}>{data.goal}</p>
        </div>
        <div style={{ borderRadius: 16, background: "#fff", padding: 12 }}>
          <p style={{ margin: 0, fontSize: 12, fontWeight: 700, color: "#617489" }}>Frente mais urgente</p>
          <p style={{ margin: "4px 0 0", fontSize: 14 }}>{data.operationFocus}</p>
        </div>
        <div style={{ borderRadius: 16, background: "#fff", padding: 12 }}>
          <p style={{ margin: 0, fontSize: 12, fontWeight: 700, color: "#617489" }}>Dor principal</p>
          <p style={{ margin: "4px 0 0", fontSize: 14 }}>{data.challenge}</p>
        </div>
      </div>

      <div style={{ marginTop: 14, display: "flex", flexWrap: "wrap", gap: 10 }}>
        <a
          href={whatsappUrl}
          target="_blank"
          rel="noreferrer"
          style={{
            display: "inline-flex",
            alignItems: "center",
            gap: 8,
            borderRadius: 999,
            background: "#f9bf0a",
            color: "#050308",
            padding: "10px 16px",
            textDecoration: "none",
            fontSize: 13,
            fontWeight: 700,
          }}
        >
          Falar com o comercial
          <ArrowRight size={14} />
        </a>
        <a
          href="#planos"
          style={{
            display: "inline-flex",
            alignItems: "center",
            gap: 8,
            borderRadius: 999,
            border: "1px solid rgba(13, 41, 80, 0.14)",
            color: "#10233d",
            padding: "10px 16px",
            textDecoration: "none",
            fontSize: 13,
            fontWeight: 700,
            background: "#fff",
          }}
        >
          Ver planos agora
          <ArrowRight size={14} />
        </a>
      </div>
    </div>
  );
}

export default function SocimobLeadChatbot({
  whatsappPhone,
  mascotUrl = "/assets/golfinho-new.png",
}: {
  whatsappPhone: string;
  mascotUrl?: string;
}) {
  const leadRef = useRef<LeadCaptureData>({
    name: "",
    company: "",
    goal: "",
    operationFocus: "",
    teamSize: "",
    challenge: "",
    whatsapp: "",
    email: null,
    urgency: "",
    recommendedPlan: "Plano Gestão",
    whatsappUrl: "",
    leadId: null,
    submitError: null,
  });
  const [, forceRender] = useState(0);

  const updateLead = (patch: Partial<LeadCaptureData>) => {
    leadRef.current = { ...leadRef.current, ...patch };
    forceRender((current) => current + 1);
  };

  const fallbackWhatsAppUrl = `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(
    "Olá! Acabei de passar pela triagem da landing do SOCIMOB e quero continuar a conversa."
  )}`;

  const submitLead = async () => {
    const current = leadRef.current;
    const recommendedPlan = recommendPlan(current.goal, current.teamSize);

    updateLead({ recommendedPlan, submitError: null });

    try {
      const response = await fetch("/api/portal/chat-lead", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Tenant-Domain": window.location.hostname,
        },
        body: JSON.stringify({
          nome: current.name,
          whatsapp: sanitizePhone(current.whatsapp),
          email: current.email || undefined,
          interesse: buildInterestSummary({ ...current, recommendedPlan }),
          origem_agendamento: "portal_home",
        }),
      });

      const data = await response.json();

      if (!response.ok || !data?.success) {
        throw new Error(data?.error || "Falha ao registrar lead");
      }

      const destinationPhone = String(data.whatsapp_number || whatsappPhone).replace(/\D/g, "");
      const message = [
        `Olá! Sou ${current.name}.`,
        `Acabei de preencher a triagem da landing do SOCIMOB.`,
        `Meu foco é: ${current.goal}.`,
        `Prioridade: ${current.operationFocus}.`,
        `Equipe: ${current.teamSize}.`,
      ].join(" ");

      updateLead({
        recommendedPlan,
        leadId: data.lead_id ?? null,
        whatsappUrl: destinationPhone
          ? `https://wa.me/${destinationPhone}?text=${encodeURIComponent(message)}`
          : fallbackWhatsAppUrl,
        submitError: null,
      });
    } catch {
      updateLead({
        recommendedPlan,
        whatsappUrl: fallbackWhatsAppUrl,
        submitError: "Não consegui registrar automaticamente, mas já deixei o atalho do WhatsApp pronto.",
      });
    }
  };

  const flow: Flow = {
    start: {
      message: "Oi! Eu sou o mascote comercial do SOCIMOB. Em 2 minutos eu entendo sua operação e já preparo um lead qualificado. Primeiro: como você se chama?",
      function: (params) => updateLead({ name: params.userInput.trim() }),
      path: (params) => (isValidName(params.userInput) ? "ask_company" : "retry_name"),
    },
    retry_name: {
      message: "Preciso de pelo menos um nome curto para seguir. Como posso te chamar?",
      function: (params) => updateLead({ name: params.userInput.trim() }),
      path: (params) => (isValidName(params.userInput) ? "ask_company" : "retry_name"),
    },
    ask_company: {
      message: () => `${leadRef.current.name}, qual é o nome da sua imobiliária, operação ou equipe?`,
      function: (params) => updateLead({ company: params.userInput.trim() }),
      path: (params) => (isValidName(params.userInput) ? "ask_goal" : "retry_company"),
    },
    retry_company: {
      message: "Me passa pelo menos o nome da operação para eu qualificar o lead direito.",
      function: (params) => updateLead({ company: params.userInput.trim() }),
      path: (params) => (isValidName(params.userInput) ? "ask_goal" : "retry_company"),
    },
    ask_goal: {
      message: () => `Perfeito. Qual resultado você quer destravar primeiro em ${leadRef.current.company}?`,
      options: [
        "Quero gerar mais leads e responder mais rápido",
        "Quero organizar locação, carteira e financeiro",
        "Quero CRM, operação e automação no mesmo sistema",
        "Quero comparar planos antes de decidir",
      ],
      chatDisabled: true,
      function: (params) => updateLead({ goal: params.userInput }),
      path: "ask_operation_focus",
    },
    ask_operation_focus: {
      message: "Qual frente pesa mais hoje na sua decisão?",
      options: [
        "Compra e venda",
        "Locação e repasses",
        "Operação mista",
        "Ainda estou mapeando",
      ],
      chatDisabled: true,
      function: (params) => updateLead({ operationFocus: params.userInput }),
      path: "ask_team_size",
    },
    ask_team_size: {
      message: "Quantas pessoas devem usar o sistema com frequência?",
      options: [
        "1 a 2 usuários",
        "3 a 5 usuários",
        "6 a 10 usuários",
        "Mais de 10 usuários",
      ],
      chatDisabled: true,
      function: (params) => updateLead({ teamSize: params.userInput }),
      path: "ask_challenge",
    },
    ask_challenge: {
      message: "Se eu tivesse que vender internamente essa proposta por você, qual gargalo eu deveria destacar?",
      function: (params) => updateLead({ challenge: params.userInput.trim() }),
      path: (params) => (params.userInput.trim().length >= 8 ? "ask_whatsapp" : "retry_challenge"),
    },
    retry_challenge: {
      message: "Me dá um pouco mais de contexto. Pode ser algo como perda de lead, atendimento lento, repasse confuso ou excesso de planilhas.",
      function: (params) => updateLead({ challenge: params.userInput.trim() }),
      path: (params) => (params.userInput.trim().length >= 8 ? "ask_whatsapp" : "retry_challenge"),
    },
    ask_whatsapp: {
      message: "Boa. Agora me passa seu WhatsApp com DDD para eu deixar o comercial pronto para continuar.",
      function: (params) => updateLead({ whatsapp: formatPhonePreview(params.userInput) }),
      path: (params) => (isValidPhone(params.userInput) ? "ask_email_preference" : "retry_whatsapp"),
    },
    retry_whatsapp: {
      message: "Esse número parece incompleto. Envie com DDD, por exemplo: (92) 99999-8888.",
      function: (params) => updateLead({ whatsapp: formatPhonePreview(params.userInput) }),
      path: (params) => (isValidPhone(params.userInput) ? "ask_email_preference" : "retry_whatsapp"),
    },
    ask_email_preference: {
      message: "Quer deixar também um e-mail comercial para a proposta?",
      options: ["Sim, vou informar", "Agora não"],
      chatDisabled: true,
      path: (params) => (params.userInput === "Sim, vou informar" ? "ask_email" : "ask_urgency"),
    },
    ask_email: {
      message: "Perfeito. Qual e-mail devemos usar?",
      function: (params) => updateLead({ email: params.userInput.trim() }),
      path: (params) => (isValidEmail(params.userInput) ? "ask_urgency" : "retry_email"),
    },
    retry_email: {
      message: "Esse e-mail não parece válido. Se quiser seguir sem ele, responda: agora não.",
      function: (params) => {
        const value = params.userInput.trim().toLowerCase();
        if (value === "agora não") {
          updateLead({ email: null });
          return;
        }
        updateLead({ email: params.userInput.trim() });
      },
      path: (params) => {
        const value = params.userInput.trim().toLowerCase();
        if (value === "agora não") {
          return "ask_urgency";
        }
        return isValidEmail(params.userInput) ? "ask_urgency" : "retry_email";
      },
    },
    ask_urgency: {
      message: "Última etapa: qual é o timing dessa conversa?",
      options: [
        "Quero falar ainda hoje",
        "Quero avançar nesta semana",
        "Estou pesquisando sem urgência",
      ],
      chatDisabled: true,
      function: async (params) => {
        updateLead({ urgency: params.userInput });
        await submitLead();
      },
      path: "done",
    },
    done: {
      message: () =>
        leadRef.current.submitError
          ? `${leadRef.current.name}, tive uma falha no registro automático, mas a triagem já está pronta para você continuar sem perder contexto.`
          : `${leadRef.current.name}, lead criado. Já deixei a intenção resumida e uma recomendação inicial para o comercial seguir sem fricção.`,
      component: () => (
        <LeadSummaryCard
          data={leadRef.current}
          fallbackWhatsAppUrl={fallbackWhatsAppUrl}
        />
      ),
      options: ["Refazer triagem"],
      chatDisabled: true,
      function: () =>
        updateLead({
          name: "",
          company: "",
          goal: "",
          operationFocus: "",
          teamSize: "",
          challenge: "",
          whatsapp: "",
          email: null,
          urgency: "",
          recommendedPlan: "Plano Gestão",
          whatsappUrl: "",
          leadId: null,
          submitError: null,
        }),
      path: "start",
    },
  };

  const settings: Settings = {
    general: {
      embedded: true,
      showHeader: false,
      showFooter: false,
      primaryColor: "#0d2950",
      secondaryColor: "#f9bf0a",
      fontFamily: "Roboto, sans-serif",
    },
    chatWindow: {
      showTypingIndicator: true,
    },
    chatHistory: {
      disabled: true,
      storageKey: "socimob-marketing-lead-chat",
    },
    chatInput: {
      enabledPlaceholderText: "Digite sua resposta aqui...",
      botDelay: 400,
    },
    botBubble: {
      showAvatar: true,
      avatar: mascotUrl,
      simulateStream: true,
      streamSpeed: 24,
    },
    userBubble: {
      showAvatar: false,
    },
    notification: {
      disabled: true,
    },
    audio: {
      disabled: true,
    },
    voice: {
      disabled: true,
    },
  };

  const styles: Styles = {
    chatWindowStyle: {
      width: "100%",
      minHeight: 560,
      background: "#f7f5ef",
      borderRadius: 28,
      boxShadow: "none",
      border: "1px solid rgba(13, 41, 80, 0.10)",
    },
    bodyStyle: {
      background: "linear-gradient(180deg, rgba(255,255,255,0.92) 0%, rgba(247,245,239,0.96) 100%)",
      padding: 18,
    },
    chatInputContainerStyle: {
      borderTop: "1px solid rgba(13, 41, 80, 0.08)",
      background: "#fffdf8",
      padding: 14,
    },
    chatInputAreaStyle: {
      background: "#ffffff",
      border: "1px solid rgba(13, 41, 80, 0.12)",
      borderRadius: 18,
      color: "#10233d",
      boxShadow: "none",
    },
    chatInputAreaFocusedStyle: {
      border: "1px solid rgba(47, 110, 168, 0.45)",
      boxShadow: "0 0 0 3px rgba(47, 110, 168, 0.12)",
    },
    botBubbleStyle: {
      background: "#ffffff",
      color: "#10233d",
      border: "1px solid rgba(13, 41, 80, 0.08)",
      borderRadius: 20,
      boxShadow: "0 10px 24px rgba(13, 41, 80, 0.06)",
      lineHeight: 1.6,
    },
    userBubbleStyle: {
      background: "#0d2950",
      color: "#ffffff",
      borderRadius: 20,
      boxShadow: "0 10px 24px rgba(13, 41, 80, 0.16)",
      lineHeight: 1.6,
    },
    botOptionStyle: {
      borderRadius: 999,
      border: "1px solid rgba(13, 41, 80, 0.12)",
      background: "#fff",
      color: "#10233d",
      fontWeight: 600,
      padding: "10px 14px",
    },
    botOptionHoveredStyle: {
      background: "#f9bf0a",
      color: "#050308",
      border: "1px solid #f9bf0a",
    },
    sendButtonStyle: {
      background: "#f1132b",
      borderRadius: 999,
    },
    sendButtonHoveredStyle: {
      background: "#ca1024",
    },
    sendIconStyle: {
      color: "#ffffff",
    },
    footerStyle: {
      display: "none",
    },
  };

  return (
    <div className="grid gap-5 xl:grid-cols-[0.88fr_1.12fr]">
      <div
        className="rounded-[32px] border border-white/20 p-6 text-white shadow-[0_24px_60px_rgba(5,3,8,0.28)]"
        style={{
          background:
            "radial-gradient(circle_at_top_left, rgba(249,191,10,0.32), transparent 34%), linear-gradient(180deg, rgba(13,41,80,0.96) 0%, rgba(5,3,8,0.94) 100%)",
        }}
      >
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
            <Bot size={22} />
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/70">Mascote Comercial</p>
            <h3 className="text-2xl font-semibold tracking-[-0.03em] text-white">Boas-vindas que qualificam</h3>
          </div>
        </div>

        <div className="mt-6 overflow-hidden rounded-[28px] border border-white/12 bg-white/5 p-4">
          <img
            src={mascotUrl}
            alt="Mascote SOCIMOB"
            className="mx-auto h-52 w-full max-w-[18rem] object-contain"
          />
        </div>

        <div className="mt-6 space-y-3">
          {[
            "Abre a conversa de forma ativa e guiada, sem depender de formulário frio.",
            "Captura nome, WhatsApp, contexto comercial e urgência do contato.",
            "Resume a intenção do cliente e entrega um lead mais pronto para o time.",
          ].map((item) => (
            <div
              key={item}
              className="flex items-start gap-3 rounded-[22px] border border-white/12 bg-white/6 px-4 py-4 text-sm leading-6 text-white/90"
            >
              <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f9bf0a] text-[#050308]">
                <Sparkles size={14} />
              </span>
              <span>{item}</span>
            </div>
          ))}
        </div>

        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <div className="rounded-[22px] border border-white/12 bg-black/18 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Fluxo</p>
            <p className="mt-2 text-sm leading-6 text-white/90">
              Saudação, intenção, frente prioritária, equipe, desafio e contato.
            </p>
          </div>
          <div className="rounded-[22px] border border-white/12 bg-black/18 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Saída</p>
            <p className="mt-2 text-sm leading-6 text-white/90">
              Lead criado e CTA imediato para seguir no WhatsApp.
            </p>
          </div>
        </div>
      </div>

      <div className="rounded-[32px] border border-white/60 bg-white/70 p-4 shadow-[0_18px_40px_rgba(32,23,6,0.12)] backdrop-blur-xl sm:p-5">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3 rounded-[24px] border border-white/70 bg-white/80 px-4 py-4">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Triagem Conversacional</p>
            <h3 className="mt-2 text-2xl font-semibold tracking-[-0.03em] text-slate-950">
              O mascote recebe, qualifica e conduz.
            </h3>
          </div>

          <div className="flex gap-2">
            <span className="inline-flex items-center gap-2 rounded-full bg-[#0d2950] px-3 py-2 text-xs font-semibold text-white">
              <MessageCircleMore size={14} />
              Fluxo guiado
            </span>
            <span className="inline-flex items-center gap-2 rounded-full bg-[#f9bf0a] px-3 py-2 text-xs font-semibold text-[#050308]">
              <PhoneCall size={14} />
              Lead pronto
            </span>
          </div>
        </div>

        <ChatBot flow={flow} settings={settings} styles={styles} />
      </div>
    </div>
  );
}
