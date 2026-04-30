import { useEffect } from "react";
import { QueryClientProvider } from "@tanstack/react-query";
import { Route, Router, Switch } from "wouter";
import { useHashLocation } from "wouter/use-hash-location";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { DemoStoreProvider } from "@/lib/demo-store";
import { queryClient } from "@/lib/queryClient";
import { initTheme } from "@/components/theme-toggle";
import PreviewSelector from "@/pages/preview-selector";
import LoginPage from "@/pages/login";
import ClientDashboard from "@/pages/client-dashboard";
import AdminDashboard from "@/pages/admin-dashboard";
import NotFound from "@/pages/not-found";

function AppRouter() {
  return (
    <Switch>
      {/* The marketing role picker is no longer the production default. It
          remains accessible at /#/welcome (or via ?preview=1 / ?welcome=1)
          so the design team can still demo the cards. */}
      <Route path="/welcome" component={PreviewSelector} />
      {/* Legacy: previously / mounted the role picker. Keep it pointing at
          the welcome page so old bookmarks resolve, but main.tsx will
          replace this hash before React boots in production. */}
      <Route path="/" component={PreviewSelector} />
      <Route path="/login" component={LoginPage} />
      <Route path="/client" component={ClientDashboard} />
      <Route path="/client/:tab" component={ClientDashboard} />
      <Route path="/client/:tab/:sub" component={ClientDashboard} />
      <Route path="/admin" component={AdminDashboard} />
      <Route path="/admin/:tab" component={AdminDashboard} />
      <Route path="/admin/:tab/:sub" component={AdminDashboard} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  useEffect(() => {
    initTheme();
  }, []);
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <DemoStoreProvider>
          <Router hook={useHashLocation}>
            <AppRouter />
          </Router>
        </DemoStoreProvider>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
