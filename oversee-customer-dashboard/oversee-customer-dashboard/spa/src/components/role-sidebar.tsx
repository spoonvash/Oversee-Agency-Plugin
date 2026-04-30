import type { LucideIcon } from "lucide-react";
import { ArrowLeftRight, Sparkles } from "lucide-react";
import { Link, useLocation } from "wouter";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import { Badge } from "@/components/ui/badge";
import { OverseeLogo } from "@/components/oversee-logo";

export type RoleNavItem = {
  id: string;
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: string | number;
};

export type RoleNavGroup = {
  label: string;
  items: RoleNavItem[];
};

type Props = {
  role: "client" | "admin";
  groups: RoleNavGroup[];
};

export function RoleSidebar({ role, groups }: Props) {
  const [location] = useLocation();
  const switchHref = role === "client" ? "/admin" : "/client";
  const switchLabel = role === "client" ? "View admin" : "View client";

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="px-3 py-4">
        <Link href="/" data-testid="link-home-logo">
          <OverseeLogo
            tone="sidebar"
            subtitle={role === "admin" ? "Admin Console" : "Client Portal"}
          />
        </Link>
      </SidebarHeader>
      <SidebarContent className="px-2">
        {groups.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel className="text-[10px] font-semibold tracking-[0.18em] uppercase text-sidebar-foreground/50">
              {group.label}
            </SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  const isActive =
                    location === item.href ||
                    (item.href !== `/${role}` && location.startsWith(item.href));
                  return (
                    <SidebarMenuItem key={item.id}>
                      <SidebarMenuButton
                        asChild
                        isActive={isActive}
                        tooltip={item.label}
                      >
                        <Link
                          href={item.href}
                          data-testid={`link-${role}-nav-${item.id}`}
                        >
                          <item.icon />
                          <span>{item.label}</span>
                          {item.badge !== undefined && item.badge !== 0 && (
                            <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                              {item.badge}
                            </span>
                          )}
                        </Link>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>
      <SidebarFooter className="px-2 py-3">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton asChild tooltip={switchLabel}>
              <Link href={switchHref} data-testid={`link-switch-${role}`}>
                <ArrowLeftRight />
                <span>{switchLabel}</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
        <div className="mt-2 hidden rounded-md border border-sidebar-border bg-sidebar-accent/40 px-3 py-2 text-xs leading-snug text-sidebar-foreground/70 group-data-[state=expanded]/sidebar-wrapper:block">
          <div className="mb-1 flex items-center gap-1.5 font-medium text-sidebar-foreground">
            <Sparkles className="size-3 text-primary" />
            <span>Preview mode</span>
            <Badge variant="outline" className="ml-auto border-sidebar-border text-[10px] uppercase tracking-wider">
              {role}
            </Badge>
          </div>
          WordPress + Woo + HighLevel demo data only.
        </div>
      </SidebarFooter>
    </Sidebar>
  );
}
