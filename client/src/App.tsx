import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch, useLocation } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/hooks/useQueryConfig";
import ProgressBar from "@/components/ProgressBar";
import { lazy, Suspense, useEffect, useState } from "react";
import { SkeletonLoader } from "./components/SkeletonLoader";
import BottomNavigation from "./components/BottomNavigation";
import { Download, RefreshCw, Share2, X } from "lucide-react";

// Lazy load das páginas para code splitting
const Dashboard = lazy(() => import("./pages/Dashboard"));
const Leads = lazy(() => import("./pages/Leads"));
const Properties = lazy(() => import("./pages/Properties"));
const Chat = lazy(() => import("./pages/Chat"));
const Login = lazy(() => import("./pages/Login"));
const PrimeiroAcessoSenha = lazy(() => import("./pages/PrimeiroAcessoSenha"));
const ForgotPassword = lazy(() => import("./pages/ForgotPassword"));
const ResetPassword = lazy(() => import("./pages/ResetPassword"));
const ClientPortal = lazy(() => import("./pages/ClientPortal"));
const ClientPortalRefined = lazy(() => import("./pages/ClientPortalRefined"));
const Agenda = lazy(() => import("./pages/Agenda"));
const Financeiro = lazy(() => import("./pages/Financeiro"));
const FinanceiroNotaDetalhe = lazy(() => import("./pages/FinanceiroNotaDetalhe"));
const AdminGestaoLocacao = lazy(() => import("./pages/AdminGestaoLocacao"));
const AdminGestaoCompraVenda = lazy(() => import("./pages/AdminGestaoCompraVenda"));
const ContasFinanceiras = lazy(() => import("./pages/ContasFinanceiras"));
const PortalPessoaFinanceiro = lazy(() => import("./pages/PortalPessoaFinanceiro"));
const PortalRegister = lazy(() => import("./pages/PortalRegister"));
const PortalLogin = lazy(() => import("./pages/PortalLogin"));
const PortalVender = lazy(() => import("./pages/PortalVender"));
const Vistorias = lazy(() => import("./pages/Vistorias"));
const Assinaturas = lazy(() => import("./pages/Assinaturas"));
const Pessoas = lazy(() => import("./pages/Pessoas"));
const PessoaPerfil = lazy(() => import("./pages/PessoaPerfil"));
const VistoriaDetail = lazy(() => import("./pages/VistoriaDetail"));
const VistoriaSolicitacaoNova = lazy(() => import("./pages/VistoriaSolicitacaoNova"));
const VistoriaSolicitacoes = lazy(() => import("./pages/VistoriaSolicitacoes"));
const VistoriaSolicitacoesKanban = lazy(() => import("./pages/VistoriaSolicitacoesKanban"));
const VistoriaSolicitacoesCalendario = lazy(() => import("./pages/VistoriaSolicitacoesCalendario"));
const VistoriaContestacoes = lazy(() => import("./pages/VistoriaContestacoes"));
const VistoriaCadastroWizard = lazy(() => import("./pages/VistoriaCadastroWizard"));
const VistoriaExecucaoWizard = lazy(() => import("./pages/VistoriaExecucaoWizard"));
const LeadProfile = lazy(() => import("./pages/LeadProfile"));
const PropertyDetail = lazy(() => import("./pages/PropertyDetail"));
const ImovelFormWizard = lazy(() => import("./pages/ImovelFormWizard"));
const Settings = lazy(() => import("./pages/Settings"));
const SystemLogs = lazy(() => import("./pages/SystemLogs"));
const Tenants = lazy(() => import("./pages/Tenants"));
const TenantAssociations = lazy(() => import("./pages/TenantAssociations"));
const AdminUsers = lazy(() => import("./pages/AdminUsers"));
const PropertyAds = lazy(() => import("./pages/PropertyAds"));
const AdsAutomation = lazy(() => import("./pages/AdsAutomation"));
const AnunciosIntegrados = lazy(() => import("./pages/AnunciosIntegrados"));
const ChavesNaMaoIntegration = lazy(() => import("./pages/ChavesNaMaoIntegration"));
const Analytics = lazy(() => import("./pages/Analytics"));
const ControleChaves = lazy(() => import("./pages/ControleChaves"));
const ImportantLinks = lazy(() => import("./pages/ImportantLinks"));
const PropertySyncRuns = lazy(() => import("./pages/PropertySyncRuns"));
const SimulacaoFinanciamento = lazy(() => import("./pages/SimulacaoFinanciamento"));const ImobiBrasilPage = lazy(() => import('./pages/ImobiBrasil'));const AnalyticsConsentBanner = lazy(() => import("./components/AnalyticsConsentBanner"));
const AnalyticsTracker = lazy(() => import("./components/AnalyticsTracker"));
const PortalProprietarioLogin = lazy(() => import('./pages/PortalProprietarioLogin'));
const PortalProprietarioDashboard = lazy(() => import('./pages/PortalProprietarioDashboard'));
const ContratoTemplates = lazy(() => import('./pages/ContratoTemplates'));
const SocimobLanding = lazy(() => import("./pages/SocimobLanding"));
const NotificationCenter = lazy(() => import("./pages/NotificationCenter"));

