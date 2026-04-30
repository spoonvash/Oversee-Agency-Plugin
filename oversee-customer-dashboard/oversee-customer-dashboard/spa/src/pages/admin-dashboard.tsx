import { useParams } from "wouter";
import { RoleShell } from "@/components/role-shell";
import { adminGroups } from "@/lib/nav";
import AdminToday from "@/pages/admin/today";
import AdminBoards from "@/pages/admin/boards";
import AdminUpdates from "@/pages/admin/updates";
import AdminFilesApprovals from "@/pages/admin/files-approvals";
import AdminClients from "@/pages/admin/clients";
import AdminCommerceHandoff from "@/pages/admin/commerce-handoff";
import { AdminSettings } from "@/pages/admin/system";

type RouteEntry = {
  component: React.FC<any>;
  title: string;
  group: string;
  forceSub?: string;
};

const ROUTES: Record<string, RouteEntry> = {
  // 5 primary destinations — nav order
  today: { component: AdminToday, title: "Today", group: "Workspace" },
  boards: { component: AdminBoards, title: "Boards", group: "Workspace" },
  updates: { component: AdminUpdates, title: "Updates", group: "Workspace" },
  clients: { component: AdminClients, title: "Clients", group: "Workspace" },
  settings: { component: AdminSettings, title: "Settings", group: "Workspace" },

  // Settings tab aliases — old links now land on the right tab.
  team: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "team" },
  automations: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "highlevel" },
  highlevel: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "highlevel" },
  integrations: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "general" },
  branding: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "general" },
  billing: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "general" },
  domains: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "general" },
  handoffs: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "woocommerce" },
  leadsie: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "leadsie" },
  onboarding: { component: AdminSettings, title: "Settings", group: "Workspace", forceSub: "leadsie" },
  system: { component: AdminSettings, title: "Settings", group: "Workspace" },

  // Backwards-compat: any commerce link shows the WooCommerce handoff notice (no product UI).
  commerce: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  orders: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  subscriptions: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  templates: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  catalog: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  payments: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },
  shop: { component: AdminCommerceHandoff, title: "Commerce", group: "Workspace" },

  // Other backwards-compat aliases (not in nav).
  "files-approvals": { component: AdminFilesApprovals, title: "Boards", group: "Workspace" },
  projects: { component: AdminBoards, title: "Boards", group: "Workspace" },
  tasks: { component: AdminBoards, title: "Boards", group: "Workspace" },
  work: { component: AdminBoards, title: "Boards", group: "Workspace" },
  inbox: { component: AdminUpdates, title: "Updates", group: "Workspace" },
  messages: { component: AdminUpdates, title: "Updates", group: "Workspace" },
  files: { component: AdminBoards, title: "Boards", group: "Workspace" },
  forms: { component: AdminBoards, title: "Boards", group: "Workspace" },
  contracts: { component: AdminBoards, title: "Boards", group: "Workspace" },
};

export default function AdminDashboard() {
  const { tab, sub } = useParams<{ tab?: string; sub?: string }>();
  const key = tab && ROUTES[tab] ? tab : "today";
  const route = ROUTES[key];
  const Component = route.component;

  const adminUser = { name: "Sasha Patel", email: "sasha@oversee.agency", initials: "SP" };

  // initialTab is whatever the route alias forces, OR the URL's :sub segment.
  const initialTab = route.forceSub ?? sub;

  return (
    <RoleShell
      role="admin"
      groups={adminGroups}
      identity={{ name: adminUser.name, sub: adminUser.email, initials: adminUser.initials }}
      breadcrumbs={[{ label: "Admin Console", href: "/admin" }, { label: route.group }, { label: route.title }]}
    >
      {/* Full-width admin canvas */}
      <div className="w-full space-y-2 px-4 py-6 md:px-6 md:py-8 lg:px-8 xl:px-10">
        <Component {...(initialTab ? { initialTab } : {})} />
      </div>
    </RoleShell>
  );
}
