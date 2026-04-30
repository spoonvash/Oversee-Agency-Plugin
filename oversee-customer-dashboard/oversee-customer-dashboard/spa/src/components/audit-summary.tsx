// Preview QA / audit summary modal. Lists every route checked and known
// preview limitations. Surfaced via the User menu in the role shell so the
// reviewer can verify scope at a glance.

import { useState } from "react";
import { CheckCircle2, ClipboardList, ExternalLink, ShieldAlert } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { PROD_ACTIONS, type PreviewAction } from "@/lib/preview-actions";

const ROUTES_CHECKED: { area: "client" | "admin"; route: string; notes: string }[] = [
  // Client — 6 nav destinations (Home, Onboarding, My Work, Files, Services, Account)
  { area: "client", route: "/client", notes: "Home — single 'Here’s what Oversee needs from you' headline + vertical action list. No widgets, no charts, no shop CTA. Empty state: 'You’re all caught up.'" },
  { area: "client", route: "/client/onboarding", notes: "Onboarding — Leadsie access requests + setup steps for each active service." },
  { area: "client", route: "/client/work", notes: "My Work — Action list (default) groups: Needs your attention / In progress with Oversee / Waiting for Oversee / Completed. Optional Board view toggle exposes the Monday board." },
  { area: "client", route: "/client/files", notes: "Files — file approvals and shared deliverables across all boards." },
  { area: "client", route: "/client/updates", notes: "Hidden legacy alias — old /client/updates deep links still resolve. Notifications now live in the top-right bell only; no sidebar entry." },
  { area: "client", route: "/client/account", notes: "Account — WooCommerce handoff list: Manage subscriptions, Update payment method, View invoices/orders, Browse more services. Each card labelled 'Opens WooCommerce'. No custom shop, cart, or checkout." },
  { area: "client", route: "/client/shop → /client/account", notes: "Backwards-compat alias — deep links to the old Shop now render Account so existing emails / links keep working with no broken pages." },
  { area: "client", route: "/client/billing → /client/account", notes: "Backwards-compat alias — deep links to old Billing route also render the WooCommerce handoff Account page." },
  // Admin — 4 nav destinations (Today, Boards, Clients, Settings)
  { area: "admin", route: "/admin", notes: "Today — Start here panel surfaces the single highest-priority action; metric tiles route to Boards / Files / Clients / Access. No header Inbox button." },
  { area: "admin", route: "/admin/boards", notes: "Boards grid — cards open the workflow. New board opens Create-board wizard with template cards: Website Build, SEO Monthly, CRM Setup, Video Commercial." },
  { area: "admin", route: "/admin/clients", notes: "Clients CRM — Overview / Work / Messages / Account status tabs. Messages tab points back into the relevant board (no second notification surface)." },
  { area: "admin", route: "/admin/updates", notes: "Hidden legacy alias — old /admin/updates and /admin/inbox deep links still resolve. Notifications now live in the top-right bell only; no sidebar entry." },
  { area: "admin", route: "/admin/commerce", notes: "Combined commerce — orders, subscriptions, templates, catalog, payments tabs preserved." },
  { area: "admin", route: "/admin/settings", notes: "Integrations, HighLevel, Branding, Plan & billing, Domains, Team." },
];

