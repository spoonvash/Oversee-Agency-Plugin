// Leadsie integration components — preview only.
//
// In production, Leadsie generates a magic link the client clicks. The client
// signs into Google/Meta/etc. using their existing login and approves the
// permissions our agency needs. No password sharing. Status updates flow back
// via webhook.
//
// In preview we render a mock modal that walks through that exact flow, so the
// admin and client can see how it will feel before the real Leadsie account is
// connected. Buttons are local-state + toasts.
import { useState } from "react";
import {
  AlertCircle,
  ArrowRight,
  CheckCircle2,
  ExternalLink,
  Globe,
  HelpCircle,
  Link2,
  Lock,
  Search,
  ShoppingBag,
  Sparkles,
  Tag,
} from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { useToast } from "@/hooks/use-toast";
import {
  useDemoStore,
  type LeadsiePlatform,
  type LeadsieRequestStatus,
  type OnboardingStep,
  type OnboardingPlan,
} from "@/lib/demo-store";

// ─────────────────────────────────────────────────────────────────────────────
// Platform metadata (icons, colors, copy). Lucide icons keep this preview
// free of external icon font dependencies.
// ─────────────────────────────────────────────────────────────────────────────

type PlatformMeta = {
  label: string;
  family: "google" | "meta" | "wordpress" | "shopify" | "linkedin" | "tiktok" | "klaviyo" | "mailchimp" | "highlevel";
  signInLabel: string; // e.g. "Sign in with Google"
  // Tailwind class fragment for tinted bg on the icon tile.
  accent: string;
  // Subtle color name for badge backgrounds.
  tone: "blue" | "indigo" | "rose" | "violet" | "amber" | "emerald" | "sky";
};

