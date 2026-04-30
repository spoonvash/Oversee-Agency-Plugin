// Direct, simple Create Board form. No templates, no template-cards.
// Admins enter board name, pick a client, optionally set service/category,
// due date, starting stage, optional first task, optional notes. Submit
// creates the board in the shared store and navigates to it.
import { useEffect, useMemo, useState } from "react";
import { useLocation } from "wouter";
import { Briefcase } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useDemoStore } from "@/lib/demo-store";
import { useToast } from "@/hooks/use-toast";

const SERVICE_TYPES = [
  "Brand strategy",
  "Web design",
  "Web development",
  "SEO",
  "Content",
  "Paid ads",
  "Video",
  "Other",
];

const STARTING_STAGES = [
  "Backlog",
  "Working On It",
  "Waiting on Client",
  "In Review",
];

type FormState = {
  name: string;
  clientEmail: string;
  serviceType: string;
  due: string;
  startingStage: string;
  firstTask: string;
  description: string;
};

const EMPTY_FORM: FormState = {
  name: "",
  clientEmail: "",
  serviceType: "",
  due: "",
  startingStage: "Backlog",
  firstTask: "",
  description: "",
};

export function CreateBoardWizard({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  onCreated?: (boardId: string) => void;
}) {
  const { addBoard, addItem, updateBoard, clients } = useDemoStore();
  const { toast } = useToast();
  const [, navigate] = useLocation();

  const clientOptions = useMemo(
    () =>
      clients
        .filter((c) => c.email)
        .map((c) => ({ name: c.name, company: c.company, email: c.email })),
    [clients],
  );

  const [form, setForm] = useState<FormState>(() => ({
    ...EMPTY_FORM,
    clientEmail: clientOptions[0]?.email ?? "",
  }));

  // Whenever the dialog opens, refresh defaults.
  useEffect(() => {
    if (open) {
      setForm({
        ...EMPTY_FORM,
        clientEmail: clientOptions[0]?.email ?? "",
      });
    }
  }, [open, clientOptions]);

  const update = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const handleCreate = () => {
    const name = form.name.trim();
    if (!name) {
      toast({
        title: "Board name required",
        description: "Give your board a clear name first.",
        variant: "destructive",
      });
      return;
    }
    const id = addBoard(name, form.clientEmail, form.description.trim());
    updateBoard(id, {
      serviceType: form.serviceType || undefined,
      due: form.due ? new Date(form.due).toISOString() : undefined,
    });
    if (form.firstTask.trim()) {
      addItem(id, form.firstTask.trim(), { workflowStage: form.startingStage });
    }
    toast({
      title: "Board created",
      description: `${name} is ready.`,
    });
    onOpenChange(false);
    if (onCreated) onCreated(id);
    setTimeout(() => navigate("/admin/boards"), 50);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl" data-testid="dialog-create-board">
        <DialogHeader>
          <DialogTitle>Create a board</DialogTitle>
          <DialogDescription>
            Add a project board. Only the name and client are required — fill in the rest if you want.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="board-name" className="text-xs">
              Board name <span className="text-rose-500">*</span>
            </Label>
            <Input
              id="board-name"
              value={form.name}
              onChange={(e) => update("name", e.target.value)}
              placeholder="e.g. Northstar Gallery — Site Refresh"
              autoFocus
              data-testid="input-board-name"
            />
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="board-client" className="text-xs">
                Client <span className="text-rose-500">*</span>
              </Label>
              <Select
                value={form.clientEmail}
                onValueChange={(v) => update("clientEmail", v)}
              >
                <SelectTrigger id="board-client" data-testid="select-client">
                  <SelectValue placeholder="Pick a client" />
                </SelectTrigger>
                <SelectContent>
                  {clientOptions.map((c) => (
                    <SelectItem key={c.email} value={c.email}>
                      {c.name} · {c.company}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs">
                Service or category <span className="text-muted-foreground">(optional)</span>
              </Label>
              <Select
                value={form.serviceType || "none"}
                onValueChange={(v) => update("serviceType", v === "none" ? "" : v)}
              >
                <SelectTrigger data-testid="select-service-type">
                  <SelectValue placeholder="Choose…" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">— None —</SelectItem>
                  {SERVICE_TYPES.map((s) => (
                    <SelectItem key={s} value={s}>{s}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="board-due" className="text-xs">
                Due date <span className="text-muted-foreground">(optional)</span>
              </Label>
              <Input
                id="board-due"
                type="date"
                value={form.due}
                onChange={(e) => update("due", e.target.value)}
                data-testid="input-board-due"
              />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs">
                Starting stage <span className="text-muted-foreground">(optional)</span>
              </Label>
              <Select
                value={form.startingStage}
                onValueChange={(v) => update("startingStage", v)}
              >
                <SelectTrigger data-testid="select-starting-stage">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {STARTING_STAGES.map((s) => (
                    <SelectItem key={s} value={s}>{s}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="board-first-task" className="text-xs">
              First task or item <span className="text-muted-foreground">(optional)</span>
            </Label>
            <Input
              id="board-first-task"
              value={form.firstTask}
              onChange={(e) => update("firstTask", e.target.value)}
              placeholder="e.g. Sitemap & content plan"
              data-testid="input-first-task"
            />
            <p className="text-[11px] text-muted-foreground">
              A single starter item — leave blank to start with an empty board.
            </p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="board-description" className="text-xs">
              Notes <span className="text-muted-foreground">(optional)</span>
            </Label>
            <Textarea
              id="board-description"
              rows={3}
              value={form.description}
              onChange={(e) => update("description", e.target.value)}
              placeholder="Short description visible to the client on their Work board."
              data-testid="input-board-description"
            />
          </div>
        </div>

        <DialogFooter>
          <Button
            variant="ghost"
            onClick={() => onOpenChange(false)}
            data-testid="button-cancel-create-board"
          >
            Cancel
          </Button>
          <Button
            onClick={handleCreate}
            className="gap-1.5"
            data-testid="button-confirm-create-board"
          >
            <Briefcase className="size-4" /> Create board
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