const SOCIMOB_MARKETING_HOSTS = new Set(["socimob.com", "www.socimob.com"]);

// Loading fallback component
function PageLoader() {
  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <div className="w-full max-w-7xl space-y-6">
        <SkeletonLoader variant="card" count={1} className="h-32" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <SkeletonLoader variant="card" count={4} />
        </div>
      </div>
    </div>
  );
}

function Router() {
  const isSocimobMarketingHost =
    typeof window !== "undefined" &&
    SOCIMOB_MARKETING_HOSTS.has(window.location.hostname.toLowerCase());

  if (isSocimobMarketingHost) {
    return (
      <Suspense fallback={<PageLoader />}>
        <Switch>
          <Route path="/" component={SocimobLanding} />
          <Route path="/planos" component={SocimobLanding} />
          <Route path="/modulos" component={SocimobLanding} />
          <Route path="/contato" component={SocimobLanding} />
          <Route component={SocimobLanding} />
        </Switch>
      </Suspense>
    );
  }

  return (
    <Suspense fallback={<PageLoader />}>
      <Switch>
      <Route path={"/"} component={ClientPortalRefined} />
      <Route path="/dashboard" component={Dashboard} />
      <Route path="/crm" component={Chat} />
      <Route path="/leads" component={Leads} />
      <Route path="/leads/:id" component={LeadProfile} />
      <Route path="/properties" component={Properties} />
      <Route path="/properties/sincronizacoes" component={PropertySyncRuns} />
      <Route path="/properties/propaganda" component={PropertyAds} />
      <Route path="/properties/novo" component={ImovelFormWizard} />
      <Route path="/properties/:id/editar" component={ImovelFormWizard} />
      <Route path="/chat" component={Chat} />
      <Route path="/notifications" component={NotificationCenter} />
      <Route path="/login" component={Login} />
      <Route path="/primeiro-acesso" component={PrimeiroAcessoSenha} />
      <Route path="/forgot-password" component={ForgotPassword} />
      <Route path="/reset-password" component={ResetPassword} />
      <Route path="/portal/imovel/:id" component={PropertyDetail} />
      <Route path="/portal/proprietario/login" component={PortalProprietarioLogin} />
      <Route path="/portal/proprietario/dashboard" component={PortalProprietarioDashboard} />
      <Route path="/portal/proprietario" component={PortalProprietarioDashboard} />
      <Route path="/portal/login" component={PortalLogin} />
      <Route path="/portal/register" component={PortalRegister} />
      <Route path="/portal/vender" component={PortalVender} />
      <Route path="/portal/simulacao" component={SimulacaoFinanciamento} />
      <Route path="/portal" component={ClientPortalRefined} />
      <Route path="/portal/classic" component={ClientPortal} />
      <Route path="/agenda" component={Agenda} />
      <Route path="/financeiro/notas/:registroTipo/:id" component={AdminFinanceiroNotaDetalheGate} />
      <Route path="/financeiro" component={AdminFinanceiroGate} />
      <Route path="/financeiro/locacao" component={AdminGestaoLocacaoGate} />
      <Route path="/financeiro/compra-venda" component={AdminGestaoCompraVendaGate} />
      <Route path="/financeiro/contas" component={AdminContasFinanceirasGate} />
      <Route path="/portal/meu-financeiro" component={PortalFinanceiroGate} />
      <Route path="/vistorias" component={Vistorias} />
      <Route path="/vistorias/wizard" component={VistoriaCadastroWizard} />
      <Route path="/vistorias/solicitacoes" component={VistoriaSolicitacoes} />
      <Route path="/vistorias/solicitacoes/kanban" component={VistoriaSolicitacoesKanban} />
      <Route path="/vistorias/solicitacoes/calendario" component={VistoriaSolicitacoesCalendario} />
      <Route path="/vistorias/solicitacoes/nova" component={VistoriaSolicitacaoNova} />
      <Route path="/vistorias/contestacoes" component={VistoriaContestacoes} />
      <Route path="/vistorias/:id/execucao" component={VistoriaExecucaoWizard} />
      <Route path="/vistorias/:id" component={VistoriaDetail} />
      <Route path="/assinaturas" component={Assinaturas} />
      <Route path="/pessoas/:id" component={PessoaPerfil} />
      <Route path="/pessoas" component={Pessoas} />
      <Route path="/settings" component={Settings} />
      <Route path="/contrato-templates" component={ContratoTemplates} />
      <Route path="/tenants" component={Tenants} />
      <Route path="/tenants/associacoes" component={TenantAssociations} />
      <Route path="/system-logs" component={SystemLogs} />
      <Route path="/admin/users" component={AdminUsers} />
      <Route path="/admin/property-ads" component={PropertyAds} />
      <Route path="/ads" component={AdsAutomation} />
      <Route path="/anuncios-integrados" component={AnunciosIntegrados} />
      <Route path="/chaves-na-mao" component={ChavesNaMaoIntegration} />
      <Route path="/controle-chaves" component={ControleChaves} />
      <Route path="/links-importantes" component={ImportantLinks} />
      <Route path="/analytics" component={Analytics} />
      <Route path="/imobi-brasil" component={ImobiBrasilPage} />
      <Route path={"/404"} component={NotFound} />
      {/* Final fallback route */}
      <Route component={NotFound} />
    </Switch>
    </Suspense>
  );
}

