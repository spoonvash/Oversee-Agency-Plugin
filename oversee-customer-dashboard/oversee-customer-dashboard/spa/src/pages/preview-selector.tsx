import { Link } from "wouter";
import {
  ArrowRight,
  Building2,
  Mail,
  MessageSquare,
  ShoppingBag,
  Users,
  Workflow,
} from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { OverseeLogo } from "@/components/oversee-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export default function PreviewSelector() {
  return (
    <div className="min-h-screen bg-background">
      <header className="border-b">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
          <OverseeLogo />
          <div className="flex items-center gap-3">
            <Badge
              variant="outline"
              className="border-border bg-background text-[11px] font-medium uppercase tracking-[0.16em] text-muted-foreground"
            >
              Preview · v5
            </Badge>
            <ThemeToggle />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-6 py-14 md:py-20">
        <section className="mb-12 max-w-3xl">
          <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary">
            Oversee · Client Portal
          </p>
          <h1 className="mt-3 text-3xl font-semibold tracking-[-0.03em] md:text-5xl">
            Pick a role to explore
          </h1>
          <p className="mt-5 max-w-2xl text-base leading-7 text-muted-foreground">
            A WordPress-native concept: a child theme paired with a companion plugin
            that ships at <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[13px]">/dashboard/</code>.
            WooCommerce drives commerce, a native project workspace handles delivery,
            and HighLevel surfaces are wrapped via SSO magic-link iframes — Assembly /
            Copilot styling throughout.
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            <Link href="/login" data-testid="link-preview-login">
              <span className="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/10">
                <Mail className="size-3.5" /> Preview the passwordless login
              </span>
            </Link>
            <span className="inline-flex items-center gap-1.5 rounded-md border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground">
              <span className="size-1 rounded-full bg-primary" />
              SELL · DELIVER · CONNECT
            </span>
          </div>
        </section>

        <div className="grid gap-6 md:grid-cols-2">
          <RoleCard
            href="/client"
            badge="/#/client"
            icon={Users}
            title="Client portal"
            description="What logged-in customers see at /dashboard/. A simplified workspace mirroring Monday.com — boards with items, file approvals, plus a WooCommerce-native services storefront and billing. Notifications live in the top-right bell only."
            bullets={[
              "Home · Onboarding · My Work",
              "Files · Services · Account",
              "Top-right bell = single notification center",
              "Boards, items & in-context conversations",
            ]}
          />
          <RoleCard
            href="/admin"
            badge="/#/admin"
            icon={Building2}
            title="Admin console"
            description="The agency operator's command center. Today cockpit, Monday-style boards across clients, file approvals, client CRM, and settings (HighLevel Workflows handle automations). Notifications live in the top-right bell only."
            bullets={[
              "Today · Boards · Clients · Settings",
              "Top-right bell = single notification center",
              "File approvals inside boards",
              "HighLevel Workflows handle automations",
            ]}
          />
        </div>

        <section className="mt-14 grid gap-4 rounded-lg border bg-card p-6 md:grid-cols-3">
          <Note
            icon={<Workflow className="size-4 text-primary" />}
            title="WordPress + WooCommerce"
            body="Child theme + companion plugin. Production runs at /dashboard/. WooCommerce is the source of truth for products, orders, and subscriptions; the dashboard restyles native flows in clean Oversee app UI."
          />
          <Note
            icon={<ShoppingBag className="size-4 text-primary" />}
            title="Three jobs: Sell · Deliver · Connect"
            body="SELL = WooCommerce catalog/cart/checkout/subscriptions/billing. DELIVER = native project workspace, tasks, forms, contracts, files, approvals. CONNECT = HighLevel iframes for conversations, calendar, reports, reputation, documents."
          />
          <Note
            icon={<MessageSquare className="size-4 text-primary" />}
            title="HighLevel via SSO iframes"
            body="Messages, Schedule a Call, Performance Reports, Documents, Reviews are HighLevel surfaces wrapped with magic-link SSO and Oversee chrome — never raw."
          />
        </section>
      </main>
    </div>
  );
}

function RoleCard({
  href,
  badge,
  icon: Icon,
  title,
  description,
  bullets,
}: {
  href: string;
  badge: string;
  icon: any;
  title: string;
  description: string;
  bullets: string[];
}) {
  return (
    <Link href={href} data-testid={`card-pick-${href.replace("/", "")}`}>
      <Card className="group h-full cursor-pointer transition hover:border-primary">
        <CardContent className="flex h-full flex-col gap-6 p-7">
          <div className="flex items-start justify-between gap-3">
            <div className="grid size-12 place-items-center rounded-lg border bg-card">
              <Icon className="size-6" />
            </div>
            <Badge
              variant="outline"
              className="font-mono text-[11px] uppercase tracking-wide text-muted-foreground"
            >
              {badge}
            </Badge>
          </div>
          <div>
            <h2 className="text-xl font-semibold tracking-[-0.02em]">{title}</h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">{description}</p>
          </div>
          <ul className="mt-auto space-y-1.5 text-xs text-muted-foreground">
            {bullets.map((b) => (
              <li key={b} className="flex items-start gap-2">
                <span className="mt-1.5 size-1 shrink-0 rounded-full bg-primary" />
                <span>{b}</span>
              </li>
            ))}
          </ul>
          <div className="flex items-center justify-between border-t pt-4 text-sm font-medium text-foreground">
            Open preview
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}

function Note({ icon, title, body }: { icon: React.ReactNode; title: string; body: string }) {
  return (
    <div>
      <div className="mb-2 flex items-center gap-2">
        {icon}
        <p className="text-sm font-medium">{title}</p>
      </div>
      <p className="text-xs leading-5 text-muted-foreground">{body}</p>
    </div>
  );
}
