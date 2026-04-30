// Client Account — consolidated WooCommerce My Account replacement.
// Tabs: Overview · Subscriptions · Orders & invoices · Payment methods · Addresses · Profile.
// In preview, every action mutates state, opens a sheet/dialog, or shows a toast
// describing the production WooCommerce/Stripe behavior. No dead buttons,
// no external-handoff-only cards.

import { useEffect, useMemo, useState } from "react";
import { useParams, useLocation } from "wouter";
import {
  AlertCircle,
  ArrowRight,
  Bell,
  CalendarClock,
  Check,
  CheckCircle2,
  CreditCard,
  Download,
  ExternalLink,
  FileText,
  Mail,
  MapPin,
  Pause,
  Play,
  Plus,
  Receipt,
  RefreshCcw,
  Repeat,
  Shield,
  ShieldCheck,
  Sparkles,
  Star,
  Trash2,
  UserRound,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import {
  Sheet,
  SheetContent,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { useToast } from "@/hooks/use-toast";
import {
  useDemoStore,
  type Invoice,
  type Subscription,
} from "@/lib/demo-store";
import { shortDate, shortDateTime } from "@/lib/format";
import { subscriptionTone, invoiceTone } from "@/lib/status-tones";
import {
  AccountTabs,
  AppCard,
  DetailSheetHeader,
  EmptyState,
  InlineHelp,
  MetricCard,
  PageHeader,
  PageShell,
  SectionHeader,
  StatusPill,
  type StatusTone,
} from "@/components/shared";
import {
  AccountSummaryRow,
  AttentionRow,
  QuickActionTile,
} from "@/components/commerce-shared";

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type AccountTab =
  | "overview"
  | "subscriptions"
  | "orders"
  | "payments"
  | "addresses"
  | "profile";

const TAB_OPTIONS: { value: AccountTab; label: string; testId: string }[] = [
  { value: "overview", label: "Overview", testId: "tab-account-overview" },
  { value: "subscriptions", label: "Subscriptions", testId: "tab-account-subscriptions" },
  { value: "orders", label: "Orders & invoices", testId: "tab-account-orders" },
  { value: "payments", label: "Payment methods", testId: "tab-account-payments" },
  { value: "addresses", label: "Addresses", testId: "tab-account-addresses" },
  { value: "profile", label: "Profile", testId: "tab-account-profile" },
];

// Tabs old paths can land on
const TAB_ALIASES: Record<string, AccountTab> = {
  subscriptions: "subscriptions",
  billing: "orders",
  invoices: "orders",
  orders: "orders",
  payments: "payments",
  "payment-methods": "payments",
  addresses: "addresses",
  profile: "profile",
};

// ─────────────────────────────────────────────────────────────────────────────
// Mock saved cards & addresses (in preview only)
// ─────────────────────────────────────────────────────────────────────────────

type SavedCard = {
  id: string;
  brand: "Visa" | "Mastercard" | "Amex";
  last4: string;
  exp: string;
  default: boolean;
};

const INITIAL_CARDS: SavedCard[] = [
  { id: "pm_1", brand: "Visa", last4: "4242", exp: "07 / 27", default: true },
  { id: "pm_2", brand: "Mastercard", last4: "7290", exp: "11 / 26", default: false },
];

type AddressData = {
  firstName: string;
  lastName: string;
  company: string;
  address1: string;
  address2: string;
  city: string;
  state: string;
  postal: string;
  country: string;
  phone?: string;
  email?: string;
};

const DEFAULT_BILLING: AddressData = {
  firstName: "Maya",
  lastName: "Lin",
  company: "Northstar Gallery",
  address1: "2440 Mission St",
  address2: "Suite 4",
  city: "San Francisco",
  state: "CA",
  postal: "94110",
  country: "United States",
  phone: "+1 (415) 555-0118",
  email: "billing@northstargallery.com",
};

const DEFAULT_SHIPPING: AddressData = {
  firstName: "Maya",
  lastName: "Lin",
  company: "Northstar Gallery",
  address1: "16 Bryant St",
  address2: "Loading dock",
  city: "San Francisco",
  state: "CA",
  postal: "94105",
  country: "United States",
};

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — status tone mapping
// ─────────────────────────────────────────────────────────────────────────────

// Subscription / invoice tone mapping has moved to `lib/status-tones.ts` so
// commerce.tsx and other surfaces can reuse the same domain→tone vocabulary.

// ─────────────────────────────────────────────────────────────────────────────
// Main component
// ─────────────────────────────────────────────────────────────────────────────

type ClientAccountProps = {
  initialTab?: string;
};

export default function ClientAccount({ initialTab: initialTabProp }: ClientAccountProps = {}) {
  const params = useParams<{ sub?: string }>();
  const [, navigate] = useLocation();
  const seedKey = params.sub ?? initialTabProp ?? "";
  const resolvedTab: AccountTab =
    (seedKey && TAB_ALIASES[seedKey]) || "overview";
  const [tab, setTab] = useState<AccountTab>(resolvedTab);
  // Keep tab synced when the URL alias changes (e.g. user navigates between
  // /client/account/orders and /client/account/profile).
  useEffect(() => {
    setTab(resolvedTab);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [seedKey]);

  const {
    customer,
    subscriptions,
    invoices,
    orders,
    services,
  } = useDemoStore();

  const activeSubs = subscriptions.filter((s) => s.status === "active");
  const pastDueSubs = subscriptions.filter((s) => s.status === "past-due");
  const pastDueInvoices = invoices.filter((i) => i.status === "past-due");
  const nextRenewal = subscriptions
    .filter((s) => s.status === "active")
    .sort((a, b) => +new Date(a.renewsOn) - +new Date(b.renewsOn))[0];
  const nextRenewalSvc = nextRenewal
    ? services.find((sv) => sv.id === nextRenewal.serviceId)
    : null;

  const [cards, setCards] = useState<SavedCard[]>(INITIAL_CARDS);
  const defaultCard = cards.find((c) => c.default);

  const counts = useMemo(
    () => ({
      subscriptions: subscriptions.length,
      orders: orders.length + invoices.length,
      payments: cards.length,
    }),
    [subscriptions.length, orders.length, invoices.length, cards.length],
  );

  const switchTab = (v: AccountTab) => {
    setTab(v);
    // Keep URL in sync for deep-linking. This avoids the full page change.
    if (v === "overview") navigate("/client/account");
    else navigate(`/client/account/${v}`);
  };

  return (
    <PageShell testId="client-account">
      <PageHeader
        title="Account"
        subtitle="Manage your subscriptions, invoices, payment methods, addresses, and profile in one place. This replaces the WooCommerce My Account area for your Oversee services."
        testId="account-header"
      />

      {/* Top summary row */}
      <div
        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        data-testid="account-summary"
      >
        <MetricCard
          label="Active subscriptions"
          value={activeSubs.length}
          meta={
            pastDueSubs.length
              ? `${pastDueSubs.length} past due`
              : "All current"
          }
          tone={pastDueSubs.length ? "danger" : "neutral"}
          testId="metric-active-subs"
        />
        <MetricCard
          label="Next renewal"
          value={nextRenewal ? shortDate(nextRenewal.renewsOn) : "—"}
          meta={nextRenewalSvc?.title ?? "No active plans"}
          testId="metric-next-renewal"
        />
        <MetricCard
          label="Past due invoices"
          value={pastDueInvoices.length}
          meta={
            pastDueInvoices.length
              ? `$${pastDueInvoices
                  .reduce((a, b) => a + b.amount, 0)
                  .toLocaleString()} owed`
              : "Nothing owed"
          }
          tone={pastDueInvoices.length ? "danger" : "neutral"}
          testId="metric-past-due"
        />
        <MetricCard
          label="Default card"
          value={defaultCard ? `•••• ${defaultCard.last4}` : "None on file"}
          meta={defaultCard ? `${defaultCard.brand} · exp ${defaultCard.exp}` : "Add a card"}
          testId="metric-default-card"
        />
      </div>

      <AccountTabs<AccountTab>
        value={tab}
        onChange={switchTab}
        options={TAB_OPTIONS.map((o) => ({
          ...o,
          count:
            o.value === "subscriptions"
              ? counts.subscriptions
              : o.value === "orders"
                ? counts.orders
                : o.value === "payments"
                  ? counts.payments
                  : undefined,
        }))}
      />

      {tab === "overview" && (
        <OverviewTab
          onGotoTab={switchTab}
          subscriptions={subscriptions}
          invoices={invoices}
        />
      )}

      {tab === "subscriptions" && <SubscriptionsTab />}

      {tab === "orders" && <OrdersTab />}

      {tab === "payments" && (
        <PaymentMethodsTab cards={cards} setCards={setCards} />
      )}

      {tab === "addresses" && <AddressesTab customer={customer} />}

      {tab === "profile" && <ProfileTab customer={customer} />}
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// OVERVIEW TAB
// ─────────────────────────────────────────────────────────────────────────────

function OverviewTab({
  onGotoTab,
  subscriptions,
  invoices,
}: {
  onGotoTab: (t: AccountTab) => void;
  subscriptions: Subscription[];
  invoices: Invoice[];
}) {
  type Attn = {
    id: string;
    icon: typeof CreditCard;
    iconTone: "danger" | "warning" | "primary";
    title: string;
    description: string;
    primary: { label: string; tab: AccountTab };
  };

  const attention = useMemo<Attn[]>(() => {
    const list: Attn[] = [];
    invoices
      .filter((i) => i.status === "past-due")
      .forEach((inv) => {
        list.push({
          id: `inv-${inv.id}`,
          icon: AlertCircle,
          iconTone: "danger",
          title: `Invoice ${inv.number} is past due`,
          description: `${inv.service} · $${inv.amount.toLocaleString()} — pay now to keep your service active.`,
          primary: { label: "Pay invoice", tab: "orders" },
        });
      });
    subscriptions
      .filter((s) => s.status === "past-due")
      .forEach((s) => {
        list.push({
          id: `sub-${s.id}`,
          icon: CreditCard,
          iconTone: "danger",
          title: `Payment failed on subscription`,
          description: `$${s.amount.toLocaleString()} renewal could not be charged. Update your default card or pay the renewal.`,
          primary: { label: "Update payment", tab: "payments" },
        });
      });
    invoices
      .filter((i) => i.status === "open")
      .slice(0, 1)
      .forEach((inv) => {
        list.push({
          id: `open-${inv.id}`,
          icon: Receipt,
          iconTone: "warning",
          title: `Upcoming invoice ${inv.number}`,
          description: `${inv.service} · $${inv.amount.toLocaleString()} — issued ${shortDate(inv.date)}.`,
          primary: { label: "View invoice", tab: "orders" },
        });
      });
    return list;
  }, [invoices, subscriptions]);

  const QUICK_LINKS: {
    icon: typeof Repeat;
    label: string;
    description: string;
    tab: AccountTab;
    testId: string;
  }[] = [
    {
      icon: Repeat,
      label: "Subscriptions",
      description: "Pause, resume, switch plans, or cancel active services.",
      tab: "subscriptions",
      testId: "quick-link-subscriptions",
    },
    {
      icon: Receipt,
      label: "Orders & invoices",
      description: "Past orders, paid invoices, and downloadable PDFs.",
      tab: "orders",
      testId: "quick-link-orders",
    },
    {
      icon: CreditCard,
      label: "Payment methods",
      description: "Saved cards, default card, and Stripe-secured updates.",
      tab: "payments",
      testId: "quick-link-payments",
    },
    {
      icon: MapPin,
      label: "Addresses",
      description: "Billing and shipping addresses on file for invoices and deliveries.",
      tab: "addresses",
      testId: "quick-link-addresses",
    },
    {
      icon: UserRound,
      label: "Profile",
      description: "Account details, login email, and notification preferences.",
      tab: "profile",
      testId: "quick-link-profile",
    },
  ];

  return (
    <div className="space-y-6">
      <section className="space-y-3">
        <SectionHeader
          title="What needs attention"
          hint={
            attention.length === 0
              ? "Everything is current."
              : `${attention.length} item${attention.length === 1 ? "" : "s"} to review`
          }
        />
        {attention.length === 0 ? (
          <EmptyState
            icon={CheckCircle2}
            tone="success"
            title="All caught up"
            description="No past-due invoices, no failed renewals. We'll surface anything that needs your attention here."
            testId="overview-empty"
          />
        ) : (
          <ul className="space-y-3" data-testid="overview-attention-list">
            {attention.map((a) => (
              <AttentionRow
                key={a.id}
                icon={a.icon}
                tone={a.iconTone}
                title={a.title}
                description={a.description}
                cta={{
                  label: a.primary.label,
                  onClick: () => onGotoTab(a.primary.tab),
                  testId: `button-attn-${a.id}`,
                }}
                testId={`attention-${a.id}`}
              />
            ))}
          </ul>
        )}
      </section>

      <section className="space-y-3">
        <SectionHeader
          title="Manage your account"
          hint="Jump to any section. Everything lives here — no second login."
        />
        <ul
          className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
          data-testid="overview-quick-links"
        >
          {QUICK_LINKS.map((q) => (
            <li key={q.tab}>
              <QuickActionTile
                icon={q.icon}
                label={q.label}
                description={q.description}
                onClick={() => onGotoTab(q.tab)}
                testId={q.testId}
              />
            </li>
          ))}
        </ul>
      </section>

      <InlineHelp icon={Shield}>
        Your account, billing, and payments are all managed here. In production this dashboard
        replaces the WooCommerce <code className="rounded bg-muted px-1 text-[10px]">/my-account/</code>{" "}
        pages and uses WooCommerce Subscriptions, WooCommerce Stripe, and the WP user account behind the scenes.
      </InlineHelp>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SUBSCRIPTIONS TAB
// ─────────────────────────────────────────────────────────────────────────────

type SubAction = "details" | "pay" | "payment" | "pause" | "resume" | "switch" | "cancel";

function SubscriptionsTab() {
  const {
    subscriptions,
    services,
    boards,
    pauseSubscription,
    resumeSubscription,
    cancelSubscription,
  } = useDemoStore();
  const { toast } = useToast();
  const [openSubId, setOpenSubId] = useState<string | null>(null);
  const [action, setAction] = useState<SubAction>("details");
  const [confirmCancel, setConfirmCancel] = useState<string | null>(null);

  const openSub = openSubId ? subscriptions.find((s) => s.id === openSubId) : null;
  const openSvc = openSub ? services.find((sv) => sv.id === openSub.serviceId) : null;
  const openBoard = openSvc
    ? boards.find((b) => b.name.toLowerCase().includes(openSvc.title.split(" ")[0].toLowerCase()))
    : null;

  const handlePauseConfirm = () => {
    if (!openSub) return;
    pauseSubscription(openSub.id);
    toast({
      title: "Subscription paused",
      description: `${openSvc?.title} — production: WooCommerce Subscriptions API status set to on-hold.`,
    });
    setAction("details");
  };

  const handleResume = () => {
    if (!openSub) return;
    resumeSubscription(openSub.id);
    toast({
      title: "Subscription resumed",
      description: `${openSvc?.title} — production: WooCommerce Subscriptions API status set to active.`,
    });
  };

  const handleConfirmCancel = () => {
    if (!confirmCancel) return;
    cancelSubscription(confirmCancel);
    const sub = subscriptions.find((s) => s.id === confirmCancel);
    const svc = sub ? services.find((sv) => sv.id === sub.serviceId) : null;
    toast({
      title: "Subscription cancelled",
      description: `${svc?.title ?? "Subscription"} cancels at end of period — production: WooCommerce Subscriptions cancel.`,
    });
    setConfirmCancel(null);
    setOpenSubId(null);
  };

  if (subscriptions.length === 0) {
    return (
      <EmptyState
        icon={Repeat}
        title="No active subscriptions"
        description="Browse services to add a recurring plan to your account."
        cta={{ label: "Browse services", onClick: () => (window.location.hash = "#/client/services") }}
        testId="subscriptions-empty"
      />
    );
  }

  return (
    <div className="space-y-4">
      <SectionHeader
        title="Your subscriptions"
        hint="Recurring services you're paying for. Manage billing and lifecycle from a single sheet."
      />

      <ul className="space-y-3" data-testid="subscriptions-list">
        {subscriptions.map((s) => {
          const svc = services.find((sv) => sv.id === s.serviceId);
          if (!svc) return null;
          const board = boards.find((b) =>
            b.name.toLowerCase().includes(svc.title.split(" ")[0].toLowerCase()),
          );
          const cadence =
            svc.cadence === "monthly" ? "/mo" : svc.cadence === "quarterly" ? "/qtr" : "";
          const subtitle = (
            <span className="tabular-nums">
              ${s.amount.toLocaleString()}
              {cadence}
              {" · "}
              {s.status === "active"
                ? `next renewal ${shortDate(s.renewsOn)}`
                : s.status === "past-due"
                  ? "payment failed"
                  : s.status === "paused"
                    ? "paused — resume any time"
                    : "cancelled"}
              {board && (
                <>
                  {" · "}
                  <span className="text-foreground">{board.name}</span>
                </>
              )}
            </span>
          );
          return (
            <AccountSummaryRow
              key={s.id}
              icon={Repeat}
              iconTone={
                s.status === "past-due"
                  ? "danger"
                  : s.status === "active"
                    ? "primary"
                    : "neutral"
              }
              title={svc.title}
              subtitle={subtitle}
              status={{
                tone: subscriptionTone(s.status),
                label: s.status === "past-due" ? "Past due" : s.status,
              }}
              actions={
                <Button
                  size="sm"
                  className="shrink-0 gap-1.5"
                  onClick={() => {
                    setOpenSubId(s.id);
                    setAction("details");
                  }}
                  data-testid={`button-manage-sub-${s.id}`}
                >
                  Manage subscription
                  <ArrowRight className="size-3.5" />
                </Button>
              }
              testId={`subscription-row-${s.id}`}
            />
          );
        })}
      </ul>

      <InlineHelp icon={Shield}>
        Production uses the WooCommerce Subscriptions native flow — pause, resume, cancel, switch plan,
        change payment method, and view renewals — all rendered inside this dashboard. No second login.
      </InlineHelp>

      {/* Detail sheet */}
      <Sheet
        open={!!openSubId}
        onOpenChange={(v) => {
          if (!v) {
            setOpenSubId(null);
            setAction("details");
          }
        }}
      >
        <SheetContent className="w-full sm:max-w-lg">
          {openSub && openSvc && (
            <>
              <SheetHeader>
                <SheetTitle className="sr-only">{openSvc.title}</SheetTitle>
              </SheetHeader>
              <DetailSheetHeader
                eyebrow="Subscription"
                title={openSvc.title}
                subtitle={`$${openSub.amount.toLocaleString()}${
                  openSvc.cadence === "monthly" ? "/mo" : openSvc.cadence === "quarterly" ? "/qtr" : ""
                } · ${openSub.status === "active" ? `Renews ${shortDate(openSub.renewsOn)}` : openSub.status}`}
                meta={
                  <StatusPill tone={subscriptionTone(openSub.status)}>
                    {openSub.status === "past-due" ? "Past due" : openSub.status}
                  </StatusPill>
                }
              />

              {/* Tabs inside the sheet — actions vs details vs payment */}
              <div className="mt-4 space-y-4">
                {action === "details" && (
                  <div className="space-y-4">
                    <div className="rounded-lg border border-border bg-muted/40 p-4 text-xs">
                      <p className="font-medium text-foreground">Plan summary</p>
                      <dl className="mt-2 space-y-1.5 text-muted-foreground">
                        <div className="flex justify-between">
                          <dt>Status</dt>
                          <dd className="font-medium text-foreground capitalize">
                            {openSub.status === "past-due" ? "Past due" : openSub.status}
                          </dd>
                        </div>
                        <div className="flex justify-between">
                          <dt>Amount</dt>
                          <dd className="font-medium text-foreground tabular-nums">
                            ${openSub.amount.toLocaleString()}
                            {openSvc.cadence === "monthly" ? "/mo" : openSvc.cadence === "quarterly" ? "/qtr" : ""}
                          </dd>
                        </div>
                        <div className="flex justify-between">
                          <dt>Next renewal</dt>
                          <dd className="font-medium text-foreground">
                            {shortDate(openSub.renewsOn)}
                          </dd>
                        </div>
                        {openBoard && (
                          <div className="flex justify-between">
                            <dt>Project board</dt>
                            <dd className="font-medium text-foreground">{openBoard.name}</dd>
                          </div>
                        )}
                      </dl>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                      {openSub.status === "past-due" && (
                        <Button
                          className="col-span-2 gap-1.5"
                          onClick={() => setAction("pay")}
                          data-testid="button-pay-renewal"
                        >
                          <CreditCard className="size-4" />
                          Pay renewal
                        </Button>
                      )}
                      <Button
                        variant="outline"
                        size="sm"
                        className="gap-1.5"
                        onClick={() => setAction("payment")}
                        data-testid="button-change-payment"
                      >
                        <CreditCard className="size-4" />
                        Change payment
                      </Button>
                      {openSub.status === "active" || openSub.status === "past-due" ? (
                        <Button
                          variant="outline"
                          size="sm"
                          className="gap-1.5"
                          onClick={() => setAction("pause")}
                          data-testid="button-pause-sub"
                        >
                          <Pause className="size-4" />
                          Pause
                        </Button>
                      ) : openSub.status === "paused" ? (
                        <Button
                          variant="outline"
                          size="sm"
                          className="gap-1.5"
                          onClick={handleResume}
                          data-testid="button-resume-sub"
                        >
                          <Play className="size-4" />
                          Resume
                        </Button>
                      ) : null}
                      <Button
                        variant="outline"
                        size="sm"
                        className="gap-1.5"
                        onClick={() => setAction("switch")}
                        data-testid="button-switch-plan"
                      >
                        <RefreshCcw className="size-4" />
                        Switch plan
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        className="col-span-2 gap-1.5 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/30"
                        onClick={() => setConfirmCancel(openSub.id)}
                        data-testid="button-cancel-sub"
                      >
                        <X className="size-4" />
                        Cancel subscription
                      </Button>
                    </div>

                    <InlineHelp icon={Shield}>
                      Production: each action calls the WooCommerce Subscriptions REST endpoints
                      (status change, switch product, cancel) and syncs the renewal schedule.
                    </InlineHelp>
                  </div>
                )}

                {action === "pay" && (
                  <PayRenewalForm
                    sub={openSub}
                    svc={openSvc.title}
                    onCancel={() => setAction("details")}
                    onPaid={() => {
                      toast({
                        title: "Renewal paid",
                        description: `${openSvc.title} is now current — production charges via WooCommerce Stripe.`,
                      });
                      setAction("details");
                    }}
                  />
                )}

                {action === "payment" && (
                  <ChangePaymentForm
                    onCancel={() => setAction("details")}
                    onSaved={(scope) => {
                      toast({
                        title: "Payment method updated",
                        description:
                          scope === "all"
                            ? "Updated for this and all your subscriptions."
                            : "Updated for this subscription only.",
                      });
                      setAction("details");
                    }}
                  />
                )}

                {action === "pause" && (
                  <div className="space-y-3 text-sm">
                    <p className="text-muted-foreground">
                      Pausing keeps your service plan in place with no charges. Your project board and
                      assets stay intact. You can resume any time.
                    </p>
                    <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
                      <Button variant="outline" size="sm" onClick={() => setAction("details")}>
                        Back
                      </Button>
                      <Button size="sm" onClick={handlePauseConfirm} data-testid="button-confirm-pause">
                        Confirm pause
                      </Button>
                    </div>
                  </div>
                )}

                {action === "switch" && (
                  <SwitchPlanForm
                    onCancel={() => setAction("details")}
                    onSwitched={() => {
                      toast({
                        title: "Plan switch requested",
                        description:
                          "Pro-rated charge applied at next renewal — production: WooCommerce Subscriptions Switching.",
                      });
                      setAction("details");
                    }}
                  />
                )}
              </div>
            </>
          )}

          <SheetFooter />
        </SheetContent>
      </Sheet>

      {/* Cancel confirm */}
      <AlertDialog open={!!confirmCancel} onOpenChange={(v) => !v && setConfirmCancel(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this subscription?</AlertDialogTitle>
            <AlertDialogDescription>
              The subscription will end at the end of the current billing period. Your team retains
              access until then. You can resubscribe later.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Keep subscription</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmCancel}
              className="bg-rose-600 hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-800"
              data-testid="button-confirm-cancel"
            >
              Cancel subscription
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function PayRenewalForm({
  sub,
  svc,
  onCancel,
  onPaid,
}: {
  sub: Subscription;
  svc: string;
  onCancel: () => void;
  onPaid: () => void;
}) {
  return (
    <div className="space-y-3 text-sm">
      <div className="rounded-lg border border-border bg-muted/40 p-4">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          Order pay
        </p>
        <p className="mt-1 text-base font-semibold tabular-nums">
          ${sub.amount.toLocaleString()}
        </p>
        <p className="text-xs text-muted-foreground">{svc} · renewal</p>
      </div>
      <div className="rounded-lg border border-border p-3">
        <p className="text-xs font-medium text-foreground">Charge default card</p>
        <p className="mt-0.5 text-[11px] text-muted-foreground">
          Production: WooCommerce <code className="rounded bg-muted px-1">/order-pay/{`{ORDER_ID}`}</code> via Stripe.
        </p>
      </div>
      <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
        <Button variant="outline" size="sm" onClick={onCancel}>
          Back
        </Button>
        <Button size="sm" onClick={onPaid} data-testid="button-confirm-pay-renewal">
          Pay $
          {sub.amount.toLocaleString()}
        </Button>
      </div>
    </div>
  );
}

function ChangePaymentForm({
  onCancel,
  onSaved,
}: {
  onCancel: () => void;
  onSaved: (scope: "this" | "all") => void;
}) {
  const [updateAll, setUpdateAll] = useState(false);
  return (
    <div className="space-y-3 text-sm">
      <p className="text-xs text-muted-foreground">
        Add a new card to use for this renewal. We'll save it to your account so future renewals don't fail.
      </p>
      <Input placeholder="Card number" data-testid="input-cc-number" />
      <div className="grid grid-cols-2 gap-2">
        <Input placeholder="MM / YY" data-testid="input-cc-exp" />
        <Input placeholder="CVC" data-testid="input-cc-cvc" />
      </div>
      <Input placeholder="ZIP / Postal code" />
      <div className="flex items-start gap-2 rounded-lg border border-border bg-muted/40 p-3">
        <Switch
          checked={updateAll}
          onCheckedChange={setUpdateAll}
          id="update-all"
          data-testid="switch-update-all"
        />
        <Label htmlFor="update-all" className="text-xs leading-snug">
          Use this card for all my subscriptions
          <span className="mt-0.5 block text-[11px] text-muted-foreground">
            Production: updates the default token across every active WooCommerce subscription.
          </span>
        </Label>
      </div>
      <InlineHelp icon={ShieldCheck}>
        In production, the card is collected through Stripe Elements (PCI-compliant iframe). Your servers
        never see the raw card data. Preview only — nothing is stored.
      </InlineHelp>
      <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
        <Button variant="outline" size="sm" onClick={onCancel}>
          Back
        </Button>
        <Button
          size="sm"
          onClick={() => onSaved(updateAll ? "all" : "this")}
          data-testid="button-save-payment"
        >
          Save card
        </Button>
      </div>
    </div>
  );
}

function SwitchPlanForm({
  onCancel,
  onSwitched,
}: {
  onCancel: () => void;
  onSwitched: () => void;
}) {
  const { services } = useDemoStore();
  const [target, setTarget] = useState<string | null>(services[0]?.id ?? null);
  return (
    <div className="space-y-3 text-sm">
      <p className="text-xs text-muted-foreground">
        Choose a plan to switch to. Pro-rated charge applies at next renewal.
      </p>
      <ul className="space-y-2" data-testid="switch-plan-options">
        {services.map((s) => {
          const selected = s.id === target;
          return (
            <li key={s.id}>
              <button
                type="button"
                onClick={() => setTarget(s.id)}
                className={`flex w-full items-start gap-3 rounded-lg border p-3 text-left transition ${
                  selected
                    ? "border-primary bg-primary-soft"
                    : "border-border bg-card hover:border-primary/40"
                }`}
                data-testid={`switch-option-${s.id}`}
              >
                <span className="grid size-8 shrink-0 place-items-center rounded-md border border-border bg-muted">
                  {selected ? <Check className="size-4 text-primary" /> : <Sparkles className="size-4" />}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold text-foreground">{s.title}</p>
                  <p className="mt-0.5 text-xs text-muted-foreground">{s.blurb}</p>
                </div>
                <span className="text-xs font-semibold tabular-nums text-foreground">
                  ${s.price.toLocaleString()}
                  {s.cadence === "monthly" ? "/mo" : s.cadence === "quarterly" ? "/qtr" : ""}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
      <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
        <Button variant="outline" size="sm" onClick={onCancel}>
          Back
        </Button>
        <Button
          size="sm"
          disabled={!target}
          onClick={onSwitched}
          data-testid="button-confirm-switch"
        >
          Confirm switch
        </Button>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ORDERS & INVOICES TAB
// ─────────────────────────────────────────────────────────────────────────────

type OrderRow = {
  id: string;
  number: string;
  date: string;
  service: string;
  total: number;
  status: "completed" | "processing" | "on-hold" | "refunded" | "pending" | "failed";
  kind: "order" | "invoice";
  invoiceStatus?: Invoice["status"];
};

function OrdersTab() {
  const { orders, invoices, customer } = useDemoStore();
  const { toast } = useToast();
  const [openId, setOpenId] = useState<string | null>(null);
  const [payOpen, setPayOpen] = useState<string | null>(null);

  // Combine orders + invoices into a unified WooCommerce-style list.
  const rows = useMemo<OrderRow[]>(() => {
    const customerOrders = orders.filter((o) => o.customer === customer.name);
    const ordersList: OrderRow[] = customerOrders.map((o) => ({
      id: `o-${o.id}`,
      number: o.number,
      date: o.date,
      service: o.service,
      total: o.total,
      status: o.status,
      kind: "order",
    }));
    const invoicesList: OrderRow[] = invoices.map((i) => ({
      id: `i-${i.id}`,
      number: i.number,
      date: i.date,
      service: i.service,
      total: i.amount,
      status:
        i.status === "paid"
          ? "completed"
          : i.status === "past-due"
            ? "failed"
            : "pending",
      kind: "invoice",
      invoiceStatus: i.status,
    }));
    return [...ordersList, ...invoicesList].sort((a, b) => +new Date(b.date) - +new Date(a.date));
  }, [orders, invoices, customer.name]);

  const openRow = openId ? rows.find((r) => r.id === openId) : null;

  if (rows.length === 0) {
    return (
      <EmptyState
        icon={Receipt}
        title="No orders or invoices yet"
        description="Past purchases and invoices appear here."
        testId="orders-empty"
      />
    );
  }

  return (
    <div className="space-y-4">
      <SectionHeader title="Orders & invoices" hint={`${rows.length} records on file`} />

      <AppCard padded={false} testId="orders-table-card">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="px-4 py-2.5 text-left font-medium">Number</th>
                <th className="px-4 py-2.5 text-left font-medium">Date</th>
                <th className="px-4 py-2.5 text-left font-medium">Service</th>
                <th className="px-4 py-2.5 text-right font-medium">Total</th>
                <th className="px-4 py-2.5 text-left font-medium">Status</th>
                <th className="px-4 py-2.5 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const tone: StatusTone =
                  r.status === "completed"
                    ? "success"
                    : r.status === "processing"
                      ? "info"
                      : r.status === "on-hold"
                        ? "warning"
                        : r.status === "failed"
                          ? "danger"
                          : r.status === "refunded"
                            ? "neutral"
                            : "warning";
                const isPayable = r.status === "pending" || r.status === "failed";
                return (
                  <tr
                    key={r.id}
                    className="border-b border-border/60 last:border-0 hover:bg-muted/30"
                    data-testid={`order-row-${r.id}`}
                  >
                    <td className="px-4 py-3 font-mono text-xs">
                      {r.number}
                    </td>
                    <td className="px-4 py-3 text-xs text-muted-foreground">
                      {shortDate(r.date)}
                    </td>
                    <td className="px-4 py-3">{r.service}</td>
                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                      ${r.total.toLocaleString()}
                    </td>
                    <td className="px-4 py-3">
                      <StatusPill tone={tone}>{r.status}</StatusPill>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-1.5">
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-8 px-2.5 text-xs"
                          onClick={() => setOpenId(r.id)}
                          data-testid={`button-view-${r.id}`}
                        >
                          View
                        </Button>
                        {isPayable && (
                          <Button
                            size="sm"
                            className="h-8 px-2.5 text-xs"
                            onClick={() => setPayOpen(r.id)}
                            data-testid={`button-pay-${r.id}`}
                          >
                            Pay now
                          </Button>
                        )}
                        {r.kind === "invoice" && r.invoiceStatus === "paid" && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 gap-1 px-2 text-xs"
                            onClick={() =>
                              toast({
                                title: "Invoice download started",
                                description: `${r.number}.pdf — production: streamed from WooCommerce PDF Invoices.`,
                              })
                            }
                            data-testid={`button-pdf-${r.id}`}
                          >
                            <Download className="size-3" />
                            PDF
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </AppCard>

      <InlineHelp icon={Shield}>
        Production wires this to the WooCommerce REST endpoints{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/orders/</code>,{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/view-order/{`{ORDER_ID}`}</code>, and{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/order-pay/{`{ORDER_ID}`}</code> — all rendered inside this dashboard.
      </InlineHelp>

      {/* View order/invoice sheet */}
      <Sheet open={!!openId} onOpenChange={(v) => !v && setOpenId(null)}>
        <SheetContent className="w-full sm:max-w-lg">
          <SheetHeader>
            <SheetTitle className="sr-only">{openRow?.number}</SheetTitle>
          </SheetHeader>
          {openRow && <OrderDetail row={openRow} />}
          <SheetFooter />
        </SheetContent>
      </Sheet>

      {/* In-dashboard checkout/order-pay preview */}
      <Dialog open={!!payOpen} onOpenChange={(v) => !v && setPayOpen(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Pay invoice</DialogTitle>
          </DialogHeader>
          {payOpen && (
            <PayInvoiceForm
              row={rows.find((r) => r.id === payOpen)!}
              onCancel={() => setPayOpen(null)}
              onPaid={() => {
                setPayOpen(null);
                toast({
                  title: "Payment recorded",
                  description:
                    "Production: WooCommerce Stripe charges your default card via /order-pay/.",
                });
              }}
            />
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

function OrderDetail({ row }: { row: OrderRow }) {
  const timeline = [
    { at: row.date, label: row.kind === "order" ? "Order placed" : "Invoice issued" },
    ...(row.status === "processing" || row.status === "completed"
      ? [{ at: row.date, label: "Processing started" }]
      : []),
    ...(row.status === "completed"
      ? [{ at: row.date, label: row.kind === "order" ? "Order completed" : "Invoice paid" }]
      : []),
    ...(row.status === "on-hold"
      ? [{ at: row.date, label: "On hold — awaiting next step" }]
      : []),
    ...(row.status === "failed"
      ? [{ at: row.date, label: "Payment failed — retry available" }]
      : []),
  ];
  return (
    <div className="space-y-4">
      <DetailSheetHeader
        eyebrow={row.kind === "order" ? "Order" : "Invoice"}
        title={row.number}
        subtitle={`${shortDate(row.date)} · ${row.service}`}
        meta={
          <StatusPill
            tone={
              row.status === "completed"
                ? "success"
                : row.status === "failed"
                  ? "danger"
                  : row.status === "processing"
                    ? "info"
                    : row.status === "on-hold"
                      ? "warning"
                      : "neutral"
            }
          >
            {row.status}
          </StatusPill>
        }
      />

      <div className="rounded-lg border border-border bg-muted/40 p-4">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Items</p>
        <div className="mt-2 flex items-start justify-between gap-3 text-sm">
          <div className="min-w-0">
            <p className="font-medium text-foreground">{row.service}</p>
            <p className="text-xs text-muted-foreground">Qty 1</p>
          </div>
          <p className="font-semibold tabular-nums">${row.total.toLocaleString()}</p>
        </div>
        <div className="mt-3 border-t border-border pt-2 text-sm">
          <div className="flex justify-between text-xs text-muted-foreground">
            <span>Subtotal</span>
            <span className="tabular-nums">${row.total.toLocaleString()}</span>
          </div>
          <div className="mt-2 flex justify-between font-semibold">
            <span>Total</span>
            <span className="tabular-nums">${row.total.toLocaleString()}</span>
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-border p-4">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Timeline</p>
        <ol className="mt-3 space-y-3">
          {timeline.map((t, idx) => (
            <li key={idx} className="flex items-start gap-3">
              <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full border border-border bg-card">
                <CalendarClock className="size-3 text-muted-foreground" />
              </span>
              <div>
                <p className="text-sm font-medium text-foreground">{t.label}</p>
                <p className="text-[11px] text-muted-foreground">{shortDateTime(t.at)}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </div>
  );
}

function PayInvoiceForm({
  row,
  onCancel,
  onPaid,
}: {
  row: OrderRow;
  onCancel: () => void;
  onPaid: () => void;
}) {
  return (
    <div className="space-y-3 text-sm">
      <div className="rounded-lg border border-border bg-muted/40 p-3">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {row.number}
        </p>
        <p className="mt-1 text-base font-semibold tabular-nums">${row.total.toLocaleString()}</p>
        <p className="text-xs text-muted-foreground">{row.service}</p>
      </div>
      <div className="rounded-lg border border-border p-3">
        <p className="text-xs font-medium text-foreground">Charge default card</p>
        <p className="mt-0.5 text-[11px] text-muted-foreground">
          Production: WooCommerce Stripe via{" "}
          <code className="rounded bg-muted px-1">/order-pay/{`{ORDER_ID}`}</code>.
        </p>
      </div>
      <DialogFooter className="gap-2 sm:gap-2">
        <Button variant="outline" size="sm" onClick={onCancel}>
          Cancel
        </Button>
        <Button size="sm" onClick={onPaid} data-testid="button-confirm-pay">
          Pay ${row.total.toLocaleString()}
        </Button>
      </DialogFooter>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PAYMENT METHODS TAB
// ─────────────────────────────────────────────────────────────────────────────

function PaymentMethodsTab({
  cards,
  setCards,
}: {
  cards: SavedCard[];
  setCards: React.Dispatch<React.SetStateAction<SavedCard[]>>;
}) {
  const { toast } = useToast();
  const [addOpen, setAddOpen] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null);

  const makeDefault = (id: string) => {
    setCards((prev) => prev.map((c) => ({ ...c, default: c.id === id })));
    const card = cards.find((c) => c.id === id);
    toast({
      title: "Default card updated",
      description: `${card?.brand} •••• ${card?.last4} is now the default for new charges.`,
    });
  };

  const useForAll = (id: string) => {
    const card = cards.find((c) => c.id === id);
    toast({
      title: "Updated all subscriptions",
      description: `${card?.brand} •••• ${card?.last4} now charges every active subscription. Production: bulk update via WooCommerce Subscriptions.`,
    });
  };

  const deleteCard = (id: string) => {
    setCards((prev) => prev.filter((c) => c.id !== id));
    setConfirmDelete(null);
    toast({
      title: "Card removed",
      description: "Production: deletes the saved Stripe payment method from your account.",
    });
  };

  const addCard = () => {
    const newCard: SavedCard = {
      id: `pm_${Date.now()}`,
      brand: "Visa",
      last4: String(Math.floor(1000 + Math.random() * 9000)),
      exp: "12 / 28",
      default: cards.length === 0,
    };
    setCards((prev) => [...prev, newCard]);
    setAddOpen(false);
    toast({
      title: "Payment method added",
      description: `${newCard.brand} •••• ${newCard.last4} saved. Production: collected via Stripe SetupIntent + Elements.`,
    });
  };

  return (
    <div className="space-y-4">
      <SectionHeader
        title="Saved payment methods"
        hint="Cards are stored securely in Stripe. Make any card the default or assign it to all your subscriptions."
        trailing={
          <Button
            size="sm"
            className="gap-1.5"
            onClick={() => setAddOpen(true)}
            data-testid="button-add-payment-method"
          >
            <Plus className="size-4" />
            Add payment method
          </Button>
        }
      />

      {cards.length === 0 ? (
        <EmptyState
          icon={CreditCard}
          title="No saved cards"
          description="Add a payment method so renewals don't fail."
          cta={{ label: "Add payment method", onClick: () => setAddOpen(true) }}
        />
      ) : (
        <ul className="space-y-3" data-testid="payment-methods-list">
          {cards.map((c) => (
            <AccountSummaryRow
              key={c.id}
              iconLabel={c.brand === "Mastercard" ? "MC" : c.brand}
              iconTone={c.default ? "primary" : "neutral"}
              title={`${c.brand} •••• ${c.last4}`}
              subtitle={`Expires ${c.exp}`}
              status={
                c.default ? { tone: "primary", label: "Default" } : undefined
              }
              actions={
                <>
                  {!c.default && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5"
                      onClick={() => makeDefault(c.id)}
                      data-testid={`button-default-${c.id}`}
                    >
                      <Star className="size-3.5" />
                      Make default
                    </Button>
                  )}
                  <Button
                    variant="outline"
                    size="sm"
                    className="gap-1.5"
                    onClick={() => useForAll(c.id)}
                    data-testid={`button-useall-${c.id}`}
                  >
                    <RefreshCcw className="size-3.5" />
                    Use for all subscriptions
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="gap-1.5 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/30"
                    onClick={() => setConfirmDelete(c.id)}
                    data-testid={`button-delete-${c.id}`}
                  >
                    <Trash2 className="size-3.5" />
                    Delete
                  </Button>
                </>
              }
              testId={`card-row-${c.id}`}
            />
          ))}
        </ul>
      )}

      <InlineHelp icon={ShieldCheck}>
        Cards never touch our servers — production uses{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/add-payment-method/</code>,{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/delete-payment-method/</code>, and{" "}
        <code className="rounded bg-muted px-1 text-[10px]">/set-default-payment-method/</code> via WooCommerce Stripe SetupIntent + Elements.
      </InlineHelp>

      <Sheet open={addOpen} onOpenChange={setAddOpen}>
        <SheetContent className="w-full sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Add payment method</SheetTitle>
          </SheetHeader>
          <div className="mt-4 space-y-3 text-sm">
            <div className="rounded-lg border border-border bg-muted/40 p-3">
              <p className="text-xs font-medium text-foreground">Stripe Elements</p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">
                Production renders a SetupIntent-backed Stripe Elements form here. Preview-only inputs below.
              </p>
            </div>
            <Input placeholder="Card number" data-testid="input-add-cc-number" />
            <div className="grid grid-cols-2 gap-2">
              <Input placeholder="MM / YY" data-testid="input-add-cc-exp" />
              <Input placeholder="CVC" data-testid="input-add-cc-cvc" />
            </div>
            <Input placeholder="ZIP / Postal code" />
            <InlineHelp icon={ShieldCheck}>
              We never see your card. Stored as a Stripe payment method token tied to your account.
            </InlineHelp>
          </div>
          <SheetFooter className="mt-4">
            <Button variant="outline" onClick={() => setAddOpen(false)}>
              Cancel
            </Button>
            <Button onClick={addCard} data-testid="button-save-new-payment">
              Save card
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!confirmDelete} onOpenChange={(v) => !v && setConfirmDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete payment method?</AlertDialogTitle>
            <AlertDialogDescription>
              You won't be able to use this card again unless you re-add it. If it was the default,
              we'll prompt you to pick a new default.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => confirmDelete && deleteCard(confirmDelete)}
              className="bg-rose-600 hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-800"
              data-testid="button-confirm-delete-card"
            >
              Delete card
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ADDRESSES TAB
// ─────────────────────────────────────────────────────────────────────────────

function AddressesTab({ customer }: { customer: { name: string; email: string } }) {
  const { toast } = useToast();
  const [billing, setBilling] = useState<AddressData>({
    ...DEFAULT_BILLING,
    email: customer.email,
  });
  const [shipping, setShipping] = useState<AddressData>(DEFAULT_SHIPPING);
  const [savedFlash, setSavedFlash] = useState<"billing" | "shipping" | null>(null);

  const saveBilling = () => {
    setSavedFlash("billing");
    setTimeout(() => setSavedFlash((f) => (f === "billing" ? null : f)), 2200);
    toast({
      title: "Billing address saved",
      description: "Production: WooCommerce /edit-address/billing/",
    });
  };

  const saveShipping = () => {
    setSavedFlash("shipping");
    setTimeout(() => setSavedFlash((f) => (f === "shipping" ? null : f)), 2200);
    toast({
      title: "Shipping address saved",
      description: "Production: WooCommerce /edit-address/shipping/",
    });
  };

  return (
    <div className="space-y-5">
      <SectionHeader
        title="Addresses"
        hint="Used on invoices, receipts, and any physical deliveries (e.g. printed brand books)."
      />

      <div className="grid gap-5 lg:grid-cols-2">
        <AppCard testId="address-billing">
          <div className="flex items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2 text-sm font-semibold">
                <Receipt className="size-4 text-muted-foreground" />
                Billing address
              </div>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Used on invoices and receipts.
              </p>
            </div>
            {savedFlash === "billing" && (
              <span
                className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                data-testid="address-billing-saved"
              >
                <Check className="size-3" />
                Saved
              </span>
            )}
          </div>
          <AddressForm
            value={billing}
            onChange={setBilling}
            includeContact
            idPrefix="billing"
          />
          <div className="mt-4 flex justify-end gap-2 border-t border-border pt-4">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setBilling({ ...DEFAULT_BILLING, email: customer.email })}
            >
              Reset
            </Button>
            <Button size="sm" onClick={saveBilling} data-testid="button-save-billing">
              Save billing address
            </Button>
          </div>
        </AppCard>

        <AppCard testId="address-shipping">
          <div className="flex items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2 text-sm font-semibold">
                <MapPin className="size-4 text-muted-foreground" />
                Shipping address
              </div>
              <p className="mt-0.5 text-xs text-muted-foreground">
                For physical deliveries — print materials, brand kits.
              </p>
            </div>
            {savedFlash === "shipping" && (
              <span
                className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                data-testid="address-shipping-saved"
              >
                <Check className="size-3" />
                Saved
              </span>
            )}
          </div>
          <AddressForm value={shipping} onChange={setShipping} idPrefix="shipping" />
          <div className="mt-4 flex justify-end gap-2 border-t border-border pt-4">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShipping(DEFAULT_SHIPPING)}
            >
              Reset
            </Button>
            <Button size="sm" onClick={saveShipping} data-testid="button-save-shipping">
              Save shipping address
            </Button>
          </div>
        </AppCard>
      </div>
    </div>
  );
}

function AddressForm({
  value,
  onChange,
  includeContact,
  idPrefix,
}: {
  value: AddressData;
  onChange: (v: AddressData) => void;
  includeContact?: boolean;
  idPrefix: string;
}) {
  const set = <K extends keyof AddressData>(k: K, v: AddressData[K]) =>
    onChange({ ...value, [k]: v });
  return (
    <div className="mt-4 grid gap-3 md:grid-cols-2">
      <Field label="First name" id={`${idPrefix}-first`} value={value.firstName} onChange={(v) => set("firstName", v)} />
      <Field label="Last name" id={`${idPrefix}-last`} value={value.lastName} onChange={(v) => set("lastName", v)} />
      <Field label="Company" id={`${idPrefix}-company`} value={value.company} onChange={(v) => set("company", v)} className="md:col-span-2" />
      <Field label="Address line 1" id={`${idPrefix}-addr1`} value={value.address1} onChange={(v) => set("address1", v)} className="md:col-span-2" />
      <Field label="Address line 2" id={`${idPrefix}-addr2`} value={value.address2} onChange={(v) => set("address2", v)} className="md:col-span-2" />
      <Field label="City" id={`${idPrefix}-city`} value={value.city} onChange={(v) => set("city", v)} />
      <Field label="State / Province" id={`${idPrefix}-state`} value={value.state} onChange={(v) => set("state", v)} />
      <Field label="Postal code" id={`${idPrefix}-postal`} value={value.postal} onChange={(v) => set("postal", v)} />
      <Field label="Country" id={`${idPrefix}-country`} value={value.country} onChange={(v) => set("country", v)} />
      {includeContact && (
        <>
          <Field label="Phone" id={`${idPrefix}-phone`} value={value.phone ?? ""} onChange={(v) => set("phone", v)} />
          <Field label="Email" id={`${idPrefix}-email`} value={value.email ?? ""} onChange={(v) => set("email", v)} />
        </>
      )}
    </div>
  );
}

function Field({
  label,
  id,
  value,
  onChange,
  className,
}: {
  label: string;
  id: string;
  value: string;
  onChange: (v: string) => void;
  className?: string;
}) {
  return (
    <div className={`space-y-1.5 ${className ?? ""}`}>
      <Label htmlFor={id} className="text-[11px] font-medium text-muted-foreground">
        {label}
      </Label>
      <Input
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="h-9 text-sm"
        data-testid={`input-${id}`}
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PROFILE TAB
// ─────────────────────────────────────────────────────────────────────────────

function ProfileTab({ customer }: { customer: { name: string; email: string; company?: string } }) {
  const { toast } = useToast();
  const [name, setName] = useState(customer.name);
  const [email, setEmail] = useState(customer.email);
  const [phone, setPhone] = useState("+1 (415) 555-0118");
  const [magicLinks, setMagicLinks] = useState(true);
  const [marketing, setMarketing] = useState(false);
  const [renewalEmails, setRenewalEmails] = useState(true);
  const [productUpdates, setProductUpdates] = useState(true);

  return (
    <div className="space-y-5">
      <SectionHeader
        title="Profile"
        hint="Account details, login, and notification preferences. Changes here update your WordPress user account."
      />

      <div className="grid gap-5 lg:grid-cols-2">
        <AppCard testId="profile-details">
          <h3 className="text-sm font-semibold">Account details</h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            This is what we display and send invoices to.
          </p>
          <div className="mt-4 grid gap-3">
            <Field id="profile-name" label="Full name" value={name} onChange={setName} />
            <Field id="profile-email" label="Email address" value={email} onChange={setEmail} />
            <Field id="profile-phone" label="Phone (optional)" value={phone} onChange={setPhone} />
            <Field
              id="profile-company"
              label="Company"
              value={customer.company ?? ""}
              onChange={() => undefined}
            />
          </div>
          <div className="mt-4 flex justify-end gap-2 border-t border-border pt-4">
            <Button
              size="sm"
              onClick={() =>
                toast({
                  title: "Profile saved",
                  description:
                    "Production: WooCommerce /edit-account/ — also updates the WP user record.",
                })
              }
              data-testid="button-save-profile"
            >
              Save changes
            </Button>
          </div>
        </AppCard>

        <AppCard testId="profile-login">
          <h3 className="text-sm font-semibold">Login & security</h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            We use passwordless magic-link sign-in. No passwords to remember or rotate.
          </p>
          <div className="mt-4 space-y-3">
            <ToggleRow
              label="Allow magic-link sign-in"
              description="Send a one-time link to your email to log in. Recommended."
              checked={magicLinks}
              onChange={setMagicLinks}
              testId="toggle-magic-link"
            />
            <button
              type="button"
              onClick={() =>
                toast({
                  title: "Sign-out from other devices",
                  description: "All other active sessions revoked.",
                })
              }
              className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
              data-testid="button-revoke-sessions"
            >
              Sign out of all other devices
              <ExternalLink className="size-3" />
            </button>
          </div>
        </AppCard>

        <AppCard className="lg:col-span-2" testId="profile-notifications">
          <h3 className="text-sm font-semibold">Notification preferences</h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Choose how we keep you in the loop. You can unsubscribe at any time.
          </p>
          <div className="mt-4 grid gap-3 md:grid-cols-2">
            <ToggleRow
              icon={Receipt}
              label="Renewal & invoice emails"
              description="Upcoming renewals, paid receipts, and failed-payment alerts."
              checked={renewalEmails}
              onChange={setRenewalEmails}
              testId="toggle-renewal-emails"
            />
            <ToggleRow
              icon={Bell}
              label="Project updates"
              description="When your team posts an update or needs your input."
              checked={productUpdates}
              onChange={setProductUpdates}
              testId="toggle-product-updates"
            />
            <ToggleRow
              icon={Mail}
              label="Marketing emails"
              description="Tips, case studies, and Oversee announcements."
              checked={marketing}
              onChange={setMarketing}
              testId="toggle-marketing"
            />
          </div>
          <div className="mt-4 flex justify-end gap-2 border-t border-border pt-4">
            <Button
              size="sm"
              onClick={() => toast({ title: "Preferences saved" })}
              data-testid="button-save-prefs"
            >
              Save preferences
            </Button>
          </div>
        </AppCard>
      </div>

      <InlineHelp icon={FileText}>
        Production wires this to <code className="rounded bg-muted px-1 text-[10px]">/edit-account/</code> for profile fields and the WP user-meta table for notification preferences.
      </InlineHelp>
    </div>
  );
}

function ToggleRow({
  label,
  description,
  checked,
  onChange,
  icon: Icon,
  testId,
}: {
  label: string;
  description: string;
  checked: boolean;
  onChange: (v: boolean) => void;
  icon?: typeof Bell;
  testId?: string;
}) {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/30 p-3">
      {Icon && (
        <span className="grid size-8 shrink-0 place-items-center rounded-md border border-border bg-card text-muted-foreground">
          <Icon className="size-4" />
        </span>
      )}
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-foreground">{label}</p>
        <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
      </div>
      <Switch
        checked={checked}
        onCheckedChange={onChange}
        data-testid={testId}
        className="mt-1"
      />
    </div>
  );
}