const CLICKABILITY_RULES: { rule: string; rationale: string }[] = [
  { rule: "Primary buttons are filled orange and labelled with a verb.", rationale: "Orange is reserved exclusively for the single primary action per surface; never used for borders or hover." },
  { rule: "Secondary buttons use a thin zinc-200/800 border with no fill.", rationale: "Visually distinct from primary, but still obviously a button." },
  { rule: "Ghost styling is for icon menus and three-dot kebabs only.", rationale: "Reduces ambient clickability noise on a dense board." },
  { rule: "Status pills look interactive only when admin can change them.", rationale: "Admin pills include a chevron and hover state. Client pills are static <span> with cursor-default." },
  { rule: "Counts (files, updates) are plain text, not buttons, unless they truly route somewhere.", rationale: "Removes phantom click targets. Numbers are informational." },
  { rule: "Item rows are entirely clickable; the whole row is the affordance.", rationale: "Hover background + cursor-pointer mirrors Monday’s row interaction." },
  { rule: "Cards hover with zinc-300 border, not orange.", rationale: "Avoids 'orange-everywhere' AI aesthetic; reserves orange for true CTAs." },
  { rule: "Internal notes are visually distinct: amber border-left and amber-tinted background plus an 'Internal' badge.", rationale: "A client can never mistake an admin internal note for a public update." },
  { rule: "Approval bar is a heavy banner with a circled icon, headline, body line, and two clearly-labeled buttons.", rationale: "Approvals are the highest-stakes action; the bar must be unmissable." },
  { rule: "Files live inside an item or board — never as a top-level destination.", rationale: "Files are context to work, not work themselves; this matches Monday." },
];

const REMOVED_FROM_NAV = [
  "Client: Shop / Browse Services as a custom catalog (handed off to WooCommerce, link lives under Account)",
  "Client: Billing / Subscriptions / Invoices as custom UI (handed off to WooCommerce via Account cards)",
  "Client: Cart / Checkout flow on the customer side entirely (WooCommerce is the system of record)",
  "Client: top-level Files page (now lives inside item Files tab and board Files view)",
  "Client: top-level Messages / Updates pages (notifications now live exclusively in the top-right bell; legacy /updates URL still resolves)",
  "Client: Forms / Contracts / Schedule / Reports / Reviews top-level pages (still reachable via deep link, not in 4-item nav)",
  "Admin: top-level Files & Approvals page (approvals surface inside item detail; files inside board)",
  "Admin: top-level Clients page (folded into Boards — each board is per-client)",
  "Admin: Projects / Tasks / Inbox / Updates / Orders / Subscriptions / Templates / Catalog / Payments / Team — all collapsed under Boards / Clients / Settings (still reachable via deep link). Notifications live exclusively in the top-right bell.",
];

const LIMITATIONS = [
  "No real authentication — preview opens directly into either dashboard via the role selector.",
  "No real backend persistence — all mutations live in React state for the session (no localStorage / cookies / IndexedDB).",
  "HighLevel surfaces (Inbox, Schedule, Reviews, Reports) render via SSO magic-link wrappers with skeleton-then-loaded states; the actual HighLevel iframe is not embedded in this preview.",
  "Automations have been removed from the admin sidebar — production runs all triggers/conditions/actions in HighLevel.",
  "Stripe / Resend / Bunny.net integrations are simulated; toasts describe the production behavior.",
  "Variation pricing is calculated locally with realistic linear adders — production reads variation prices straight from WooCommerce.",
  "File uploads use a sample image picker (no real upload pipeline). Approval reply images use the same picker; the UI models the real upload flow.",
  "Search hotkey ⌘K opens the command palette but only routes within the SPA — does not query backend.",
  "Mobile sidebar collapses to icons; resize breakpoints tested at 375 / 768 / 1280.",
];

