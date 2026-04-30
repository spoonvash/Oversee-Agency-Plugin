import { useEffect, useState } from "react";
import {
  ArrowRight,
  Bell,
  Hash,
  KeyRound,
  Plus,
  Send,
  ShieldCheck,
  Slack as SlackIcon,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useDemoStore } from "@/lib/demo-store";
import { useToast } from "@/hooks/use-toast";
import {
  AccountTabs,
  ExternalLinkRow,
  IntegrationCard,
  PageHeader,
  PageShell,
  SettingsPanel,
  StatusPill,
  SummaryStat,
} from "@/components/shared";

// =================== AUTOMATIONS (DEPRECATED, no longer reachable) ===================
// HighLevel handles all trigger / condition / action workflows for the Oversee
// preview. Automations were removed from the admin sidebar. The component is
// kept exported only so any old import sites keep typechecking.
export function AdminAutomations() {
  return (
    <PageShell>
      <PageHeader
        eyebrow="Workspace"
        title="Automations have moved"
        subtitle="All triggers, conditions, and actions now run in HighLevel. Use Settings → HighLevel to open the workflow builder."
      />
    </PageShell>
  );
}

// =================== TEAM (kept exported for back-compat; rendered inside Settings) ===
export function AdminTeam() {
  return <TeamPanel />;
}

// =================== SETTINGS — 6 tabs ===================
type SettingsTab = "general" | "highlevel" | "woocommerce" | "slack" | "leadsie" | "team";

const TAB_OPTIONS: { value: SettingsTab; label: string; testId: string }[] = [
  { value: "general", label: "General", testId: "tab-settings-general" },
  { value: "highlevel", label: "HighLevel", testId: "tab-settings-highlevel" },
  { value: "woocommerce", label: "WooCommerce", testId: "tab-settings-woocommerce" },
  { value: "slack", label: "Slack", testId: "tab-settings-slack" },
  { value: "leadsie", label: "Leadsie", testId: "tab-settings-leadsie" },
  { value: "team", label: "Team", testId: "tab-settings-team" },
];

const TAB_ALIASES: Record<string, SettingsTab> = {
  general: "general",
  branding: "general",
  billing: "general",
  domains: "general",
  integrations: "general",
  highlevel: "highlevel",
  hl: "highlevel",
  woocommerce: "woocommerce",
  woo: "woocommerce",
  handoffs: "woocommerce",
  slack: "slack",
  notifications: "slack",
  leadsie: "leadsie",
  onboarding: "leadsie",
  access: "leadsie",
  team: "team",
  members: "team",
  automations: "highlevel",
};

type AdminSettingsProps = {
  initialTab?: string;
};

