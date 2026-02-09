import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/hooks/useQueryConfig";
import ProgressBar from "@/components/ProgressBar";
import { lazy, Suspense } from "react";
import { SkeletonLoader } from "./components/SkeletonLoader";

// Lazy load das páginas para code splitting
const Dashboard = lazy(() => import("./pages/Dashboard"));
const Leads = lazy(() => import("./pages/Leads"));
const Properties = lazy(() => import("./pages/Properties"));
const Chat = lazy(() => import("./pages/Chat"));
const NotificationCenter = lazy(() => import("./pages/NotificationCenter"));
const Login = lazy(() => import("./pages/Login"));
const ForgotPassword = lazy(() => import("./pages/ForgotPassword"));
const ResetPassword = lazy(() => import("./pages/ResetPassword"));
const ClientPortal = lazy(() => import("./pages/ClientPortal"));
const Agenda = lazy(() => import("./pages/Agenda"));
const Financeiro = lazy(() => import("./pages/Financeiro"));
const Vistorias = lazy(() => import("./pages/Vistorias"));
const Assinaturas = lazy(() => import("./pages/Assinaturas"));
const Pessoas = lazy(() => import("./pages/Pessoas"));
const VistoriaDetail = lazy(() => import("./pages/VistoriaDetail"));
const VistoriaSolicitacaoNova = lazy(() => import("./pages/VistoriaSolicitacaoNova"));
const VistoriaSolicitacoes = lazy(() => import("./pages/VistoriaSolicitacoes"));
const VistoriaSolicitacoesKanban = lazy(() => import("./pages/VistoriaSolicitacoesKanban"));
const VistoriaSolicitacoesCalendario = lazy(() => import("./pages/VistoriaSolicitacoesCalendario"));
const VistoriaContestacoes = lazy(() => import("./pages/VistoriaContestacoes"));
const LeadProfile = lazy(() => import("./pages/LeadProfile"));
const PropertyDetail = lazy(() => import("./pages/PropertyDetail"));
const ImovelForm = lazy(() => import("./pages/ImovelForm"));
const Settings = lazy(() => import("./pages/Settings"));
const SystemLogs = lazy(() => import("./pages/SystemLogs"));
const Tenants = lazy(() => import("./pages/Tenants"));
const AdminUsers = lazy(() => import("./pages/AdminUsers"));
const PropertyAds = lazy(() => import("./pages/PropertyAds"));
const Analytics = lazy(() => import("./pages/Analytics"));
const AnalyticsConsentBanner = lazy(() => import("./components/AnalyticsConsentBanner"));
const AnalyticsTracker = lazy(() => import("./components/AnalyticsTracker"));

// Loading fallback component
function PageLoader() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-slate-900 dark:via-blue-950 dark:to-indigo-950 flex items-center justify-center p-4">
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
  return (
    <Suspense fallback={<PageLoader />}>
      <Switch>
      <Route path={"/"} component={ClientPortal} />
      <Route path="/dashboard" component={Dashboard} />
      <Route path="/leads" component={Leads} />
      <Route path="/leads/:id" component={LeadProfile} />
      <Route path="/properties" component={Properties} />
      <Route path="/properties/novo" component={ImovelForm} />
      <Route path="/chat" component={Chat} />
      <Route path="/notifications" component={NotificationCenter} />
      <Route path="/login" component={Login} />
      <Route path="/forgot-password" component={ForgotPassword} />
      <Route path="/reset-password" component={ResetPassword} />
      <Route path="/portal/imovel/:id" component={PropertyDetail} />
      <Route path="/portal" component={ClientPortal} />
      <Route path="/agenda" component={Agenda} />
      <Route path="/financeiro" component={Financeiro} />
      <Route path="/vistorias" component={Vistorias} />
      <Route path="/vistorias/solicitacoes" component={VistoriaSolicitacoes} />
      <Route path="/vistorias/solicitacoes/kanban" component={VistoriaSolicitacoesKanban} />
      <Route path="/vistorias/solicitacoes/calendario" component={VistoriaSolicitacoesCalendario} />
      <Route path="/vistorias/solicitacoes/nova" component={VistoriaSolicitacaoNova} />
      <Route path="/vistorias/contestacoes" component={VistoriaContestacoes} />
      <Route path="/vistorias/:id" component={VistoriaDetail} />
      <Route path="/assinaturas" component={Assinaturas} />
      <Route path="/pessoas/:id" component={Pessoas} />
      <Route path="/pessoas" component={Pessoas} />
      <Route path="/settings" component={Settings} />
      <Route path="/tenants" component={Tenants} />
      <Route path="/system-logs" component={SystemLogs} />
      <Route path="/admin/users" component={AdminUsers} />
      <Route path="/admin/property-ads" component={PropertyAds} />
      <Route path="/analytics" component={Analytics} />
      <Route path={"/404"} component={NotFound} />
      {/* Final fallback route */}
      <Route component={NotFound} />
    </Switch>
    </Suspense>
  );
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
            <Toaster />
            <Suspense fallback={null}>
              <AnalyticsConsentBanner />
              <AnalyticsTracker />
            </Suspense>
            <Router />
          </TooltipProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
}

export default App;