function AdminOnlyPage({ component: Component }: { component: React.ComponentType }) {
  const [, setLocation] = useLocation();
  const [canAccess, setCanAccess] = useState<boolean | null>(null);

  useEffect(() => {
    const token = typeof window !== 'undefined' ? localStorage.getItem('token') : null;
    const rawUser = typeof window !== 'undefined' ? localStorage.getItem('user') : null;
    let user: any = null;

    try {
      user = rawUser ? JSON.parse(rawUser) : null;
    } catch {
      user = null;
    }

    const role = (user?.role || '').toLowerCase();

    if (!token) {
      setLocation('/login');
      setCanAccess(false);
      return;
    }

    if (!['admin', 'super_admin'].includes(role)) {
      setLocation('/dashboard');
      setCanAccess(false);
      return;
    }

    setCanAccess(true);
  }, [setLocation]);

  if (canAccess !== true) return null;
  return <Component />;
}

function AdminFinanceiroGate() {
  return <AdminOnlyPage component={Financeiro} />;
}

function AdminFinanceiroNotaDetalheGate() {
  return <AdminOnlyPage component={FinanceiroNotaDetalhe} />;
}

function AdminGestaoLocacaoGate() {
  return <AdminOnlyPage component={AdminGestaoLocacao} />;
}

function AdminGestaoCompraVendaGate() {
  return <AdminOnlyPage component={AdminGestaoCompraVenda} />;
}

function AdminContasFinanceirasGate() {
  return <AdminOnlyPage component={ContasFinanceiras} />;
}

function PortalFinanceiroGate() {
  const [, setLocation] = useLocation();
  const [canAccess, setCanAccess] = useState<boolean | null>(null);

  useEffect(() => {
    const token = typeof window !== "undefined" ? localStorage.getItem("token") : null;
    const rawUser = typeof window !== "undefined" ? localStorage.getItem("user") : null;
    let user: any = null;
    try {
      user = rawUser ? JSON.parse(rawUser) : null;
    } catch {
      user = null;
    }
    const role = (user?.role || "").toLowerCase();

    if (!token) {
      setLocation("/login");
      setCanAccess(false);
      return;
    }

    if (["admin", "super_admin", "corretor"].includes(role)) {
      setLocation("/dashboard");
      setCanAccess(false);
      return;
    }

    if (role && role !== "client") {
      setLocation("/dashboard");
      setCanAccess(false);
      return;
    }

    setCanAccess(true);
  }, [setLocation]);

  if (canAccess !== true) return null;
  return <PortalPessoaFinanceiro />;
}

function LoginRedirect() {
  const [, setLocation] = useLocation();

  useEffect(() => {
    setLocation("/login");
  }, [setLocation]);

  return null;
}

type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed"; platform: string }>;
};

