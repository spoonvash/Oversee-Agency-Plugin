// Admin Commerce handoff — commerce is managed in WooCommerce, not here.
// Provides a single CTA to open the WooCommerce admin and quick links for context.
import { ExternalLink, Settings2, ShoppingBag } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useDemoStore } from "@/lib/demo-store";
import {
  PageHeader,
  PriorityBanner,
  SectionCard,
  DataListRow,
  SettingsPanel,
} from "@/components/shared";

const QUICK_LINKS = [
  { label: "Products", path: "/wp-admin/edit.php?post_type=product" },
  { label: "Orders", path: "/wp-admin/edit.php?post_type=shop_order" },
  { label: "Subscriptions", path: "/wp-admin/edit.php?post_type=shop_subscription" },
  { label: "Coupons", path: "/wp-admin/edit.php?post_type=shop_coupon" },
  { label: "Reports", path: "/wp-admin/admin.php?page=wc-reports" },
  { label: "Settings", path: "/wp-admin/admin.php?page=wc-settings" },
];

export default function AdminCommerceHandoff() {
  const { products, integrations } = useDemoStore();
  const woo = integrations.find((i) => i.id === "woocommerce");

  return (
    <div className="space-y-5">
      <PageHeader
        eyebrow="Commerce"
        title="Commerce is managed in WooCommerce"
        subtitle="Products, orders, subscriptions, payments, and tax all live in your Oversee WooCommerce install. We don't mirror that here so there's a single source of truth."
      />

      <PriorityBanner
        icon={ShoppingBag}
        eyebrow={woo?.status === "connected" ? "WooCommerce connected" : "WooCommerce"}
        title="Open the WooCommerce admin to manage commerce"
        subtitle={`${woo?.detail ?? "REST API connected"} · ${products.length} products mirrored for client browsing.`}
        cta={{
          label: "Open WooCommerce admin",
          onClick: () =>
            alert("In production: opens https://overseeagency.com/wp-admin/admin.php?page=wc-admin"),
          testId: "button-open-woo-admin",
        }}
        tone="primary"
        testId="commerce-handoff-banner"
      />

      <div className="flex flex-wrap items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          className="h-9 gap-2"
          onClick={() => (location.hash = "#/admin/settings")}
          data-testid="button-commerce-settings"
        >
          <Settings2 className="size-4" /> Integration settings
        </Button>
        <Button
          variant="ghost"
          size="sm"
          className="h-9 gap-2"
          onClick={() => alert("In production: opens https://overseeagency.com/wp-admin/admin.php?page=wc-admin")}
          data-testid="button-open-woo-admin-secondary"
        >
          <ExternalLink className="size-4" /> Direct link
        </Button>
      </div>

      <SectionCard
        title="Jump to WooCommerce"
        hint="All commerce admin happens on the WooCommerce side. These links open in your existing wp-admin session."
        testId="commerce-quick-links"
      >
        <div className="divide-y divide-border/60">
          {QUICK_LINKS.map((q) => (
            <DataListRow
              key={q.label}
              icon={ShoppingBag}
              title={q.label}
              meta={q.path}
              onOpen={() =>
                alert(`In production: opens https://overseeagency.com${q.path}`)
              }
              testId={`woo-link-${q.label.toLowerCase()}`}
            />
          ))}
        </div>
      </SectionCard>

      <SettingsPanel
        title="Why is commerce not in this dashboard?"
        hint="WooCommerce already handles payments, taxes, refunds, and renewals. Duplicating that surface here leads to drift. The Oversee dashboard focuses on operations — boards, updates, clients — and links out to WooCommerce when you need to manage commerce."
        testId="commerce-rationale"
      >
        <p className="text-xs text-muted-foreground">
          For per-board commerce context (services purchased, current cadence, next renewal), open the
          client&rsquo;s record from the Clients page — those panels read live from WooCommerce.
        </p>
      </SettingsPanel>
    </div>
  );
}
