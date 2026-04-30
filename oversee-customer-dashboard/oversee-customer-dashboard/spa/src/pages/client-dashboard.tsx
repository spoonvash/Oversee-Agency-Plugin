import { RoleShell } from "@/components/role-shell";
import { useParams } from "wouter";
import { useDemoStore } from "@/lib/demo-store";
import { clientGroups } from "@/lib/nav";
import ClientHome from "@/pages/client/home";
import ClientWork from "@/pages/client/work";
import ClientUpdates from "@/pages/client/updates";
import ClientAccount from "@/pages/client/account";
import ClientOnboarding from "@/pages/client/onboarding";
import ClientFilesPage from "@/pages/client/files-page";
import { ClientShop } from "@/pages/client/commerce";

type RouteEntry = {
  component: React.FC<any>;
  title: string;
  group: string;
  // Optional sub override — when an alias should land on a specific Account tab
  // even though the URL has no :sub segment.
  forceSub?: string;
};

const ROUTES: Record<string, RouteEntry> = {
  // Primary destinations — six total in the sidebar (Onboarding added)
  home: { component: ClientHome, title: "Home", group: "Workspace" },
  onboarding: { component: ClientOnboarding, title: "Onboarding", group: "Workspace" },
  work: { component: ClientWork, title: "My Work", group: "Workspace" },
  updates: { component: ClientUpdates, title: "Updates", group: "Workspace" },
  services: { component: ClientShop, title: "Services", group: "Services & account" },
  account: { component: ClientAccount, title: "Account", group: "Services & account" },

  // Account tab aliases — old links land in the right tab inside the new
  // consolidated Account page.
  subscriptions: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "subscriptions" },
  billing: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "orders" },
  invoices: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "orders" },
  orders: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "orders" },
  payments: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "payments" },
  addresses: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "addresses" },
  profile: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "profile" },
  contracts: { component: ClientAccount, title: "Account", group: "Services & account", forceSub: "orders" },

  // Backwards-compat shop aliases
  shop: { component: ClientShop, title: "Services", group: "Services & account" },
  commerce: { component: ClientShop, title: "Services", group: "Services & account" },

  // Workspace aliases
  workspace: { component: ClientWork, title: "My Work", group: "Workspace" },
  files: { component: ClientFilesPage, title: "Files", group: "Workspace" },
  tasks: { component: ClientWork, title: "My Work", group: "Workspace" },
  forms: { component: ClientWork, title: "My Work", group: "Workspace" },
  messages: { component: ClientUpdates, title: "Updates", group: "Workspace" },
  schedule: { component: ClientUpdates, title: "Updates", group: "Workspace" },
  reports: { component: ClientHome, title: "Home", group: "Workspace" },
  reviews: { component: ClientHome, title: "Home", group: "Workspace" },
  setup: { component: ClientOnboarding, title: "Onboarding", group: "Workspace" },
  access: { component: ClientOnboarding, title: "Onboarding", group: "Workspace" },
};

export default function ClientDashboard() {
  const { tab } = useParams<{ tab?: string }>();
  const key = tab && ROUTES[tab] ? tab : "home";
  const route = ROUTES[key];
  const { customer } = useDemoStore();

  const Component = route.component;
  const initials = customer.name.split(" ").map((s) => s[0]).join("").toUpperCase();

  return (
    <RoleShell
      role="client"
      groups={clientGroups}
      identity={{ name: customer.name, sub: customer.email, initials }}
      breadcrumbs={[{ label: "Client Portal", href: "/client" }, { label: route.group }, { label: route.title }]}
    >
      {/* Full-width content area — pages decide their own internal max widths via inner wrappers if needed */}
      <div className="w-full space-y-2 px-4 py-6 md:px-6 md:py-8 lg:px-8 xl:px-10">
        <Component {...(route.forceSub ? { initialTab: route.forceSub } : {})} />
      </div>
    </RoleShell>
  );
}