function PwaMobilePrompt() {
  const [location] = useLocation();
  const [installPrompt, setInstallPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [dismissed, setDismissed] = useState(() => {
    if (typeof window === "undefined") return true;
    const dismissedAt = Number(localStorage.getItem("socimob:pwa-install-dismissed-at") || 0);
    return dismissedAt > 0 && Date.now() - dismissedAt < 7 * 24 * 60 * 60 * 1000;
  });
  const [updateReady, setUpdateReady] = useState(false);
  const [showInstallHelp, setShowInstallHelp] = useState(false);

  useEffect(() => {
    if (typeof window === "undefined") return;

    const onBeforeInstallPrompt = (event: Event) => {
      event.preventDefault();
      setInstallPrompt(event as BeforeInstallPromptEvent);
    };

    const onInstallReady = () => {
      const prompt = (window as any).deferredPwaInstallPrompt as BeforeInstallPromptEvent | undefined;
      if (prompt) setInstallPrompt(prompt);
    };

    const onControllerChange = () => setUpdateReady(true);

    onInstallReady();
    window.addEventListener("beforeinstallprompt", onBeforeInstallPrompt);
    window.addEventListener("socimob:pwa-install-ready", onInstallReady);
    navigator.serviceWorker?.addEventListener("controllerchange", onControllerChange);

    return () => {
      window.removeEventListener("beforeinstallprompt", onBeforeInstallPrompt);
      window.removeEventListener("socimob:pwa-install-ready", onInstallReady);
      navigator.serviceWorker?.removeEventListener("controllerchange", onControllerChange);
    };
  }, []);

  const isAuthenticated = typeof window !== "undefined" && Boolean(localStorage.getItem("token"));
  const isInstallRoute = location === "/dashboard";
  const isMobile =
    typeof window !== "undefined" &&
    (window.matchMedia("(max-width: 767px)").matches || /android|iphone|ipad|ipod|mobile/i.test(window.navigator.userAgent));
  const isStandalone =
    typeof window !== "undefined" &&
    (window.matchMedia("(display-mode: standalone)").matches || (window.navigator as any).standalone === true);
  const isIos =
    typeof window !== "undefined" && /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !(window as any).MSStream;
  const canShowInstall = isMobile && isAuthenticated && isInstallRoute && !dismissed && !isStandalone;

  if (isStandalone && !updateReady) return null;
  if (!updateReady && !canShowInstall) return null;

  const closeInstall = () => {
    localStorage.setItem("socimob:pwa-install-dismissed-at", String(Date.now()));
    setDismissed(true);
  };

  const install = async () => {
    if (!installPrompt) {
      setShowInstallHelp(true);
      return;
    }

    await installPrompt.prompt();
    const choice = await installPrompt.userChoice;
    if (choice.outcome !== "dismissed") closeInstall();
    (window as any).deferredPwaInstallPrompt = undefined;
    setInstallPrompt(null);
  };

  return (
    <div className="fixed inset-x-2.5 bottom-[calc(env(safe-area-inset-bottom)+4.55rem)] z-[45] mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-2 text-slate-900 shadow-[0_10px_26px_rgba(15,23,42,0.16)] md:hidden">
      <div className="flex items-center gap-2.5">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
          {updateReady ? <RefreshCw className="h-5 w-5" /> : showInstallHelp && isIos ? <Share2 className="h-5 w-5" /> : <Download className="h-5 w-5" />}
        </div>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold">
            {updateReady ? "Nova versão disponível" : showInstallHelp ? "Adicionar na tela inicial" : "Instalar SOCIMOB"}
          </p>
          <p className="truncate text-xs leading-5 text-slate-600">
            {updateReady
              ? "Atualize para carregar os últimos recursos."
              : showInstallHelp
                ? isIos
                  ? "Toque em Compartilhar e depois em Adicionar a Tela de Inicio."
                  : "No menu do navegador, toque em Instalar app ou Adicionar a tela inicial."
                : "Abra mais rápido, em tela cheia e com acesso direto no celular."}
          </p>
        </div>
          <button
            type="button"
            onClick={() => (updateReady ? window.location.reload() : install())}
            className="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 px-2.5 text-xs font-semibold text-white"
          >
            {updateReady ? "Atualizar" : "Instalar"}
          </button>
        {!updateReady && (
          <button type="button" onClick={closeInstall} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500">
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
    </div>
  );
}

function MobileScrollUnlock() {
  const [location] = useLocation();

  useEffect(() => {
    if (typeof window === "undefined") return;
    if (!window.matchMedia("(max-width: 767px)").matches) return;

    const root = document.documentElement;
    const body = document.body;

    root.style.overflowY = "auto";
    root.style.height = "auto";
    body.style.overflowY = "auto";
    body.style.height = "auto";
    body.style.position = "";
    body.style.touchAction = "pan-y pinch-zoom";
  }, [location]);

  return null;
}

// NOTE: About Theme
// - First choose a default theme according to your design style (dark or light bg), than change color palette in index.css
//   to keep consistent foreground/background color across components
// - If you want to make theme switchable, pass `switchable` ThemeProvider and use `useTheme` hook

function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider
          defaultTheme="light"
          switchable
        >
          <TooltipProvider>
            <ProgressBar />
            <MobileScrollUnlock />
            <Toaster />
            <Suspense fallback={null}>
              <AnalyticsConsentBanner />
              <AnalyticsTracker />
            </Suspense>
            <Router />
            <PwaMobilePrompt />
            <BottomNavigation />
          </TooltipProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
}

export default App;
