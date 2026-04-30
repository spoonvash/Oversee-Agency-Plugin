import {
  FolderOpen,
  Home,
  KanbanSquare,
  ListChecks,
  Rocket,
  Settings,
  ShoppingBag,
  Sun,
  UserRound,
  Users,
} from "lucide-react";
import type { RoleNavGroup } from "@/components/role-sidebar";

// Sidebar IA — simple, no notification duplication.
// The top-right bell is the SINGLE notification surface for both roles.
// Project conversations live inside Workflow item details + on Home / Today
// (Recent project messages module). No "Updates" or "Inbox" sidebar item.
//
// Client: Home · Onboarding · My Work · Files · Services · Account
// Admin:  Today · Boards · Clients · Settings
export const clientGroups: RoleNavGroup[] = [
  {
    label: "Workspace",
    items: [
      { id: "home", label: "Home", href: "/client", icon: Home },
      { id: "onboarding", label: "Onboarding", href: "/client/onboarding", icon: Rocket },
      { id: "work", label: "My Work", href: "/client/work", icon: ListChecks },
      { id: "files", label: "Files", href: "/client/files", icon: FolderOpen },
    ],
  },
  {
    label: "Services & account",
    items: [
      { id: "services", label: "Services", href: "/client/services", icon: ShoppingBag },
      { id: "account", label: "Account", href: "/client/account", icon: UserRound },
    ],
  },
];

export const adminGroups: RoleNavGroup[] = [
  {
    label: "Workspace",
    items: [
      { id: "today", label: "Today", href: "/admin", icon: Sun },
      { id: "boards", label: "Boards", href: "/admin/boards", icon: KanbanSquare },
      { id: "clients", label: "Clients", href: "/admin/clients", icon: Users },
      { id: "settings", label: "Settings", href: "/admin/settings", icon: Settings },
    ],
  },
];
