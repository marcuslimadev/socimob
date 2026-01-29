import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import VersionBadge from "./components/VersionBadge";
import { ThemeProvider } from "./contexts/ThemeContext";
import Dashboard from "./pages/Dashboard";
import Leads from "./pages/Leads";
import Properties from "./pages/Properties";
import Chat from "./pages/Chat";
import NotificationCenter from "./pages/NotificationCenter";
import Login from "./pages/Login";
import ForgotPassword from "./pages/ForgotPassword";
import ResetPassword from "./pages/ResetPassword";
import ClientPortal from "./pages/ClientPortal";
import Agenda from "./pages/Agenda";
import Financeiro from "./pages/Financeiro";
import Vistorias from "./pages/Vistorias";
import Assinaturas from "./pages/Assinaturas";
import Pessoas from "./pages/Pessoas";
import VistoriaDetail from "./pages/VistoriaDetail";
import VistoriaSolicitacaoNova from "./pages/VistoriaSolicitacaoNova";
import VistoriaSolicitacoes from "./pages/VistoriaSolicitacoes";
import VistoriaSolicitacoesKanban from "./pages/VistoriaSolicitacoesKanban";
import VistoriaSolicitacoesCalendario from "./pages/VistoriaSolicitacoesCalendario";
import VistoriaContestacoes from "./pages/VistoriaContestacoes";

import PropertyDetail from "./pages/PropertyDetail";
import ImovelForm from "./pages/ImovelForm";
import Settings from "./pages/Settings";


function Router() {
  return (
    <Switch>
      <Route path={"/"} component={ClientPortal} />
      <Route path="/dashboard" component={Dashboard} />
      <Route path="/leads" component={Leads} />
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
      <Route path="/pessoas" component={Pessoas} />
      <Route path="/settings" component={Settings} />
      <Route path={"/404"} component={NotFound} />
      {/* Final fallback route */}
      <Route component={NotFound} />
    </Switch>
  );
}

// NOTE: About Theme
// - First choose a default theme according to your design style (dark or light bg), than change color palette in index.css
//   to keep consistent foreground/background color across components
// - If you want to make theme switchable, pass `switchable` ThemeProvider and use `useTheme` hook

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider
        defaultTheme="dark"
      // switchable
      >
        <TooltipProvider>
          <Toaster />
          <Router />
          <VersionBadge />
        </TooltipProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