export function AuditSummary({
  open,
  onOpenChange,
  area,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  area: "client" | "admin";
}) {
  const [tab, setTab] = useState<"routes" | "clickability" | "removed" | "actions" | "limits">("routes");
  const routes = ROUTES_CHECKED.filter((r) => r.area === area);
  const actions = PROD_ACTIONS.filter((a) => a.area === area);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="grid size-7 place-items-center rounded-md bg-primary/10 text-primary">
              <ClipboardList className="size-4" />
            </span>
            Preview QA / audit summary
            <Badge variant="outline" className="ml-2 text-[10px] uppercase tracking-wide">
              {area}
            </Badge>
          </DialogTitle>
        </DialogHeader>

        <Tabs value={tab} onValueChange={(v) => setTab(v as any)}>
          <TabsList className="h-9 bg-muted/40 p-0.5 flex-wrap">
            <TabsTrigger value="routes" className="h-8 px-3 text-xs">
              Routes ({routes.length})
            </TabsTrigger>
            <TabsTrigger value="clickability" className="h-8 px-3 text-xs">
              Clickability rules
            </TabsTrigger>
            <TabsTrigger value="removed" className="h-8 px-3 text-xs">
              Removed from nav
            </TabsTrigger>
            <TabsTrigger value="actions" className="h-8 px-3 text-xs">
              Production actions ({actions.length})
            </TabsTrigger>
            <TabsTrigger value="limits" className="h-8 px-3 text-xs">
              Known limitations
            </TabsTrigger>
          </TabsList>

          <TabsContent value="routes" className="mt-4 max-h-[60vh] overflow-y-auto">
            <div className="space-y-2">
              {routes.map((r) => (
                <div key={r.route} className="rounded-md border bg-card p-3">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="size-3.5 shrink-0 text-success" />
                    <code className="font-mono text-xs font-medium">{r.route}</code>
                  </div>
                  <p className="mt-1 ml-5 text-xs leading-relaxed text-muted-foreground">{r.notes}</p>
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="clickability" className="mt-4 max-h-[60vh] overflow-y-auto">
            <p className="mb-3 text-xs text-muted-foreground">
              Buttons must clearly look like buttons. Non-buttons must clearly NOT look clickable. These rules are applied throughout the rebuild.
            </p>
            <div className="space-y-2">
              {CLICKABILITY_RULES.map((c, i) => (
                <div key={i} className="rounded-md border bg-card p-3">
                  <div className="flex items-start gap-2">
                    <CheckCircle2 className="size-3.5 shrink-0 mt-0.5 text-success" />
                    <p className="text-sm font-medium">{c.rule}</p>
                  </div>
                  <p className="mt-1 ml-5 text-xs leading-relaxed text-muted-foreground">{c.rationale}</p>
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="removed" className="mt-4 max-h-[60vh] overflow-y-auto">
            <p className="mb-3 text-xs text-muted-foreground">
              IA over-simplified to 5 nav items per role. The pages below are still reachable via deep-linked URL or contextual entry points — they’re just not top-level.
            </p>
            <div className="space-y-2">
              {REMOVED_FROM_NAV.map((r, i) => (
                <div key={i} className="rounded-md border p-3 text-xs">
                  {r}
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="actions" className="mt-4 max-h-[60vh] overflow-y-auto">
            <p className="mb-3 text-xs text-muted-foreground">
              Each preview interaction shows a toast or dialog describing the production action below.
            </p>
            <div className="space-y-2">
              {actions.map((a: PreviewAction) => (
                <div key={a.id} className="rounded-md border p-3">
                  <div className="flex items-baseline justify-between gap-2">
                    <p className="text-sm font-medium">{a.label}</p>
                    <Badge variant="outline" className="text-[10px] font-normal">
                      {a.page}
                    </Badge>
                  </div>
                  <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{a.description}</p>
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="limits" className="mt-4 max-h-[60vh] overflow-y-auto">
            <div className="space-y-2">
              {LIMITATIONS.map((l, i) => (
                <div
                  key={i}
                  className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/5 p-3 text-xs leading-relaxed"
                >
                  <ShieldAlert className="size-3.5 shrink-0 text-warning" />
                  {l}
                </div>
              ))}
            </div>
          </TabsContent>
        </Tabs>

        <p className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
          <ExternalLink className="size-3" />
          Live Oversee catalog mirrors{" "}
          <code className="rounded bg-muted px-1 py-0.5 font-mono">
            overseeagency.com/wp-json/wc/store/v1/products
          </code>
          .
        </p>
      </DialogContent>
    </Dialog>
  );
}
