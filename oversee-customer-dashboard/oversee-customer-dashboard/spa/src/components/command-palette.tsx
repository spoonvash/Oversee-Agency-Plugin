import { useEffect, useState } from "react";
import { useLocation } from "wouter";
import {
  Home,
  LayoutGrid,
  MessageSquare,
  Receipt,
  Settings,
  Users,
  ArrowRightLeft,
  Sun,
  Moon,
  ListChecks,
  Sparkles,
  Briefcase,
} from "lucide-react";
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command";
import { setDark, useDark } from "@/components/theme-toggle";

type Cmd = {
  id: string;
  label: string;
  group: string;
  icon: any;
  href?: string;
  action?: () => void;
};

export function CommandPalette({
  open,
  onOpenChange,
  role,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  role: "client" | "admin";
}) {
  const [, navigate] = useLocation();
  const dark = useDark();

  const clientCmds: Cmd[] = [
    { id: "c-home", label: "Home", group: "Client · Workspace", icon: Home, href: "/client" },
    { id: "c-onboarding", label: "Onboarding", group: "Client · Workspace", icon: Sparkles, href: "/client/onboarding" },
    { id: "c-work", label: "My Work", group: "Client · Workspace", icon: ListChecks, href: "/client/work" },
    { id: "c-files", label: "Files", group: "Client · Workspace", icon: MessageSquare, href: "/client/files" },
    { id: "c-services", label: "Services", group: "Client · Workspace", icon: Briefcase, href: "/client/services" },
    { id: "c-account", label: "Account", group: "Client · Workspace", icon: Receipt, href: "/client/account" },
  ];
  const adminCmds: Cmd[] = [
    { id: "a-today", label: "Today", group: "Admin · Workspace", icon: Home, href: "/admin" },
    { id: "a-boards", label: "Boards", group: "Admin · Workspace", icon: LayoutGrid, href: "/admin/boards" },
    { id: "a-clients", label: "Clients", group: "Admin · Workspace", icon: Users, href: "/admin/clients" },
    { id: "a-settings", label: "Settings", group: "Admin · System", icon: Settings, href: "/admin/settings" },
  ];

  const navCmds = role === "client" ? clientCmds : adminCmds;

  const actions: Cmd[] = [
    {
      id: "act-toggle-theme",
      label: dark ? "Switch to light mode" : "Switch to dark mode",
      group: "Quick actions",
      icon: dark ? Sun : Moon,
      action: () => setDark(!dark),
    },
    {
      id: "act-switch-role",
      label: role === "client" ? "Switch to admin preview" : "Switch to client preview",
      group: "Quick actions",
      icon: ArrowRightLeft,
      href: role === "client" ? "/admin" : "/client",
    },
  ];

  const grouped = [...navCmds, ...actions].reduce<Record<string, Cmd[]>>((acc, c) => {
    acc[c.group] = acc[c.group] || [];
    acc[c.group].push(c);
    return acc;
  }, {});

  const run = (c: Cmd) => {
    onOpenChange(false);
    if (c.action) c.action();
    if (c.href) setTimeout(() => navigate(c.href!), 50);
  };

  return (
    <CommandDialog open={open} onOpenChange={onOpenChange}>
      <CommandInput placeholder="Type a command or search..." data-testid="input-command" />
      <CommandList>
        <CommandEmpty>No results found.</CommandEmpty>
        {Object.entries(grouped).map(([groupName, cmds], idx) => (
          <div key={groupName}>
            {idx > 0 && <CommandSeparator />}
            <CommandGroup heading={groupName}>
              {cmds.map((c) => (
                <CommandItem
                  key={c.id}
                  onSelect={() => run(c)}
                  data-testid={`command-${c.id}`}
                >
                  <c.icon className="mr-2 size-4 text-muted-foreground" />
                  {c.label}
                </CommandItem>
              ))}
            </CommandGroup>
          </div>
        ))}
      </CommandList>
    </CommandDialog>
  );
}

export function useCommandPalette() {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen((v) => !v);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);
  return { open, setOpen };
}
