// Client Onboarding — service-based setup checklist with Leadsie access cards.
//
// Refactored: PageHeader + SummaryStat strip + PriorityBanner for the Leadsie
// preview note + plain SectionCard for empty/footer reassurance. Now also
// renders a dedicated Completed section so clients can see what they've already
// finished, plus the OnboardingStepDialog for non-Leadsie kinds.
import { useMemo, useState } from "react";
import { CheckCircle2, ShieldCheck, Sparkles } from "lucide-react";
import { useDemoStore, type OnboardingStep } from "@/lib/demo-store";
import { LeadsieAccessDialog, OnboardingPlanCard } from "@/components/leadsie";
import { OnboardingStepDialog } from "@/components/onboarding-step-dialog";
import {
  PageHeader,
  SummaryStat,
  SectionCard,
  PriorityBanner,
} from "@/components/shared";

export default function ClientOnboarding() {
  const { customer, subscriptions, services, onboardingPlans, leadsieSettings } = useDemoStore();
  const [openLeadsie, setOpenLeadsie] = useState<{ planServiceId: string; step: OnboardingStep } | null>(null);
  const [openStep, setOpenStep] = useState<{ planServiceId: string; step: OnboardingStep } | null>(null);

  // Active subs determine which plans to show.
  const activeSubs = subscriptions.filter((s) => s.status === "active" || s.status === "past-due");

  const visiblePlans = useMemo(
    () =>
      activeSubs
        .map((sub) => {
          const plan = onboardingPlans.find((p) => p.serviceId === sub.serviceId);
          const service = services.find((s) => s.id === sub.serviceId);
          if (!plan || !service) return null;
          return { plan, service };
        })
        .filter(Boolean) as { plan: typeof onboardingPlans[number]; service: typeof services[number] }[],
    [activeSubs, onboardingPlans, services],
  );

  const totalSteps = visiblePlans.reduce((sum, { plan }) => sum + plan.steps.length, 0);
  const doneSteps = visiblePlans.reduce(
    (sum, { plan }) => sum + plan.steps.filter((s) => s.status === "complete").length,
    0,
  );
  const overallPct = totalSteps === 0 ? 0 : Math.round((doneSteps / totalSteps) * 100);
  const remaining = totalSteps - doneSteps;
  const firstName = customer.name.split(" ")[0];

  // Flatten completed steps across plans for the Completed section.
  const completedEntries = useMemo(() => {
    const out: { planLabel: string; serviceTitle: string; step: OnboardingStep }[] = [];
    visiblePlans.forEach(({ plan, service }) => {
      plan.steps
        .filter((s) => s.status === "complete")
        .forEach((step) => {
          out.push({ planLabel: plan.label ?? service.title, serviceTitle: service.title, step });
        });
    });
    return out;
  }, [visiblePlans]);

  return (
    <div className="space-y-6" data-testid="client-onboarding-page">
      <PageHeader
        eyebrow="Finish your setup"
        title={`Hi ${firstName} — let's get Oversee everything we need`}
        subtitle="Each service you bought has its own setup checklist. We use Leadsie for access requests so you never share a password — sign in with Google or Meta, click approve, and you're done."
        testId="onboarding-header"
      />

      <div className="grid grid-cols-2 gap-3 md:grid-cols-3" data-testid="onboarding-stats">
        <SummaryStat
          label="Overall progress"
          value={`${overallPct}%`}
          tone={overallPct >= 100 ? "success" : overallPct > 0 ? "primary" : "neutral"}
          testId="stat-onboarding-progress"
        />
        <SummaryStat
          label="Steps complete"
          value={`${doneSteps} of ${totalSteps}`}
          tone="neutral"
          testId="stat-onboarding-complete"
        />
        <SummaryStat
          label="Steps remaining"
          value={remaining}
          tone={remaining === 0 ? "success" : "warning"}
          testId="stat-onboarding-remaining"
        />
      </div>

      {!leadsieSettings.connected && (
        <PriorityBanner
          tone="warning"
          icon={ShieldCheck}
          eyebrow="Preview note"
          title="Leadsie isn't fully wired yet"
          subtitle="Clicking Grant access opens a walk-through of how the real flow will look. No data leaves your account."
          testId="onboarding-leadsie-banner"
        />
      )}

      {/* Plans */}
      {visiblePlans.length === 0 ? (
        <SectionCard testId="onboarding-empty">
          <div className="flex flex-col items-center px-4 py-12 text-center">
            <div className="grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
              <CheckCircle2 className="size-6" />
            </div>
            <h2 className="mt-3 text-base font-semibold">Nothing to set up yet</h2>
            <p className="mt-1 max-w-md text-sm text-muted-foreground">
              Once a service is active, its onboarding checklist will land here.
            </p>
          </div>
        </SectionCard>
      ) : (
        <div className="space-y-5" data-testid="onboarding-plans">
          {visiblePlans.map(({ plan, service }) => (
            <OnboardingPlanCard
              key={plan.serviceId}
              plan={plan}
              serviceTitle={service.title}
              audience="client"
              onOpenLeadsie={(step) => setOpenLeadsie({ planServiceId: plan.serviceId, step })}
              onOpenStep={(step, planServiceId) => setOpenStep({ planServiceId, step })}
            />
          ))}
        </div>
      )}

      {/* Completed steps section — visible only when at least one is done */}
      {completedEntries.length > 0 && (
        <SectionCard
          title="Completed"
          hint={`${completedEntries.length} step${completedEntries.length === 1 ? "" : "s"} finished — nothing else to do here.`}
          tone="success"
          testId="onboarding-completed"
        >
          <ul className="divide-y divide-border" data-testid="onboarding-completed-list">
            {completedEntries.map(({ planLabel, step }) => (
              <li
                key={`${planLabel}-${step.id}`}
                className="flex items-start gap-3 px-4 py-3"
                data-testid={`onboarding-completed-${step.id}`}
              >
                <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                  <CheckCircle2 className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium leading-snug">{step.title}</p>
                  <p className="mt-0.5 text-[11px] uppercase tracking-wide text-muted-foreground">
                    {planLabel}
                  </p>
                  {step.why && (
                    <p className="mt-1 line-clamp-2 max-w-prose text-xs text-muted-foreground">
                      {step.why}
                    </p>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </SectionCard>
      )}

      {/* Reassurance footer */}
      <SectionCard testId="onboarding-reassurance">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div className="flex items-start gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
              <Sparkles className="size-5" />
            </span>
            <div>
              <p className="text-sm font-semibold">Why we ask for access this way</p>
              <p className="mt-1 max-w-prose text-xs leading-relaxed text-muted-foreground">
                Leadsie issues us a least-privilege role on each platform. We can't see your password,
                you can revoke access in seconds, and our team is auditable. Industry standard for
                agency / client setup.
              </p>
            </div>
          </div>
          <div className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50/80 px-2.5 py-1 text-[11px] font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
            <ShieldCheck className="size-3" /> No password sharing
          </div>
        </div>
      </SectionCard>

      <LeadsieAccessDialog
        step={openLeadsie?.step ?? null}
        planServiceId={openLeadsie?.planServiceId ?? ""}
        onClose={() => setOpenLeadsie(null)}
        audience="client"
      />
      <OnboardingStepDialog
        step={openStep?.step ?? null}
        planServiceId={openStep?.planServiceId ?? ""}
        onClose={() => setOpenStep(null)}
      />
    </div>
  );
}