export function AdminSettings({ initialTab: initialTabProp }: AdminSettingsProps = {}) {
  const seed = (initialTabProp && TAB_ALIASES[initialTabProp]) || "general";
  const [tab, setTab] = useState<SettingsTab>(seed);
  useEffect(() => {
    setTab(seed);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialTabProp]);

  return (
    <PageShell>
      <PageHeader
        eyebrow="Workspace"
        title="Settings"
        subtitle="Workspace configuration. Commerce runs in WooCommerce. Conversations and automations run in HighLevel."
        testId="page-header-settings"
      />

      <AccountTabs<SettingsTab>
        value={tab}
        onChange={setTab}
        options={TAB_OPTIONS}
      />

      {tab === "general" && <GeneralPanel />}
      {tab === "highlevel" && <HighLevelPanel />}
      {tab === "woocommerce" && <WooCommercePanel />}
      {tab === "slack" && <SlackPanel />}
      {tab === "leadsie" && <LeadsiePanel />}
      {tab === "team" && <TeamPanel />}
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// General — branding, plan, domain, integrations
// ─────────────────────────────────────────────────────────────────────────────
// Saved branding defaults. State lives in GeneralPanel and reverts/persists on Reset/Save.
const DEFAULT_BRANDING = {
  name: "Oversee Agency",
  subdomain: "dashboard",
  loginRoute: "/dashboard/login/",
  accent: "#ff8201",
};

function GeneralPanel() {
  const { integrations } = useDemoStore();
  const { toast } = useToast();
  const [testing, setTesting] = useState<string | null>(null);
  const [savedBranding, setSavedBranding] = useState(DEFAULT_BRANDING);
  const [branding, setBranding] = useState(DEFAULT_BRANDING);
  const isDirty =
    branding.name !== savedBranding.name ||
    branding.subdomain !== savedBranding.subdomain ||
    branding.loginRoute !== savedBranding.loginRoute ||
    branding.accent !== savedBranding.accent;

  return (
    <div className="space-y-5">
      <SettingsPanel
        title="Workspace branding"
        hint="Name, subdomain, login route, and primary accent."
        testId="settings-branding"
        footer={
          <>
            <Button
              variant="outline"
              size="sm"
              disabled={!isDirty}
              onClick={() => {
                setBranding(savedBranding);
                toast({ title: "Branding reset", description: "Reverted to last saved values." });
              }}
              data-testid="button-branding-reset"
            >
              Reset
            </Button>
            <Button
              size="sm"
              disabled={!isDirty}
              onClick={() => {
                setSavedBranding(branding);
                toast({ title: "Branding saved", description: "Workspace branding updated." });
              }}
              data-testid="button-branding-save"
            >
              Save changes
            </Button>
          </>
        }
      >
        <div className="grid gap-4 md:grid-cols-2">
          <ControlledField
            label="Workspace name"
            value={branding.name}
            onChange={(v) => setBranding((p) => ({ ...p, name: v }))}
            testId="input-workspace-name"
          />
          <ControlledField
            label="Portal subdomain"
            value={branding.subdomain}
            onChange={(v) => setBranding((p) => ({ ...p, subdomain: v }))}
            suffix=".oversee.agency"
            testId="input-subdomain"
          />
          <ControlledField
            label="Login route"
            value={branding.loginRoute}
            onChange={(v) => setBranding((p) => ({ ...p, loginRoute: v }))}
            testId="input-login-route"
          />
          <ControlledField
            label="Primary accent"
            value={branding.accent}
            onChange={(v) => setBranding((p) => ({ ...p, accent: v }))}
            testId="input-accent"
          />
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Plan & billing"
        hint="Manage your Oversee subscription and payment method."
        testId="settings-plan"
      >
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 p-4">
          <div>
            <p className="text-sm font-semibold">Oversee · Agency plan</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Up to 50 client portals · unlimited users · $499/mo</p>
          </div>
          <Button
            variant="outline"
            size="sm"
            asChild
            data-testid="button-manage-plan"
          >
            <a
              href="https://billing.stripe.com/p/login/test_oversee_agency"
              target="_blank"
              rel="noopener noreferrer"
              className="gap-1.5"
            >
              Manage plan
            </a>
          </Button>
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Custom domain"
        hint="Cloudflare-managed SSL. Updates propagate within minutes."
        testId="settings-domain"
      >
        <div className="max-w-md">
          <Field label="Domain" defaultValue="dashboard.oversee.agency" testId="input-domain" />
          <p className="mt-2 text-[11px] text-muted-foreground">SSL provisioned via Cloudflare · last verified 2h ago</p>
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Integrations"
        hint="Service connections. Test runs a no-op ping against each."
        testId="settings-integrations"
      >
        <div className="space-y-2">
          {integrations.map((i) => (
            <IntegrationCard
              key={i.id}
              icon={<ShieldCheck className="size-4" />}
              name={i.name}
              description={i.detail}
              connected={i.status === "connected"}
              state={
                i.status === "connected"
                  ? { label: "Connected", tone: "success" }
                  : i.status === "needs-attention"
                    ? { label: "Needs attention", tone: "warning" }
                    : { label: "Disconnected", tone: "danger" }
              }
              primaryAction={{
                label: testing === i.id ? "Testing…" : "Test",
                onClick: () => {
                  setTesting(i.id);
                  setTimeout(() => {
                    setTesting(null);
                    toast({
                      title: i.status === "connected" ? "Connection OK" : "Connection failed",
                      description:
                        i.status === "connected"
                          ? `${i.name}: ping returned 200`
                          : `${i.name}: ${i.detail}`,
                    });
                  }, 600);
                },
                testId: `test-integration-${i.id}`,
              }}
              testId={`integration-${i.id}`}
            />
          ))}
        </div>
      </SettingsPanel>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// HighLevel
// ─────────────────────────────────────────────────────────────────────────────
function HighLevelPanel() {
  const { toast } = useToast();
  return (
    <div className="space-y-5">
      <SettingsPanel
        title="HighLevel workspace"
        hint="All conversations, automations, calendars, and reports run from your HighLevel sub-account. Oversee surfaces them via SSO magic-link wrappers."
        status={<StatusPill tone="success">Connected</StatusPill>}
        testId="settings-highlevel"
        footer={
          <Button
            variant="outline"
            size="sm"
            onClick={() => toast({ title: "Opening HighLevel", description: "SSO magic-link to workflows." })}
            data-testid="button-manage-hl"
          >
            Manage in HighLevel
          </Button>
        }
      >
        <div className="grid gap-3 md:grid-cols-3">
          <SummaryStat tone="info" label="Conversations" value="42 open" hint="SMS · email · chat" />
          <SummaryStat tone="primary" label="Workflows" value="18 active" hint="Triggers run by HighLevel" />
          <SummaryStat label="Calendars" value="3 connected" hint="Discovery, review, kickoff" />
        </div>
        <p className="mt-4 text-[11px] text-muted-foreground">
          Automations have moved to HighLevel. Manage all triggers, conditions, and actions there.
        </p>
      </SettingsPanel>

      <SettingsPanel
        title="Quick shortcuts"
        hint="Open HighLevel directly to the screen you need."
        testId="settings-highlevel-shortcuts"
      >
        <div className="grid gap-2 md:grid-cols-2">
          {[
            { label: "Conversations", href: "https://app.gohighlevel.com/conversations" },
            { label: "Pipelines", href: "https://app.gohighlevel.com/opportunities" },
            { label: "Workflows", href: "https://app.gohighlevel.com/automation" },
            { label: "Calendars", href: "https://app.gohighlevel.com/calendars" },
          ].map((l) => (
            <ExternalLinkRow
              key={l.label}
              label={l.label}
              href={l.href}
              testId={`hl-link-${l.label.toLowerCase()}`}
            />
          ))}
        </div>
      </SettingsPanel>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// WooCommerce
// ─────────────────────────────────────────────────────────────────────────────
function WooCommercePanel() {
  return (
    <div className="space-y-5">
      <SettingsPanel
        title="WooCommerce store"
        hint="All commerce — product catalog, cart, checkout, subscription billing — runs in your WooCommerce store. Oversee surfaces purchasable services and reads order status; transactions complete in WooCommerce."
        status={<StatusPill tone="success">Connected</StatusPill>}
        testId="settings-woocommerce"
        footer={
          <a
            href="https://example.com/wp-admin/edit.php?post_type=product"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
            data-testid="button-open-woo-admin"
          >
            Open WooCommerce admin
            <ArrowRight className="size-4" />
          </a>
        }
      >
        <div className="grid gap-3 md:grid-cols-3">
          <SummaryStat tone="info" label="Products" value="25 active" hint="Visible in Services" />
          <SummaryStat tone="primary" label="Orders" value="143 this month" hint="Synced from Woo" />
          <SummaryStat tone="success" label="Subscriptions" value="62 active" hint="Auto-renewing" />
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Quick shortcuts"
        hint="Jump straight into WooCommerce."
        testId="settings-woocommerce-shortcuts"
      >
        <div className="grid gap-2 md:grid-cols-2">
          {[
            { label: "Products", href: "https://example.com/wp-admin/edit.php?post_type=product" },
            { label: "Orders", href: "https://example.com/wp-admin/edit.php?post_type=shop_order" },
            { label: "Subscriptions", href: "https://example.com/wp-admin/admin.php?page=wc-orders--shop_subscription" },
            { label: "Settings", href: "https://example.com/wp-admin/admin.php?page=wc-settings" },
          ].map((l) => (
            <ExternalLinkRow
              key={l.label}
              label={l.label}
              href={l.href}
              testId={`woo-link-${l.label.toLowerCase()}`}
            />
          ))}
        </div>
      </SettingsPanel>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Team
// ─────────────────────────────────────────────────────────────────────────────
function TeamPanel() {
  const { team } = useDemoStore();
  const { toast } = useToast();
  const [inviteOpen, setInviteOpen] = useState(false);
  const [inviteEmail, setInviteEmail] = useState("");
  // Edit dialog: working copy + role override map (preview only).
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<{ name: string; email: string; role: string }>({
    name: "",
    email: "",
    role: "Admin",
  });
  const [overrides, setOverrides] = useState<Record<string, { name: string; email: string; role: string }>>({});
  const editingMember = editingId ? team.find((m) => m.id === editingId) : null;
  const TEAM_ROLES = ["Owner", "Admin", "PM", "Designer", "Strategist"];

  return (
    <div className="space-y-4">
      <SettingsPanel
        title="Members"
        hint="Roles and access. Invites are sent through WordPress."
        testId="settings-team"
        trailing={
          <Button
            size="sm"
            className="gap-2"
            onClick={() => setInviteOpen(true)}
            data-testid="button-invite-team"
          >
            <Plus className="size-4" /> Invite
          </Button>
        }
      >
        <div className="-mx-1 divide-y divide-border rounded-lg border border-border bg-card">
          {team.map((base) => {
            const m = { ...base, ...(overrides[base.id] ?? {}) };
            return (
              <div
                key={m.id}
                className="flex flex-wrap items-center gap-3 px-4 py-3"
                data-testid={`row-team-${m.id}`}
              >
                <span
                  className={`grid size-9 shrink-0 place-items-center rounded-md text-[11px] font-semibold text-white ${base.avatarColor}`}
                  aria-hidden
                >
                  {m.name
                    .split(" ")
                    .map((s) => s[0])
                    .join("")}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold leading-tight">{m.name}</p>
                  <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{m.email}</p>
                </div>
                <Badge variant="outline" className="shrink-0 text-[10px]">
                  {m.role}
                </Badge>
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-7 text-xs"
                  onClick={() => {
                    setEditingId(m.id);
                    setEditForm({ name: m.name, email: m.email, role: m.role });
                  }}
                  data-testid={`button-edit-member-${m.id}`}
                >
                  Edit
                </Button>
              </div>
            );
          })}
        </div>
      </SettingsPanel>

      <Dialog open={!!editingId} onOpenChange={(v) => !v && setEditingId(null)}>
        <DialogContent data-testid="dialog-edit-member">
          <DialogHeader>
            <DialogTitle>Edit member</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-1.5">
              <label className="text-xs font-medium">Name</label>
              <Input
                value={editForm.name}
                onChange={(e) => setEditForm((p) => ({ ...p, name: e.target.value }))}
                data-testid="input-edit-member-name"
              />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-medium">Email</label>
              <Input
                type="email"
                value={editForm.email}
                onChange={(e) => setEditForm((p) => ({ ...p, email: e.target.value }))}
                data-testid="input-edit-member-email"
              />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-medium">Role</label>
              <div className="flex flex-wrap gap-1.5">
                {TEAM_ROLES.map((role) => (
                  <Button
                    key={role}
                    type="button"
                    size="sm"
                    variant={editForm.role === role ? "default" : "outline"}
                    onClick={() => setEditForm((p) => ({ ...p, role }))}
                    data-testid={`button-edit-member-role-${role.toLowerCase()}`}
                  >
                    {role}
                  </Button>
                ))}
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditingId(null)} data-testid="button-cancel-edit-member">
              Cancel
            </Button>
            <Button
              disabled={!editForm.name.trim() || !editForm.email.trim()}
              onClick={() => {
                if (!editingId || !editingMember) return;
                setOverrides((p) => ({ ...p, [editingId]: editForm }));
                toast({ title: "Member updated", description: `${editForm.name} — ${editForm.role}` });
                setEditingId(null);
              }}
              data-testid="button-confirm-edit-member"
            >
              Save changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Invite teammate</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Input
              value={inviteEmail}
              onChange={(e) => setInviteEmail(e.target.value)}
              placeholder="name@oversee.agency"
              data-testid="input-invite-email"
            />
            <p className="text-[11px] text-muted-foreground">
              Sends a WordPress invite link with role pre-set. They&rsquo;ll set their password on first login.
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setInviteOpen(false)}>
              Cancel
            </Button>
            <Button
              disabled={!inviteEmail.trim()}
              onClick={() => {
                setInviteOpen(false);
                const email = inviteEmail;
                setInviteEmail("");
                toast({ title: "Invite sent", description: email });
              }}
              data-testid="button-confirm-invite"
            >
              Send invite
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Slack — PREVIEW ONLY. No real OAuth, no real chat.postMessage.
// All state is local React; "Connect" flips a flag, "Test" appends to a mock log.
// ─────────────────────────────────────────────────────────────────────────────
type SlackEventKey = "client-update" | "project-approved" | "failed-payment";

const SLACK_EVENTS: { key: SlackEventKey; label: string; description: string; defaultChannel: string }[] = [
  {
    key: "client-update",
    label: "New client update",
    description: "Client posts on a board item or replies to a thread.",
    defaultChannel: "#client-updates",
  },
  {
    key: "project-approved",
    label: "Project approved",
    description: "Client approves a deliverable or marks an item Approved.",
    defaultChannel: "#approvals",
  },
  {
    key: "failed-payment",
    label: "Failed payment",
    description: "WooCommerce subscription renewal fails or invoice goes past-due.",
    defaultChannel: "#billing-alerts",
  },
];

const SLACK_SCOPES = [
  { scope: "chat:write", reason: "Post project and item updates to channels." },
  { scope: "chat:write.public", reason: "Post to public channels Oversee hasn't been invited to." },
  { scope: "channels:read", reason: "List channels so admins can pick where notifications go." },
  { scope: "channels:join", reason: "Auto-join channels when an admin maps a new event." },
  { scope: "incoming-webhook", reason: "Per-channel webhook fallback for one-way notifications." },
  { scope: "commands", reason: "Optional slash commands like /oversee status to query board state." },
];

function SlackPanel() {
  const { toast } = useToast();
  const { boards } = useDemoStore();
  const [connected, setConnected] = useState(true);
  const [previewMode, setPreviewMode] = useState(true);
  const [connectOpen, setConnectOpen] = useState(false);
  const [channelMap, setChannelMap] = useState<Record<SlackEventKey, string>>(() => ({
    "client-update": "#client-updates",
    "project-approved": "#approvals",
    "failed-payment": "#billing-alerts",
  }));
  const [perItemEnabled, setPerItemEnabled] = useState(true);
  const [testLog, setTestLog] = useState<{ id: string; at: string; channel: string; text: string }[]>([]);

  const sendTest = (channel: string, text: string) => {
    setTestLog((prev) =>
      [
        {
          id: `t_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
          at: new Date().toISOString(),
          channel,
          text,
        },
        ...prev,
      ].slice(0, 12),
    );
    toast({
      title: "Mock notification queued",
      description: `Preview only — nothing was sent to ${channel}. Live mode would call slack_send_message on the connected workspace.`,
    });
  };

  return (
    <div className="space-y-5">
      <IntegrationCard
        icon={<SlackIcon className="size-5" />}
        name="Slack workspace"
        description="Send Oversee notifications — client updates, approvals, billing alerts — into your team's Slack. Two-way replies arrive via the Slack Events API."
        connected={connected}
        meta={
          <>
            Connector: <span className="font-mono">slack_direct</span>
            {connected && (
              <>
                {" · "}
                <span className={previewMode ? "text-amber-700 dark:text-amber-300" : "text-orange-700 dark:text-orange-300"}>
                  {previewMode ? "Preview mode" : "Live mode"}
                </span>
              </>
            )}
          </>
        }
        primaryAction={
          connected
            ? {
                label: "Disconnect",
                onClick: () => {
                  setConnected(false);
                  setPreviewMode(true);
                  toast({
                    title: "Slack disconnected (preview)",
                    description: "This only resets the in-app state. The workspace connector is unchanged.",
                  });
                },
                testId: "button-disconnect-slack",
              }
            : {
                label: "Connect Slack",
                onClick: () => setConnectOpen(true),
                testId: "button-connect-slack",
              }
        }
        testId="settings-slack-connection"
      />

      {connected && (
        <SettingsPanel
          title="Preview mode"
          hint="On: every Test button only updates this UI and shows a toast. Off: tests would call slack_send_message against the live workspace. Keep this on while validating the integration."
          testId="settings-slack-preview-mode"
          trailing={
            <Switch
              checked={previewMode}
              onCheckedChange={(v) => {
                setPreviewMode(v);
                toast({
                  title: v ? "Preview mode on" : "Preview mode off",
                  description: v
                    ? "Test actions will only mutate the in-app log."
                    : "Production tests would now hit Slack — confirm before sending.",
                });
              }}
              data-testid="toggle-slack-preview-mode"
            />
          }
        >
          <p className="text-[11px] text-muted-foreground">
            The Slack connector is authorized at the workspace level. This screen lets you map events,
            run safe preview tests, and review what production behavior will look like before flipping
            preview mode off.
          </p>
        </SettingsPanel>
      )}

      <SettingsPanel
        title="Channel mapping"
        hint="Where each event type lands in Slack. Send a test message to verify in production."
        testId="settings-slack-channels"
      >
        <div className="space-y-2">
          {SLACK_EVENTS.map((evt) => (
            <div
              key={evt.key}
              className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3"
            >
              <div className="flex min-w-0 flex-1 items-start gap-3">
                <span className="grid size-8 shrink-0 place-items-center rounded-md border border-border bg-muted">
                  <Bell className="size-3.5" />
                </span>
                <div className="min-w-0">
                  <p className="text-sm font-medium">{evt.label}</p>
                  <p className="text-[11px] text-muted-foreground">{evt.description}</p>
                </div>
              </div>
              <div className="relative">
                <Hash className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={channelMap[evt.key].replace(/^#/, "")}
                  onChange={(e) =>
                    setChannelMap((m) => ({ ...m, [evt.key]: "#" + e.target.value.replace(/^#/, "") }))
                  }
                  placeholder={evt.defaultChannel.replace(/^#/, "")}
                  className="h-8 w-44 pl-7 text-xs"
                  data-testid={`select-slack-channel-${evt.key}`}
                />
              </div>
              <Button
                variant="outline"
                size="sm"
                className="h-8 gap-1.5 text-xs"
                onClick={() =>
                  sendTest(channelMap[evt.key], `[${evt.label}] Test notification from Oversee admin.`)
                }
                disabled={!connected}
                data-testid={`button-test-slack-${evt.key}`}
              >
                <Send className="size-3.5" /> Test
              </Button>
            </div>
          ))}
          {!connected && (
            <p className="text-[11px] text-muted-foreground">Connect Slack to enable test messages.</p>
          )}
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Per-project notifications"
        hint="Send item-level updates from each board to a dedicated channel. Configure the channel on the board's Edit screen."
        testId="settings-slack-per-board"
      >
        <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3">
          <div className="min-w-0">
            <p className="text-sm font-medium">Send item updates to Slack</p>
            <p className="text-[11px] text-muted-foreground">
              When on, each board with a Slack channel set on its Edit form will mirror new updates to that channel.
            </p>
          </div>
          <Switch
            checked={perItemEnabled}
            onCheckedChange={setPerItemEnabled}
            disabled={!connected}
            data-testid="toggle-slack-per-item"
          />
        </div>
        {boards.some((b) => b.slackChannel) && (
          <div className="mt-3 space-y-1.5 text-[11px] text-muted-foreground">
            <p className="font-medium text-foreground">Boards with channels set</p>
            <ul className="list-disc space-y-0.5 pl-5">
              {boards
                .filter((b) => b.slackChannel)
                .map((b) => (
                  <li key={b.id} data-testid={`text-board-slack-${b.id}`}>
                    {b.name} — <span className="font-mono">{b.slackChannel}</span>
                  </li>
                ))}
            </ul>
          </div>
        )}
      </SettingsPanel>

      <SettingsPanel
        title="Test notification log"
        hint="Preview-only record of every test message you've queued in this session."
        testId="settings-slack-log"
      >
        {testLog.length === 0 ? (
          <div className="rounded-lg border border-dashed border-border bg-muted/30 px-4 py-8 text-center text-xs text-muted-foreground">
            No test notifications yet. Send one from a channel mapping above.
          </div>
        ) : (
          <ol className="space-y-2">
            {testLog.map((l) => (
              <li
                key={l.id}
                className="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-xs"
                data-testid={`text-slack-log-${l.id}`}
              >
                <span className="grid size-7 shrink-0 place-items-center rounded-md border border-border bg-muted">
                  <Send className="size-3" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-baseline gap-2">
                    <span className="font-mono text-[11px] font-medium">{l.channel}</span>
                    <span className="text-[10px] tabular-nums text-muted-foreground">
                      {new Date(l.at).toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })}
                    </span>
                  </div>
                  <p className="mt-0.5 truncate text-foreground">{l.text}</p>
                </div>
              </li>
            ))}
          </ol>
        )}
      </SettingsPanel>

      <SettingsPanel
        title="What the connected Slack can do"
        hint="The slack_direct connector is authorized. Here's what each capability maps to in this UI."
        testId="settings-slack-production-notes"
      >
        <ul className="space-y-2 text-xs text-muted-foreground">
          <li>
            <span className="font-medium text-foreground">Channel search & mapping.</span>{" "}
            <span className="font-mono">slack_search_channels</span> populates the channel pickers above so
            admins can route each event type to the right channel without typing IDs.
          </li>
          <li>
            <span className="font-medium text-foreground">Drafted messages, admin-approved.</span>{" "}
            <span className="font-mono">slack_send_message_draft</span> stages an outbound notification.
            Nothing leaves Oversee until an admin reviews and confirms.
          </li>
          <li>
            <span className="font-medium text-foreground">Live notifications.</span> Once an admin turns
            preview mode off and saves a mapping, <span className="font-mono">slack_send_message</span>{" "}
            (or <span className="font-mono">slack_schedule_message</span> for delayed sends) posts the
            payload to the mapped channel.
          </li>
          <li>
            <span className="font-medium text-foreground">Read-only context.</span>{" "}
            <span className="font-mono">slack_read_channel</span> and{" "}
            <span className="font-mono">slack_read_thread</span> let Oversee pull recent thread context into
            an item's Updates feed where authorized — useful for stitching a Slack thread back to a board
            item without leaving the workspace.
          </li>
          <li>
            <span className="font-medium text-foreground">User lookup.</span>{" "}
            <span className="font-mono">slack_search_users</span> resolves @mentions and assignees so
            Oversee owners can be notified directly in DM when an item changes status.
          </li>
          <li>
            <span className="font-medium text-foreground">Safety rail.</span> While preview mode is on,
            none of these tools are called from this screen — every Test button only mutates the in-app
            log and shows a toast.
          </li>
        </ul>
      </SettingsPanel>

      <Dialog open={connectOpen} onOpenChange={setConnectOpen}>
        <DialogContent data-testid="dialog-connect-slack">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <SlackIcon className="size-4" /> Reconnect Slack
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 text-sm">
            <p className="text-muted-foreground">
              The Slack workspace connector is already authorized. This flow is shown for transparency —
              it lists exactly which scopes the connector relies on. Reconnecting only resets the in-app
              state; it does not reinstall anything.
            </p>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Scopes Oversee uses
              </p>
              <ul className="mt-2 space-y-1.5">
                {SLACK_SCOPES.map((s) => (
                  <li key={s.scope} className="flex items-start gap-2 text-xs">
                    <span className="mt-0.5 inline-flex shrink-0 items-center rounded-md border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">
                      {s.scope}
                    </span>
                    <span className="text-muted-foreground">{s.reason}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setConnectOpen(false)}
              data-testid="button-cancel-connect-slack"
            >
              Cancel
            </Button>
            <Button
              onClick={() => {
                setConnected(true);
                setPreviewMode(true);
                setConnectOpen(false);
                toast({
                  title: "Slack reconnected",
                  description: "Preview mode is on. No real messages will be sent until an admin turns it off.",
                });
              }}
              data-testid="button-confirm-connect-slack"
            >
              Reconnect
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Leadsie — agency onboarding & client access requests
// ─────────────────────────────────────────────────────────────────────────────
function LeadsiePanel() {
  const { leadsieSettings, updateLeadsieSettings, onboardingPlans } = useDemoStore();
  const { toast } = useToast();
  const [agencySlug, setAgencySlug] = useState(leadsieSettings.agencySlug);
  const [embed, setEmbed] = useState(leadsieSettings.embedSnippet);
  const [webhook, setWebhook] = useState(leadsieSettings.webhookUrl);
  const [connecting, setConnecting] = useState(false);

  const totalAccessSteps = onboardingPlans.flatMap((p) =>
    p.steps.filter((s) => s.kind === "leadsie-access"),
  ).length;
  const completedAccess = onboardingPlans.flatMap((p) =>
    p.steps.filter((s) => s.kind === "leadsie-access" && s.status === "complete"),
  ).length;

  return (
    <div className="space-y-5">
      <IntegrationCard
        icon={<KeyRound className="size-5" />}
        name="Leadsie agency account"
        description="Leadsie generates one-click access links so clients can grant Google, Meta, and WordPress permissions without sharing passwords. Embedded inside the Oversee onboarding checklist."
        connected={leadsieSettings.connected}
        state={
          leadsieSettings.connected
            ? { label: "Connected", tone: "success" }
            : { label: "Setup required", tone: "warning" }
        }
        primaryAction={
          !leadsieSettings.connected
            ? {
                label: connecting ? "Connecting…" : "Connect Leadsie",
                onClick: () => {
                  setConnecting(true);
                  setTimeout(() => {
                    updateLeadsieSettings({ connected: true });
                    setConnecting(false);
                    toast({
                      title: "Leadsie connected (preview)",
                      description: "In production this kicks off the OAuth handshake.",
                    });
                  }, 700);
                },
                testId: "button-leadsie-connect",
              }
            : undefined
        }
        testId="settings-leadsie-status"
      />

      {!leadsieSettings.connected && (
        <SettingsPanel
          title="No Leadsie account yet"
          hint="Until you connect, request links shown to clients are simulated for preview. Sign up at leadsie.com (Agency plan), then paste the embed snippet below to go live."
          testId="settings-leadsie-setup-hint"
          footer={
            <a
              href="https://www.leadsie.com"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium hover:bg-muted/40"
              data-testid="link-leadsie-signup"
            >
              Open leadsie.com <ArrowRight className="size-3" />
            </a>
          }
        >
          <p className="text-[11px] text-muted-foreground">
            Once connected, Oversee will generate Leadsie request URLs for every onboarding plan
            access step automatically.
          </p>
        </SettingsPanel>
      )}

      <SettingsPanel
        title="Access request volume"
        hint="A snapshot of Leadsie activity across active onboarding plans."
        testId="settings-leadsie-volume"
      >
        <div className="grid gap-3 md:grid-cols-3">
          <SummaryStat
            tone="info"
            label="Active templates"
            value={`${leadsieSettings.defaultTemplates.length} platforms`}
            hint="Pre-configured permission scopes"
          />
          <SummaryStat
            tone="primary"
            label="Open requests"
            value={`${totalAccessSteps - completedAccess} / ${totalAccessSteps}`}
            hint="Across active onboarding plans"
          />
          <SummaryStat
            tone="success"
            label="Approved"
            value={`${completedAccess} access${completedAccess === 1 ? "" : "es"}`}
            hint="Granted by clients"
          />
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Configuration"
        hint="Identify your agency and where Leadsie should ping you when clients respond."
        testId="settings-leadsie-config"
        footer={
          <>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setAgencySlug(leadsieSettings.agencySlug);
                setEmbed(leadsieSettings.embedSnippet);
                setWebhook(leadsieSettings.webhookUrl);
                toast({ title: "Reverted unsaved changes" });
              }}
              data-testid="button-leadsie-reset"
            >
              Reset
            </Button>
            <Button
              size="sm"
              onClick={() => {
                updateLeadsieSettings({
                  agencySlug,
                  embedSnippet: embed,
                  webhookUrl: webhook,
                });
                toast({ title: "Leadsie settings saved" });
              }}
              data-testid="button-leadsie-save"
            >
              Save changes
            </Button>
          </>
        }
      >
        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <label className="text-xs font-medium">Agency slug</label>
            <div className="flex items-center gap-2">
              <Input
                value={agencySlug}
                onChange={(e) => setAgencySlug(e.target.value)}
                className="h-9 text-sm"
                data-testid="input-leadsie-slug"
              />
              <span className="shrink-0 text-xs text-muted-foreground">.leadsie.com</span>
            </div>
            <p className="text-[11px] text-muted-foreground">
              Used to build request URLs like{" "}
              <code className="text-foreground/80">app.leadsie.com/connect/{agencySlug}-…</code>.
            </p>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium">Webhook URL</label>
            <Input
              value={webhook}
              onChange={(e) => setWebhook(e.target.value)}
              className="h-9 text-sm"
              data-testid="input-leadsie-webhook"
            />
            <p className="text-[11px] text-muted-foreground">
              Leadsie pings this when a client opens, signs in, approves, or revokes.
            </p>
          </div>
        </div>
        <div className="mt-4 space-y-1.5">
          <label className="text-xs font-medium">Embed snippet</label>
          <Textarea
            value={embed}
            onChange={(e) => setEmbed(e.target.value)}
            rows={3}
            className="font-mono text-xs"
            data-testid="input-leadsie-embed"
            placeholder="<!-- Paste embed code from Leadsie → Settings → Embed -->"
          />
          <p className="text-[11px] text-muted-foreground">
            Pulled from Leadsie → Settings → Embed. We render this inside client onboarding cards.
          </p>
        </div>
      </SettingsPanel>

      <SettingsPanel
        title="Default permission templates"
        hint="What we ask for on each platform when generating a Leadsie link."
        testId="settings-leadsie-templates"
      >
        <ul className="divide-y divide-border rounded-lg border border-border bg-card">
          {leadsieSettings.defaultTemplates.map((t) => (
            <li
              key={t.platform}
              className="flex items-center justify-between gap-3 px-4 py-2.5"
              data-testid={`template-${t.platform}`}
            >
              <div className="min-w-0">
                <p className="text-sm font-medium capitalize">{t.platform.replace(/-/g, " ")}</p>
                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{t.label}</p>
              </div>
              <Badge variant="outline" className="shrink-0 text-[10px]">
                Default
              </Badge>
            </li>
          ))}
        </ul>
        <p className="mt-3 text-[11px] text-muted-foreground">
          Permission scopes can be customized per service after Leadsie is connected.
        </p>
      </SettingsPanel>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────
function Field({
  label,
  defaultValue,
  suffix,
  testId,
}: {
  label: string;
  defaultValue?: string;
  suffix?: string;
  testId?: string;
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium">{label}</label>
      <div className="flex items-center gap-2">
        <Input defaultValue={defaultValue} className="h-9 text-sm" data-testid={testId} />
        {suffix && <span className="shrink-0 text-xs text-muted-foreground">{suffix}</span>}
      </div>
    </div>
  );
}

function ControlledField({
  label,
  value,
  onChange,
  suffix,
  testId,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  suffix?: string;
  testId?: string;
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium">{label}</label>
      <div className="flex items-center gap-2">
        <Input
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 text-sm"
          data-testid={testId}
        />
        {suffix && <span className="shrink-0 text-xs text-muted-foreground">{suffix}</span>}
      </div>
    </div>
  );
}

