// OnboardingStepDialog — preview UX for non-Leadsie onboarding steps.
//
// In production each kind opens a different real flow:
//   - intake-form  → Gravity Forms / Typeform embed
//   - upload       → file picker that drops into WordPress media
//   - schedule     → Calendly / GoHighLevel calendar embed
//   - approve      → contract / brief approval flow
//
// In preview we render a small purpose-built form for each kind that actually
// captures input, then marks the step complete via setOnboardingStepStatus.
// The upload kind embeds the same real <input type="file"> pipeline used by
// the rest of the site and stores files via the shared `addFile` action so
// they appear in the Files page and item drawers.
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ArrowRight,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  CloudUpload,
  FileSignature,
  FileText,
  Image as ImageIcon,
  Trash2,
} from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { useDemoStore, type OnboardingStep, type FileEntry } from "@/lib/demo-store";
import { useToast } from "@/hooks/use-toast";

type Props = {
  step: OnboardingStep | null;
  planServiceId: string;
  onClose: () => void;
};

// Static schedule slots for the preview scheduler.
const SCHEDULE_SLOTS = [
  { id: "s1", label: "Tue, May 5 — 10:00 AM CT" },
  { id: "s2", label: "Tue, May 5 — 2:30 PM CT" },
  { id: "s3", label: "Wed, May 6 — 9:00 AM CT" },
  { id: "s4", label: "Wed, May 6 — 4:00 PM CT" },
  { id: "s5", label: "Thu, May 7 — 11:00 AM CT" },
];

const ACCEPT =
  "image/*,application/pdf,video/*,.doc,.docx,.txt,.zip,.csv,.xls,.xlsx,.ppt,.pptx";

type Staged = {
  key: string;
  file: File;
  previewUrl?: string;
  kind: FileEntry["kind"];
};

function inferKind(file: File): FileEntry["kind"] {
  if (file.type.startsWith("image/")) return "image";
  if (file.type === "application/pdf" || /\.pdf$/i.test(file.name)) return "pdf";
  if (file.type.startsWith("video/")) return "video";
  return "doc";
}

function humanSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(kb < 10 ? 1 : 0)} KB`;
  const mb = kb / 1024;
  return `${mb.toFixed(mb < 10 ? 1 : 0)} MB`;
}

export function OnboardingStepDialog({ step, planServiceId, onClose }: Props) {
  const { setOnboardingStepStatus, addFile } = useDemoStore();
  const { toast } = useToast();
  const open = !!step && step.kind !== "leadsie-access";

  const [intake, setIntake] = useState({ goals: "", audience: "", competitors: "" });
  const [slot, setSlot] = useState<string | null>(null);
  const [agreed, setAgreed] = useState(false);
  const [staged, setStaged] = useState<Staged[]>([]);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const reset = useCallback(() => {
    setIntake({ goals: "", audience: "", competitors: "" });
    setSlot(null);
    setAgreed(false);
    setStaged((prev) => {
      prev.forEach((s) => {
        if (s.previewUrl) URL.revokeObjectURL(s.previewUrl);
      });
      return [];
    });
    onClose();
  }, [onClose]);

  // Reset when the step changes (different step opens).
  useEffect(() => {
    if (!step) {
      setIntake({ goals: "", audience: "", competitors: "" });
      setSlot(null);
      setAgreed(false);
      setStaged((prev) => {
        prev.forEach((s) => {
          if (s.previewUrl) URL.revokeObjectURL(s.previewUrl);
        });
        return [];
      });
    }
  }, [step]);

  if (!open || !step) return null;

  const stageFiles = (fileList: FileList | File[]) => {
    const files = Array.from(fileList);
    if (files.length === 0) return;
    const next: Staged[] = files.map((file, idx) => {
      const kind = inferKind(file);
      const previewUrl = kind === "image" ? URL.createObjectURL(file) : undefined;
      return {
        key: `${file.name}-${file.size}-${file.lastModified}-${idx}-${Date.now()}`,
        file,
        previewUrl,
        kind,
      };
    });
    setStaged((prev) => [...prev, ...next]);
  };

  const removeStaged = (key: string) => {
    setStaged((prev) => {
      const target = prev.find((s) => s.key === key);
      if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl);
      return prev.filter((s) => s.key !== key);
    });
  };

  const submit = () => {
    let okMessage = "";
    if (step.kind === "intake-form") {
      if (!intake.goals.trim() || !intake.audience.trim()) {
        toast({
          title: "Fill in goals and audience",
          description: "Both fields help us scope the work accurately.",
        });
        return;
      }
      okMessage = "Your answers are saved. Your team will use them to scope the next sprint.";
    } else if (step.kind === "upload") {
      if (staged.length === 0) {
        toast({
          title: "Pick at least one file",
          description: "Drop a file or use Browse to attach an asset.",
        });
        return;
      }
      const now = new Date().toISOString();
      staged.forEach((s) => {
        addFile({
          name: s.file.name,
          kind: s.kind,
          src: s.previewUrl,
          size: humanSize(s.file.size),
          uploadedAt: now,
          uploadedBy: "Maya Lin",
          approval: "pending",
          version: 1,
        });
      });
      okMessage = `${staged.length} file${staged.length === 1 ? "" : "s"} attached and shared with your team.`;
    } else if (step.kind === "schedule") {
      if (!slot) {
        toast({ title: "Choose a time", description: "Pick one of the available slots above." });
        return;
      }
      const chosen = SCHEDULE_SLOTS.find((s) => s.id === slot)?.label;
      okMessage = `Locked in: ${chosen}. We'll send a calendar invite shortly.`;
    } else if (step.kind === "approve") {
      if (!agreed) {
        toast({ title: "Confirm to continue", description: "Tick the box to acknowledge." });
        return;
      }
      okMessage = "Thanks — your approval has been recorded.";
    } else {
      okMessage = "Saved.";
    }
    setOnboardingStepStatus(planServiceId, step.id, "complete");
    toast({ title: `${step.title} — done`, description: okMessage });
    // For upload, don't revoke URLs — the addFile records reference them.
    if (step.kind === "upload") {
      setStaged([]);
      onClose();
    } else {
      reset();
    }
  };

  const titleIcon =
    step.kind === "intake-form" ? (
      <ClipboardList className="size-5" />
    ) : step.kind === "upload" ? (
      <CloudUpload className="size-5" />
    ) : step.kind === "schedule" ? (
      <CalendarClock className="size-5" />
    ) : (
      <FileSignature className="size-5" />
    );

  return (
    <Dialog open={open} onOpenChange={(v) => !v && reset()}>
      <DialogContent className="max-w-xl gap-0 overflow-hidden p-0" data-testid="dialog-onboarding-step">
        <DialogHeader className="space-y-1 border-b border-border bg-muted/40 px-6 py-4">
          <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
            {titleIcon} Onboarding step
          </p>
          <DialogTitle className="text-lg">{step.title}</DialogTitle>
          <p className="text-xs text-muted-foreground">{step.why}</p>
        </DialogHeader>

        <div className="space-y-4 px-6 py-5">
          {step.kind === "intake-form" && (
            <>
              <Field label="Top 1–2 goals for this engagement">
                <Textarea
                  value={intake.goals}
                  onChange={(e) => setIntake((p) => ({ ...p, goals: e.target.value }))}
                  placeholder="e.g. Triple newsletter sign-ups, launch a member portal"
                  className="min-h-[64px] resize-none text-sm"
                  data-testid="textarea-intake-goals"
                />
              </Field>
              <Field label="Who is your primary audience?">
                <Input
                  value={intake.audience}
                  onChange={(e) => setIntake((p) => ({ ...p, audience: e.target.value }))}
                  placeholder="e.g. mid-career collectors in the Pacific Northwest"
                  className="h-9 text-sm"
                  data-testid="input-intake-audience"
                />
              </Field>
              <Field label="Two or three competitors / inspirations">
                <Input
                  value={intake.competitors}
                  onChange={(e) => setIntake((p) => ({ ...p, competitors: e.target.value }))}
                  placeholder="e.g. davidzwirner.com, halegallery.com"
                  className="h-9 text-sm"
                  data-testid="input-intake-competitors"
                />
              </Field>
            </>
          )}

          {step.kind === "upload" && (
            <div className="space-y-3">
              <input
                ref={inputRef}
                type="file"
                multiple
                accept={ACCEPT}
                onChange={(e) => {
                  if (e.target.files && e.target.files.length > 0) {
                    stageFiles(e.target.files);
                    e.target.value = "";
                  }
                }}
                className="sr-only"
                data-testid="input-onboarding-upload-file"
                aria-label="Choose files to attach"
              />
              <div
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                  e.preventDefault();
                  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    stageFiles(e.dataTransfer.files);
                  }
                }}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    inputRef.current?.click();
                  }
                }}
                role="button"
                tabIndex={0}
                data-testid="onboarding-upload-dropzone"
                className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border bg-muted/30 px-6 py-8 text-center transition hover:border-primary/60 hover:bg-muted/50"
              >
                <CloudUpload className="size-7 text-muted-foreground" />
                <p className="text-sm font-medium">
                  Drop files here, or{" "}
                  <span className="text-primary underline-offset-2 hover:underline">browse</span>
                </p>
                <p className="text-[11px] text-muted-foreground">
                  Brand assets, logos, references — any common file format.
                </p>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="mt-1 gap-1"
                  onClick={(e) => {
                    e.stopPropagation();
                    inputRef.current?.click();
                  }}
                  data-testid="button-onboarding-browse"
                >
                  <CloudUpload className="size-3.5" /> Browse files
                </Button>
              </div>

              {staged.length > 0 && (
                <ul className="space-y-1.5" data-testid="onboarding-upload-staged-list">
                  {staged.map((s) => (
                    <li
                      key={s.key}
                      className="flex items-center gap-3 rounded-md border border-border bg-card p-2"
                      data-testid={`onboarding-staged-${s.file.name}`}
                    >
                      <div className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md border border-border bg-muted">
                        {s.kind === "image" && s.previewUrl ? (
                          <img src={s.previewUrl} alt={s.file.name} className="size-full object-cover" />
                        ) : (
                          <FileText className="size-4 text-muted-foreground" />
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{s.file.name}</p>
                        <p className="text-[11px] text-muted-foreground">
                          {humanSize(s.file.size)} · {s.kind}
                        </p>
                      </div>
                      {s.kind === "image" && (
                        <span className="grid size-6 place-items-center rounded-md bg-primary/10 text-primary">
                          <ImageIcon className="size-3" />
                        </span>
                      )}
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 gap-1 text-xs"
                        onClick={() => removeStaged(s.key)}
                        data-testid={`button-onboarding-remove-${s.file.name}`}
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}

          {step.kind === "schedule" && (
            <div>
              <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                Available times
              </p>
              <ul className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                {SCHEDULE_SLOTS.map((s) => {
                  const active = slot === s.id;
                  return (
                    <li key={s.id}>
                      <button
                        type="button"
                        onClick={() => setSlot(s.id)}
                        className={`w-full rounded-md border px-3 py-2 text-left text-sm transition ${
                          active
                            ? "border-primary bg-primary/5"
                            : "border-border bg-card hover:border-primary/40"
                        }`}
                        data-testid={`onboarding-slot-${s.id}`}
                      >
                        {s.label}
                      </button>
                    </li>
                  );
                })}
              </ul>
              <p className="mt-2 text-[11px] text-muted-foreground">
                Times shown in your local timezone. We send calendar invites and a Zoom link.
              </p>
            </div>
          )}

          {step.kind === "approve" && (
            <div className="space-y-3">
              {(step.permissions ?? []).length > 0 && (
                <div>
                  <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    What you're approving
                  </p>
                  <ul className="space-y-1.5 text-sm">
                    {(step.permissions ?? []).map((p) => (
                      <li key={p} className="flex items-start gap-2">
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>{p}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
              <label className="flex cursor-pointer items-start gap-2 rounded-md border border-border bg-muted/30 p-3 text-sm">
                <Checkbox
                  checked={agreed}
                  onCheckedChange={(v) => setAgreed(!!v)}
                  data-testid="checkbox-onboarding-agree"
                  className="mt-0.5"
                />
                <span>
                  I have read the above and approve. I understand this can be revoked at any time from my
                  account settings.
                </span>
              </label>
            </div>
          )}

          <div className="rounded-md border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
            <Badge variant="outline" className="mr-2 text-[10px] uppercase tracking-wide">
              Preview
            </Badge>
            In production this opens the real form, scheduler, or signed approval flow. Your input here is
            stored locally for the demo only.
          </div>
        </div>

        <DialogFooter className="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-6 py-3 sm:flex-row sm:items-center sm:justify-end">
          <Button variant="ghost" size="sm" onClick={reset} data-testid="button-onboarding-cancel">
            Cancel
          </Button>
          <Button size="sm" onClick={submit} className="gap-1" data-testid="button-onboarding-submit">
            {step.kind === "schedule"
              ? "Confirm time"
              : step.kind === "approve"
                ? "Approve"
                : step.kind === "upload"
                  ? "Attach files"
                  : "Submit"}
            <ArrowRight className="size-3.5" />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
        {label}
      </p>
      {children}
    </div>
  );
}