export const PLATFORM_META: Record<LeadsiePlatform, PlatformMeta> = {
 "google-analytics": { label: "Google Analytics 4", family: "google", signInLabel: "Sign in with Google", accent: "from-amber-500/15 to-orange-500/15 text-amber-700 dark:text-amber-300", tone: "amber" },
 "google-search-console": { label: "Google Search Console", family: "google", signInLabel: "Sign in with Google", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
 "google-business-profile": { label: "Google Business Profile", family: "google", signInLabel: "Sign in with Google", accent: "from-emerald-500/15 to-teal-500/15 text-emerald-700 dark:text-emerald-300", tone: "emerald" },
 "google-tag-manager": { label: "Google Tag Manager", family: "google", signInLabel: "Sign in with Google", accent: "from-sky-500/15 to-blue-500/15 text-sky-700 dark:text-sky-300", tone: "sky" },
 "google-ads": { label: "Google Ads", family: "google", signInLabel: "Sign in with Google", accent: "from-yellow-500/15 to-amber-500/15 text-amber-800 dark:text-amber-300", tone: "amber" },
 "google-merchant-center": { label: "Google Merchant Center", family: "google", signInLabel: "Sign in with Google", accent: "from-orange-500/15 to-amber-500/15 text-orange-700 dark:text-orange-300", tone: "amber" },
 "meta-business": { label: "Meta Business Manager", family: "meta", signInLabel: "Continue with Facebook", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
 "meta-page": { label: "Facebook Page", family: "meta", signInLabel: "Continue with Facebook", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
 "meta-ads": { label: "Meta Ads Manager", family: "meta", signInLabel: "Continue with Facebook", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
 "meta-pixel": { label: "Meta Pixel", family: "meta", signInLabel: "Continue with Facebook", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
 "meta-catalog": { label: "Meta Catalog", family: "meta", signInLabel: "Continue with Facebook", accent: "from-blue-500/15 to-indigo-500/15 text-blue-700 dark:text-blue-300", tone: "blue" },
  instagram: { label: "Instagram", family: "meta", signInLabel: "Continue with Instagram", accent: "from-rose-500/15 to-violet-500/15 text-rose-700 dark:text-rose-300", tone: "rose" },
  linkedin: { label: "LinkedIn Page", family: "linkedin", signInLabel: "Sign in with LinkedIn", accent: "from-sky-500/15 to-blue-500/15 text-sky-700 dark:text-sky-300", tone: "sky" },
  tiktok: { label: "TikTok Business", family: "tiktok", signInLabel: "Continue with TikTok", accent: "from-zinc-500/15 to-zinc-700/15 text-zinc-700 dark:text-zinc-200", tone: "violet" },
  shopify: { label: "Shopify", family: "shopify", signInLabel: "Continue with Shopify", accent: "from-emerald-500/15 to-teal-500/15 text-emerald-700 dark:text-emerald-300", tone: "emerald" },
  wordpress: { label: "WordPress", family: "wordpress", signInLabel: "Sign in to WordPress", accent: "from-zinc-500/15 to-zinc-700/15 text-zinc-700 dark:text-zinc-200", tone: "indigo" },
  klaviyo: { label: "Klaviyo", family: "klaviyo", signInLabel: "Sign in with Klaviyo", accent: "from-emerald-500/15 to-teal-500/15 text-emerald-700 dark:text-emerald-300", tone: "emerald" },
  mailchimp: { label: "Mailchimp", family: "mailchimp", signInLabel: "Sign in with Mailchimp", accent: "from-amber-500/15 to-yellow-500/15 text-amber-700 dark:text-amber-300", tone: "amber" },
  highlevel: { label: "HighLevel", family: "highlevel", signInLabel: "Sign in with HighLevel", accent: "from-violet-500/15 to-indigo-500/15 text-violet-700 dark:text-violet-300", tone: "violet" },
};

export function PlatformIcon({ platform, className = "size-5" }: { platform: LeadsiePlatform; className?: string }) {
  const meta = PLATFORM_META[platform];
  // Lightweight family-based icons. Real production uses brand SVGs.
  const Icon =
    meta.family === "google"
      ? platform === "google-search-console"
        ? Search
        : platform === "google-business-profile"
        ? Tag
        : Globe
      : meta.family === "meta"
      ? Sparkles
      : meta.family === "shopify"
      ? ShoppingBag
      : meta.family === "wordpress"
      ? Globe
      : Link2;
  return <Icon className={className} aria-hidden />;
}

export const STATUS_LABEL: Record<LeadsieRequestStatus, string> = {
 "not-started": "Not started",
 "link-sent": "Link sent",
 "client-opened": "Client opened",
  approved: "Access granted",
  blocked: "Blocked",
};

export const STATUS_TONE: Record<LeadsieRequestStatus, string> = {
 "not-started": "border-strong bg-zinc-100 text-zinc-700   dark:text-zinc-300",
 "link-sent": "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200",
 "client-opened": "border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-200",
  approved: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200",
  blocked: "border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200",
};

// ─────────────────────────────────────────────────────────────────────────────
// Mock Leadsie access modal — embedded preview of the client-facing flow.
// ─────────────────────────────────────────────────────────────────────────────

type LeadsieAccessDialogProps = {
  /** The onboarding step to show. */
  step: OnboardingStep | null;
  /** Service id of the parent plan (for state mutation). */
  planServiceId: string;
  onClose: () => void;
  /** Who is using the dialog. Admin sees "send link" framing. Client sees "approve". */
  audience: "admin" | "client";
};

export function LeadsieAccessDialog({ step, planServiceId, onClose, audience }: LeadsieAccessDialogProps) {
  const { setOnboardingStepStatus, leadsieSettings } = useDemoStore();
  const { toast } = useToast();
  const open = !!step && step.kind === "leadsie-access" && !!step.platform;
  const [phase, setPhase] = useState<"intro" | "signing" | "permissions" | "approved">("intro");

  if (!open || !step || !step.platform) return null;
  const meta = PLATFORM_META[step.platform];

  const reset = () => {
    setPhase("intro");
    onClose();
  };

  const sendLink = () => {
    setOnboardingStepStatus(planServiceId, step.id, "in-progress", "link-sent");
    toast({
      title: "Access request sent",
      description: leadsieSettings.connected
        ? `Email sent via Leadsie to the client. They'll see a one-click ${meta.signInLabel} flow.`
        : "Preview only — Leadsie isn't connected yet, so no real email was sent.",
    });
  };

  const advance = () => {
    if (phase === "intro") {
      setPhase("signing");
    } else if (phase === "signing") {
      setPhase("permissions");
      setOnboardingStepStatus(planServiceId, step.id, "in-progress", "client-opened");
    } else if (phase === "permissions") {
      setPhase("approved");
      setOnboardingStepStatus(planServiceId, step.id, "complete", "approved");
      toast({
        title: "Access granted",
        description: `${meta.label} access is now live for Oversee. The agency was notified automatically.`,
      });
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !v && reset()}>
      <DialogContent className="max-w-2xl gap-0 overflow-hidden p-0 sm:max-w-2xl" data-testid="dialog-leadsie">
        {/* Header band — Leadsie + platform brand */}
        <div className={`relative bg-gradient-to-br ${meta.accent} px-6 py-5`}>
          <DialogHeader className="space-y-2">
            <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em]">
              <span className="inline-flex items-center gap-1.5">
                <Lock className="size-3" /> Secure access via Leadsie
              </span>
              {!leadsieSettings.connected && (
                <Badge variant="outline" className="border-orange-400 bg-orange-50 text-[10px] uppercase text-orange-800 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-200">
                  Preview · setup pending
                </Badge>
              )}
            </div>
            <DialogTitle className="flex items-center gap-3 text-xl">
              <span className="grid size-10 place-items-center rounded-lg bg-white/70 shadow-sm /80">
                <PlatformIcon platform={step.platform} className="size-5" />
              </span>
              <span>{meta.label}</span>
            </DialogTitle>
            <p className="text-sm text-foreground/80">{step.why}</p>
          </DialogHeader>
        </div>

        {/* Body — phase-specific */}
        <div className="space-y-4 px-6 py-5">
          {phase === "intro" && (
            <>
              <Section title="What we're asking for">
                <ul className="space-y-1.5 text-sm">
                  {(step.permissions ?? []).map((p) => (
                    <li key={p} className="flex items-start gap-2">
                      <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                      <span>{p}</span>
                    </li>
                  ))}
                </ul>
              </Section>
              <Section title="How it works">
                <ol className="space-y-1.5 text-sm text-muted-foreground">
                  <li>1. Click the button below to open Leadsie's secure request page.</li>
                  <li>2. {meta.signInLabel} (no Leadsie account needed for the client).</li>
                  <li>3. Approve the listed permissions in one click.</li>
                  <li>4. Access is granted to Oversee automatically — no password sharing.</li>
                </ol>
              </Section>
              <Note>
                Request URL preview:{" "}
                <code className="rounded bg-muted px-1.5 py-0.5 text-xs">{step.requestUrl}</code>
              </Note>
            </>
          )}

          {phase === "signing" && (
            <Section title={meta.signInLabel}>
              <div className="rounded-lg border border-border bg-muted/30 p-5 text-center">
                <div className="mx-auto grid size-12 place-items-center rounded-full bg-card shadow-sm">
                  <PlatformIcon platform={step.platform} className="size-6" />
                </div>
                <p className="mt-3 text-sm font-medium">Choose an account</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  In production, this is the real {meta.family} sign-in screen.
                </p>
                <div className="mx-auto mt-4 max-w-xs space-y-2">
                  <button
                    type="button"
                    className="flex w-full items-center gap-3 rounded-md border border-border bg-card px-3 py-2 text-left text-sm transition hover:border-orange-300 hover:bg-orange-50/40 dark:hover:border-orange-900/60 dark:hover:bg-orange-950/15"
                    data-testid="leadsie-mock-account"
                  >
                    <span className="grid size-7 place-items-center rounded-full bg-orange-100 text-xs font-semibold text-orange-700 dark:bg-orange-950/40 dark:text-orange-300">
                      ML
                    </span>
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-medium">Maya Lin</span>
                      <span className="block truncate text-[11px] text-muted-foreground">maya@northstargallery.com</span>
                    </span>
                  </button>
                </div>
              </div>
            </Section>
          )}

          {phase === "permissions" && (
            <Section title={`${meta.label} wants the following access`}>
              <ul className="divide-y divide-border rounded-lg border border-border">
                {(step.permissions ?? []).map((p) => (
                  <li key={p} className="flex items-start gap-3 px-4 py-3 text-sm">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>{p}</span>
                  </li>
                ))}
              </ul>
              <Note>
                Oversee will use this access only to deliver the service you purchased. You can revoke at any time from
                your account's security settings.
              </Note>
            </Section>
          )}

          {phase === "approved" && (
            <div className="flex flex-col items-center gap-3 py-6 text-center">
              <div className="grid size-14 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                <CheckCircle2 className="size-7" />
              </div>
              <p className="text-base font-semibold">{meta.label} access granted</p>
              <p className="max-w-sm text-sm text-muted-foreground">
                Oversee can now start working on your account. We'll send a confirmation in your Updates feed.
              </p>
            </div>
          )}
        </div>

        <DialogFooter className="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
          {audience === "admin" && phase === "intro" ? (
            <div className="flex flex-1 items-center gap-2 text-xs text-muted-foreground">
              <ExternalLink className="size-3.5" />
              <span>Leadsie sends the request via email + SMS to the client.</span>
            </div>
          ) : (
            <span />
          )}
          <div className="flex flex-wrap items-center justify-end gap-2">
            {audience === "admin" && phase === "intro" && (
              <Button
                variant="outline"
                onClick={() => {
                  sendLink();
                  reset();
                }}
                data-testid="button-leadsie-send-link"
              >
                Send via email
              </Button>
            )}
            {phase !== "approved" ? (
              <Button onClick={advance} className="gap-1.5" data-testid="button-leadsie-advance">
                {phase === "intro"
                  ? audience === "client"
                    ? "Continue to sign-in"
                    : "Preview client flow"
                  : phase === "signing"
                  ? "Continue"
                  : "Approve access"}
                <ArrowRight className="size-4" />
              </Button>
            ) : (
              <Button onClick={reset} className="gap-1.5" data-testid="button-leadsie-done">
                Done
              </Button>
            )}
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{title}</p>
      {children}
    </section>
  );
}

function Note({ children }: { children: React.ReactNode }) {
  return (
    <p className="flex items-start gap-2 rounded-md border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
      <AlertCircle className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
      <span>{children}</span>
    </p>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Onboarding plan card + step rows
// ─────────────────────────────────────────────────────────────────────────────

export function OnboardingPlanCard({
  plan,
  serviceTitle,
  onOpenLeadsie,
  onOpenStep,
  audience,
}: {
  plan: OnboardingPlan;
  serviceTitle: string;
  onOpenLeadsie: (step: OnboardingStep) => void;
  onOpenStep?: (step: OnboardingStep, planServiceId: string) => void;
  audience: "admin" | "client";
}) {
  const total = plan.steps.length;
  const done = plan.steps.filter((s) => s.status === "complete").length;
  const pct = Math.round((done / total) * 100);
  const nextStep = plan.steps.find((s) => s.status !== "complete");

  return (
    <section
      className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      data-testid={`onboarding-card-${plan.serviceId}`}
    >
      {/* Header */}
      <header className="flex flex-col gap-3 border-b border-border bg-gradient-to-br from-orange-50 to-orange-50/40 px-5 py-4 dark:from-orange-950/30 dark:to-orange-950/10 md:flex-row md:items-center md:justify-between">
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-700 dark:text-orange-300">
            Onboarding
          </p>
          <h3 className="mt-0.5 truncate text-base font-semibold leading-snug">
            {plan.label ?? serviceTitle}
          </h3>
          {nextStep && pct < 100 ? (
            <p className="mt-1 text-xs text-muted-foreground">
              Next: <span className="font-medium text-foreground">{nextStep.title}</span>
              {nextStep.due && (
                <>
                  {" · due "}
                  {new Date(nextStep.due).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
                </>
              )}
            </p>
          ) : (
            <p className="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
              All onboarding steps are complete. We'll let you know when work kicks off.
            </p>
          )}
        </div>
        <div className="flex flex-col items-end gap-1 md:min-w-[180px]">
          <div className="flex items-center gap-2 text-xs">
            <span className="font-medium tabular-nums">
              {done}/{total} done
            </span>
            <Badge variant="outline" className="tabular-nums">
              {pct}%
            </Badge>
          </div>
          <Progress value={pct} className="h-2 w-full md:w-44" />
        </div>
      </header>

      {/* Steps */}
      <ul className="divide-y divide-border">
        {plan.steps.map((step) => (
          <OnboardingStepRow
            key={step.id}
            step={step}
            planServiceId={plan.serviceId}
            audience={audience}
            onOpenLeadsie={onOpenLeadsie}
            onOpenStep={onOpenStep}
          />
        ))}
      </ul>
    </section>
  );
}

function OnboardingStepRow({
  step,
  planServiceId,
  audience,
  onOpenLeadsie,
  onOpenStep,
}: {
  step: OnboardingStep;
  planServiceId: string;
  audience: "admin" | "client";
  onOpenLeadsie: (step: OnboardingStep) => void;
  onOpenStep?: (step: OnboardingStep, planServiceId: string) => void;
}) {
  const { setOnboardingStepStatus } = useDemoStore();
  const { toast } = useToast();

  const platformMeta = step.platform ? PLATFORM_META[step.platform] : null;
  const statusKey = step.leadsieStatus ?? (step.status === "complete" ? "approved" : step.status === "in-progress" ? "client-opened" : "not-started");

  const ctaLabel =
    step.status === "complete"
      ? "View details"
      : step.kind === "leadsie-access"
      ? step.status === "in-progress"
        ? "Open request"
        : "Grant access"
      : step.kind === "intake-form"
      ? "Open form"
      : step.kind === "upload"
      ? "Upload files"
      : step.kind === "schedule"
      ? "Choose a time"
      : "Mark complete";

  const handleClick = () => {
    if (step.status === "complete") {
      toast({ title: step.title, description: "Already complete. Nothing else needed." });
      return;
    }
    if (step.kind === "leadsie-access") {
      onOpenLeadsie(step);
      return;
    }
    // Non-Leadsie steps: open the real preview dialog if a handler was passed.
    if (onOpenStep) {
      onOpenStep(step, planServiceId);
      return;
    }
    // Fallback (admin context, etc.) — mark complete with a toast.
    setOnboardingStepStatus(planServiceId, step.id, "complete");
    toast({
      title: `${step.title} — saved`,
      description: "Preview only. In production this opens the relevant form, scheduler, or uploader.",
    });
  };

  return (
    <li className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
      <div className="flex min-w-0 items-start gap-3">
        <span className={`mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg border ${
          step.status === "complete"
            ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300"
            : step.kind === "leadsie-access"
            ? "border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-300"
            : "border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-300"
        }`} aria-hidden>
          {step.status === "complete" ? (
            <CheckCircle2 className="size-4" />
          ) : platformMeta ? (
            <PlatformIcon platform={step.platform!} className="size-4" />
          ) : step.kind === "intake-form" ? (
            <Tag className="size-4" />
          ) : step.kind === "upload" ? (
            <ShoppingBag className="size-4" />
          ) : (
            <Globe className="size-4" />
          )}
        </span>
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <h4 className="truncate text-sm font-semibold leading-snug">{step.title}</h4>
            {step.optional && (
              <Badge variant="outline" className="text-[10px] uppercase tracking-wider">
                Optional
              </Badge>
            )}
            {step.kind === "leadsie-access" && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <button
                    type="button"
                    className="text-muted-foreground hover:text-foreground"
                    aria-label="Why we need this"
                    data-testid={`tooltip-why-${step.id}`}
                  >
                    <HelpCircle className="size-3.5" />
                  </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">
                  <p className="text-xs leading-relaxed">{step.why}</p>
                  {(step.permissions ?? []).length > 0 && (
                    <ul className="mt-1.5 list-inside list-disc text-[11px] text-muted-foreground">
                      {(step.permissions ?? []).map((p) => (
                        <li key={p}>{p}</li>
                      ))}
                    </ul>
                  )}
                </TooltipContent>
              </Tooltip>
            )}
          </div>
          <p className="mt-1 line-clamp-2 max-w-prose text-xs text-muted-foreground">{step.why}</p>
          <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
            <Badge
              variant="outline"
              className={`gap-1 px-1.5 text-[10px] uppercase tracking-wide ${STATUS_TONE[statusKey]}`}
              data-testid={`step-status-${step.id}`}
            >
              {STATUS_LABEL[statusKey]}
            </Badge>
            {step.due && (
              <span>
                Due {new Date(step.due).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
              </span>
            )}
            {platformMeta && audience === "admin" && (
              <span className="hidden md:inline-flex">
                Leadsie URL:{" "}
                <code className="ml-1 rounded bg-muted px-1 py-0.5 text-[10px]">{step.requestUrl}</code>
              </span>
            )}
          </div>
        </div>
      </div>
      <div className="md:shrink-0">
        <Button
          size="sm"
          variant={step.status === "complete" ? "outline" : "default"}
          className={`h-9 w-full gap-1.5 md:w-auto ${
            step.status === "complete"
              ? ""
              : "bg-orange-600 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
          }`}
          onClick={handleClick}
          data-testid={`button-step-${step.id}`}
        >
          {ctaLabel}
          <ArrowRight className="size-3.5" />
        </Button>
      </div>
    </li>
  );
}
