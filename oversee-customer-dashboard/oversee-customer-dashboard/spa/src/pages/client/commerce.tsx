// Client Services storefront — visually-distinct redesign of the catalog.
//
// Layout:
//   ┌──────────────────────────────────────────────────────────────────┐
//   │ Storefront header:                                                │
//   │   left  ─ "Services" + count + subtitle                           │
//   │   centre ─ large search                                            │
//   │   right ─ sort + cart CTA                                         │
//   ├──────────────────────────────────────────────────────────────────┤
//   │ Active-filter chip row (only when filters narrow results)         │
//   ├──────────────────────────────────────────────────────────────────┤
//   │ ┌─ sidebar ─┐ ┌─ product grid (3-4 cols, full-width) ─────────┐  │
//   │ │ category  │ │ ServiceCard ServiceCard ServiceCard           │  │
//   │ │ type      │ │ …                                             │  │
//   │ │ industry  │ │                                                │  │
//   │ └───────────┘ └────────────────────────────────────────────────┘  │
//   └──────────────────────────────────────────────────────────────────┘
//
// All shared visual primitives live in `@/components/commerce-shared`.
// Zero inline styles.

import { useMemo, useState } from "react";
import {
  Check,
  Minus,
  Plus,
  Search,
  ShieldCheck,
  ShoppingCart,
  SlidersHorizontal,
  Trash2,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  useDemoStore,
  type CartLine,
  type WooProduct,
} from "@/lib/demo-store";
import { getProductImage } from "@/lib/commerce";
import { useToast } from "@/hooks/use-toast";
import { EmptyState } from "@/components/shared";
import {
  FilterChipGroup,
  ServiceCard,
  ServiceVisual,
} from "@/components/commerce-shared";

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

type SortOpt = "popular" | "price-asc" | "price-desc" | "name";

function priceLabel(p: WooProduct): { main: string; sub?: string } {
  if (p.type === "simple") {
    return { main: `$${(p.price ?? 0).toLocaleString()}`, sub: "one-time" };
  }
  if (p.type === "subscription") {
    return { main: `$${(p.price ?? 0).toLocaleString()}`, sub: "/ month" };
  }
  const lo = p.priceMin ?? 0;
  const hi = p.priceMax ?? lo;
  if (p.type === "variable") {
    return {
      main: lo === hi ? `$${lo.toLocaleString()}` : `From $${lo.toLocaleString()}`,
      sub:
        lo === hi
          ? "one-time"
          : `up to $${hi.toLocaleString()} · one-time`,
    };
  }
  // variable-subscription
  const term = p.termMonths ? ` for ${p.termMonths} months` : "";
  return { main: `From $${lo.toLocaleString()}`, sub: `/ month${term}` };
}

function productKind(t: WooProduct["type"]): Parameters<typeof ServiceCard>[0]["kind"] {
  if (t === "simple") return "one-time";
  if (t === "subscription") return "subscription";
  if (t === "variable") return "configurable";
  return "configurable-subscription";
}

// ─────────────────────────────────────────────────────────────────────────────
// CLIENT SHOP — storefront
// ─────────────────────────────────────────────────────────────────────────────

export function ClientShop() {
  const { products, cart } = useDemoStore();
  const [cartOpen, setCartOpen] = useState(false);
  const [detail, setDetail] = useState<WooProduct | null>(null);
  const [query, setQuery] = useState("");
  const [category, setCategory] = useState<string>("all");
  const [productType, setProductType] = useState<string>("all");
  const [industry, setIndustry] = useState<string>("all");
  const [sort, setSort] = useState<SortOpt>("popular");
  const [showFilters, setShowFilters] = useState(false);

  const categoryOptions = useMemo(() => {
    const counts = new Map<string, number>();
    products.forEach((p) =>
      counts.set(p.category, (counts.get(p.category) ?? 0) + 1),
    );
    return [
      { value: "all", label: "All", count: products.length },
      ...Array.from(counts.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([value, count]) => ({ value, label: value, count })),
    ];
  }, [products]);

  const typeOptions = useMemo(() => {
    const counts: Record<string, number> = {
      simple: 0,
      subscription: 0,
      variable: 0,
      "variable-subscription": 0,
    };
    products.forEach((p) => {
      counts[p.type] = (counts[p.type] ?? 0) + 1;
    });
    return [
      { value: "all", label: "All types", count: products.length },
      { value: "simple", label: "One-time", count: counts.simple },
      { value: "subscription", label: "Subscription", count: counts.subscription },
      { value: "variable", label: "Configurable", count: counts.variable },
      {
        value: "variable-subscription",
        label: "Configurable subscription",
        count: counts["variable-subscription"],
      },
    ];
  }, [products]);

  const industryOptions = useMemo(() => {
    const counts = new Map<string, number>();
    products.forEach((p) =>
      p.industries.forEach((i) => counts.set(i, (counts.get(i) ?? 0) + 1)),
    );
    return [
      { value: "all", label: "All industries", count: products.length },
      ...Array.from(counts.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([value, count]) => ({ value, label: value, count })),
    ];
  }, [products]);

  const filtered = useMemo(() => {
    let list = products;
    if (category !== "all") list = list.filter((p) => p.category === category);
    if (productType !== "all") list = list.filter((p) => p.type === productType);
    if (industry !== "all")
      list = list.filter((p) => p.industries.includes(industry));
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter(
        (p) =>
          p.name.toLowerCase().includes(q) ||
          p.shortDescription.toLowerCase().includes(q) ||
          p.category.toLowerCase().includes(q),
      );
    }
    const lo = (p: WooProduct) => p.priceMin ?? p.price ?? 0;
    const hi = (p: WooProduct) => p.priceMax ?? p.price ?? 0;
    if (sort === "price-asc") list = [...list].sort((a, b) => lo(a) - lo(b));
    if (sort === "price-desc") list = [...list].sort((a, b) => hi(b) - hi(a));
    if (sort === "name")
      list = [...list].sort((a, b) => a.name.localeCompare(b.name));
    if (sort === "popular") {
      list = [...list].sort((a, b) => (b.badge ? 1 : 0) - (a.badge ? 1 : 0));
    }
    return list;
  }, [products, category, productType, industry, query, sort]);

  const cartCount = cart.reduce((acc, l) => acc + l.qty, 0);
  const filtersActive =
    category !== "all" ||
    productType !== "all" ||
    industry !== "all" ||
    query.trim().length > 0;

  const clearAll = () => {
    setQuery("");
    setCategory("all");
    setProductType("all");
    setIndustry("all");
  };

  return (
    <div className="space-y-5" data-testid="services-page">
      {/* Storefront header — full-width, three-column on desktop */}
      <header
        className="rounded-2xl border border-border bg-card p-5 shadow-sm md:p-6"
        data-testid="services-header"
      >
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">
          <div className="min-w-0 lg:max-w-xs">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
              Services
            </p>
            <h1 className="mt-1 text-xl font-semibold tracking-tight text-foreground">
              Browse the Oversee catalog
            </h1>
            <p
              className="mt-1 text-xs leading-relaxed text-muted-foreground"
              data-testid="result-count"
            >
              {filtered.length} of {products.length} services
              {filtersActive ? " match your filters" : " available"}
            </p>
          </div>

          <div className="flex-1">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search services, e.g. branding, SEO, content…"
                className="h-11 pl-10 pr-10 text-sm"
                data-testid="input-shop-search"
              />
              {query && (
                <button
                  type="button"
                  onClick={() => setQuery("")}
                  className="absolute right-2 top-1/2 grid size-7 -translate-y-1/2 place-items-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                  aria-label="Clear search"
                  data-testid="button-clear-search"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Select value={sort} onValueChange={(v) => setSort(v as SortOpt)}>
              <SelectTrigger
                className="h-10 w-[170px] text-xs"
                data-testid="sort-select"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="popular">Sort: Popular</SelectItem>
                <SelectItem value="price-asc">Price: Low → High</SelectItem>
                <SelectItem value="price-desc">Price: High → Low</SelectItem>
                <SelectItem value="name">A → Z</SelectItem>
              </SelectContent>
            </Select>
            <Button
              variant="outline"
              className="h-10 gap-1.5 md:hidden"
              onClick={() => setShowFilters((v) => !v)}
              data-testid="button-toggle-filters"
            >
              <SlidersHorizontal className="size-4" /> Filters
            </Button>
            <Button
              onClick={() => setCartOpen(true)}
              className="h-10 gap-1.5 bg-orange-600 px-4 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
              data-testid="button-open-cart"
            >
              <ShoppingCart className="size-4" /> Cart
              {cartCount > 0 && (
                <span
                  className="grid size-5 place-items-center rounded-full bg-white/20 text-[10px] font-bold tabular-nums"
                  data-testid="cart-badge-count"
                >
                  {cartCount}
                </span>
              )}
            </Button>
          </div>
        </div>

        {/* Active filter chip row */}
        {filtersActive && (
          <div
            className="mt-4 flex flex-wrap items-center gap-1.5 border-t border-border/60 pt-3"
            data-testid="active-filters"
          >
            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
              Active:
            </span>
            {query && (
              <ActiveChip onClear={() => setQuery("")} testId="chip-query">
                Search: “{query}”
              </ActiveChip>
            )}
            {category !== "all" && (
              <ActiveChip
                onClear={() => setCategory("all")}
                testId="chip-category"
              >
                {category}
              </ActiveChip>
            )}
            {productType !== "all" && (
              <ActiveChip
                onClear={() => setProductType("all")}
                testId="chip-type"
              >
                {typeOptions.find((t) => t.value === productType)?.label}
              </ActiveChip>
            )}
            {industry !== "all" && (
              <ActiveChip
                onClear={() => setIndustry("all")}
                testId="chip-industry"
              >
                {industry}
              </ActiveChip>
            )}
            <Button
              variant="ghost"
              size="sm"
              onClick={clearAll}
              className="ml-auto h-7 px-2 text-[11px]"
              data-testid="button-clear-all"
            >
              Clear all
            </Button>
          </div>
        )}
      </header>

      {/* Body: sidebar + grid */}
      <div className="grid gap-5 md:grid-cols-[240px_1fr] lg:gap-6">
        {/* Sidebar */}
        <aside
          className={
            showFilters
              ? "block space-y-5"
              : "hidden space-y-5 md:block"
          }
          data-testid="services-sidebar"
        >
          <div className="rounded-xl border border-border bg-card p-4">
            <div className="space-y-5">
              <FilterChipGroup
                label="Category"
                value={category}
                options={categoryOptions}
                onChange={setCategory}
                testId="filter-category"
                variant="stack"
              />
              <FilterChipGroup
                label="Type"
                value={productType}
                options={typeOptions}
                onChange={setProductType}
                testId="filter-type"
                variant="stack"
              />
              <FilterChipGroup
                label="Industry"
                value={industry}
                options={industryOptions}
                onChange={setIndustry}
                testId="filter-industry"
                variant="stack"
              />
            </div>
          </div>

          <div className="rounded-xl border border-dashed border-border bg-card/40 p-3 text-[11px] leading-relaxed text-muted-foreground">
            <p className="font-semibold text-foreground">Source: WooCommerce</p>
            <p className="mt-1">
              Catalog mirrors{" "}
              <code className="rounded bg-muted px-1 font-mono text-[10px]">
                /wp-json/wc/store/v1/products
              </code>{" "}
              on overseeagency.com.
            </p>
          </div>
        </aside>

        {/* Grid */}
        <div data-testid="services-grid-container">
          {filtered.length === 0 ? (
            <EmptyState
              icon={Search}
              title="No services match these filters."
              description="Try clearing the search or pick a different category."
              cta={{
                label: "Clear filters",
                onClick: clearAll,
                testId: "button-clear-filters",
              }}
              testId="services-empty-state"
            />
          ) : (
            <div
              className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
              data-testid="services-grid"
            >
              {filtered.map((p) => {
                const { main, sub } = priceLabel(p);
                const variantCount = p.variations?.length;
                // "included" surfaces the attribute *names* ("Length", "Platform"),
                // not their first option (which is often numeric — "0", "30").
                // Empty/numeric labels create confusion in the storefront card.
                const included = p.attributes
                  ?.map((a) => a.name)
                  .filter((s) => /[a-zA-Z]/.test(s))
                  .slice(0, 2);
                // Resolve the WooCommerce image once via the shared helper
                // so cards, the configure sheet, and cart line render the
                // same source. No selected variation here — storefront card
                // always reflects the parent product image.
                const img = getProductImage(p);
                return (
                  <ServiceCard
                    key={p.id}
                    id={String(p.id)}
                    name={p.name}
                    category={p.category}
                    shortDescription={p.shortDescription}
                    priceMain={main}
                    priceSub={sub}
                    kind={productKind(p.type)}
                    variantCount={variantCount}
                    imageUrl={img.src}
                    imageAlt={img.alt}
                    badge={p.badge}
                    included={included}
                    onOpen={() => setDetail(p)}
                    testId={`product-card-${p.id}`}
                  />
                );
              })}
            </div>
          )}
        </div>
      </div>

      <CartSheet open={cartOpen} onOpenChange={setCartOpen} />

      <ProductDetailSheet
        product={detail}
        onClose={() => setDetail(null)}
        onAdded={() => {
          setDetail(null);
          setCartOpen(true);
        }}
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Active-filter chip — local helper, single-purpose, no logic
// ─────────────────────────────────────────────────────────────────────────────

function ActiveChip({
  children,
  onClear,
  testId,
}: {
  children: React.ReactNode;
  onClear: () => void;
  testId?: string;
}) {
  return (
    <span
      className="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2.5 py-0.5 text-[11px] text-foreground"
      data-testid={testId}
    >
      {children}
      <button
        type="button"
        onClick={onClear}
        className="ml-0.5 grid size-4 place-items-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
        aria-label="Remove filter"
      >
        <X className="size-2.5" />
      </button>
    </span>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Product detail / configure sheet
// ─────────────────────────────────────────────────────────────────────────────

function ProductDetailSheet({
  product,
  onClose,
  onAdded,
}: {
  product: WooProduct | null;
  onClose: () => void;
  onAdded: () => void;
}) {
  const { addLineToCart } = useDemoStore();
  const { toast } = useToast();
  const [selected, setSelected] = useState<Record<string, string>>({});
  const [qty, setQty] = useState(1);

  // Reset on product change
  useMemo(() => {
    setSelected({});
    setQty(1);
  }, [product?.id]);

  if (!product) return null;
  const p = product;

  const isVariable = p.type === "variable" || p.type === "variable-subscription";
  const requiredAttrs = p.attributes ?? [];
  const allSelected = requiredAttrs.every((a) => selected[a.name]);
  const matchedVar =
    isVariable && allSelected
      ? p.variations?.find((v) =>
          requiredAttrs.every((a) => v.attrs[a.name] === selected[a.name]),
        )
      : undefined;

  const unitPrice = isVariable ? matchedVar?.price ?? p.priceMin ?? 0 : p.price ?? 0;
  const totalPreview = unitPrice * qty;

  const handleAdd = () => {
    if (isVariable && (!matchedVar || !allSelected)) return;
    addLineToCart({
      productId: p.id,
      variationId: matchedVar?.id,
      selectedAttrs: isVariable ? selected : undefined,
      unitPrice,
      qty,
      period: p.period,
      termMonths: p.termMonths,
    });
    toast({ title: "Added to cart", description: p.name });
    onAdded();
  };

  return (
    <Sheet open={!!product} onOpenChange={(v) => !v && onClose()}>
      <SheetContent className="flex w-full max-w-2xl flex-col overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-start gap-2 pr-6">
            <span className="leading-snug">{p.name}</span>
          </SheetTitle>
        </SheetHeader>

        <div className="mt-4 space-y-5">
          <div className="overflow-hidden rounded-xl border border-border">
            {(() => {
              // When the user has chosen a variation that carries its own
              // image (per WooCommerce variation imagery), prefer that.
              // Otherwise fall back to the parent product image.
              const img = getProductImage(p, matchedVar);
              return (
                <ServiceVisual
                  src={img.src}
                  alt={img.alt}
                  seed={`${p.category}-${p.id}`}
                  initials={p.name}
                  ratio="16/9"
                  testId="detail-visual"
                />
              );
            })()}
          </div>

          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
              {p.category}
            </Badge>
            <Badge variant="outline" className="text-[10px] capitalize">
              {p.type === "variable-subscription" ? "configurable subscription" : p.type}
            </Badge>
            {p.termMonths && (
              <Badge variant="outline" className="text-[10px]">
                {p.termMonths}-month plan
              </Badge>
            )}
          </div>

          <p className="text-sm leading-relaxed text-muted-foreground">
            {p.shortDescription}
          </p>

          {/* Attribute selectors */}
          {isVariable && requiredAttrs.length > 0 && (
            <div className="space-y-4">
              {requiredAttrs.map((a) => (
                <div key={a.name}>
                  <label className="mb-1.5 block text-xs font-semibold">
                    {a.name}
                    <span className="ml-1.5 text-[10px] font-normal text-muted-foreground">
                      ({a.options.length} options)
                    </span>
                  </label>
                  <FilterChipGroup
                    label=""
                    value={selected[a.name] ?? ""}
                    options={a.options.map((o) => ({ value: o, label: o }))}
                    onChange={(v) => setSelected((s) => ({ ...s, [a.name]: v }))}
                    variant="chip"
                    testId={`opt-${a.name.replace(/\s+/g, "-").toLowerCase()}`}
                  />
                </div>
              ))}
              {!allSelected && (
                <p className="rounded-md border border-amber-300/60 bg-amber-50 p-2 text-[11px] text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                  Pick a value for each option above to see your final price.
                </p>
              )}
            </div>
          )}

          {/* Industry tags (informational) */}
          {p.industries.length > 0 && (
            <div>
              <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                Best for
              </p>
              <div className="flex flex-wrap gap-1">
                {p.industries.slice(0, 8).map((i) => (
                  <span
                    key={i}
                    className="rounded-md border border-border bg-card px-2 py-0.5 text-[11px] text-muted-foreground"
                  >
                    {i}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Summary */}
          <div className="rounded-xl border border-border bg-card p-4">
            <div className="flex items-center justify-between text-sm">
              <span className="font-medium">Selection summary</span>
              {isVariable && allSelected && matchedVar && (
                <span className="font-mono text-[10px] text-muted-foreground">
                  var #{matchedVar.id}
                </span>
              )}
            </div>
            <div className="mt-2">
              {isVariable ? (
                allSelected ? (
                  <div className="space-y-1 text-xs">
                    {requiredAttrs.map((a) => (
                      <div key={a.name} className="flex justify-between">
                        <span className="text-muted-foreground">{a.name}</span>
                        <span className="font-medium">{selected[a.name]}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-[11px] text-muted-foreground">
                    Select all required options above.
                  </p>
                )
              ) : (
                <p className="text-[11px] text-muted-foreground">
                  No options to configure for this service.
                </p>
              )}
            </div>

            <div className="mt-3 flex items-center justify-between border-t border-border pt-3">
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="icon"
                  className="size-7"
                  onClick={() => setQty((q) => Math.max(1, q - 1))}
                  data-testid="qty-minus"
                  aria-label="Decrease quantity"
                  title="Decrease quantity"
                >
                  <Minus className="size-3" />
                </Button>
                <span
                  className="w-6 text-center text-sm font-medium tabular-nums"
                  data-testid="qty-value"
                >
                  {qty}
                </span>
                <Button
                  variant="outline"
                  size="icon"
                  className="size-7"
                  onClick={() => setQty((q) => q + 1)}
                  data-testid="qty-plus"
                  aria-label="Increase quantity"
                  title="Increase quantity"
                >
                  <Plus className="size-3" />
                </Button>
              </div>
              <div className="text-right">
                <p
                  className="text-lg font-semibold tabular-nums text-foreground"
                  data-testid="config-price"
                >
                  ${totalPreview.toLocaleString()}
                </p>
                <p className="text-[11px] text-muted-foreground">
                  {p.period === "month"
                    ? p.termMonths
                      ? `per month · ${p.termMonths} months total`
                      : "per month"
                    : "one-time"}
                </p>
              </div>
            </div>
          </div>
        </div>

        <SheetFooter className="mt-5 border-t border-border pt-3">
          <Button
            variant="outline"
            onClick={onClose}
            data-testid="button-detail-close"
          >
            Close
          </Button>
          <Button
            onClick={handleAdd}
            disabled={isVariable && !allSelected}
            className="gap-1.5 bg-orange-600 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
            data-testid="button-detail-add"
          >
            <Plus className="size-3.5" /> Add to cart — ${totalPreview.toLocaleString()}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Cart sheet
// ─────────────────────────────────────────────────────────────────────────────

function CartSheet({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  const { cart, products, removeLineFromCart, clearCart } = useDemoStore();
  const { toast } = useToast();
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [orderConfirm, setOrderConfirm] = useState<string | null>(null);

  const lines = cart
    .map((line) => ({
      line,
      product: products.find((p) => p.id === line.productId),
    }))
    .filter((x): x is { line: CartLine; product: WooProduct } => !!x.product);

  const oneTimeTotal = lines
    .filter((l) => l.line.period !== "month")
    .reduce((acc, l) => acc + l.line.unitPrice * l.line.qty, 0);
  const monthlyTotal = lines
    .filter((l) => l.line.period === "month")
    .reduce((acc, l) => acc + l.line.unitPrice * l.line.qty, 0);
  const dueToday = oneTimeTotal + monthlyTotal;

  return (
    <>
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent className="flex w-full max-w-lg flex-col">
          <SheetHeader>
            <SheetTitle>Your cart</SheetTitle>
          </SheetHeader>
          <div className="mt-4 flex-1 space-y-3 overflow-y-auto">
            {lines.length === 0 ? (
              <EmptyState
                icon={ShoppingCart}
                title="Cart is empty"
                description="Add a service from the catalog to start a checkout."
                compact
                testId="cart-empty-state"
              />
            ) : (
              lines.map(({ line, product }) => {
                // Match the cart line to the variation it was added with, if
                // any, so the line image reflects the user's actual selection
                // (mirrors what they saw in the configure sheet).
                const lineVariation = line.variationId
                  ? product.variations?.find((v) => v.id === line.variationId)
                  : undefined;
                const lineImg = getProductImage(product, lineVariation);
                return (
                <div
                  key={line.id}
                  className="flex items-start gap-3 rounded-xl border border-border bg-card p-3"
                  data-testid={`cart-line-${line.id}`}
                >
                  <div className="size-14 shrink-0 overflow-hidden rounded-md border border-border">
                    <ServiceVisual
                      src={lineImg.src}
                      alt={lineImg.alt}
                      seed={`${product.category}-${product.id}`}
                      initials={product.name}
                      ratio="1/1"
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{product.name}</p>
                    <p className="text-[11px] text-muted-foreground">
                      {product.category}
                    </p>
                    {line.selectedAttrs && (
                      <ul className="mt-1 space-y-0.5 text-[11px] text-muted-foreground">
                        {Object.entries(line.selectedAttrs).map(([k, v]) => (
                          <li key={k}>
                            <span className="font-medium text-foreground">
                              {k}:
                            </span>{" "}
                            {v}
                          </li>
                        ))}
                      </ul>
                    )}
                    <p className="mt-1.5 text-[11px] tabular-nums">
                      ${line.unitPrice.toLocaleString()} × {line.qty}
                      {line.period === "month" ? " / month" : ""}
                      {line.termMonths ? ` · ${line.termMonths} months` : ""}
                    </p>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0"
                    onClick={() => removeLineFromCart(line.id)}
                    data-testid={`cart-remove-${line.id}`}
                    aria-label="Remove from cart"
                    title="Remove from cart"
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                </div>
                );
              })
            )}
          </div>
          {lines.length > 0 && (
            <SheetFooter className="border-t border-border pt-3">
              <div className="flex w-full flex-col gap-2">
                <div className="space-y-1 text-xs">
                  {oneTimeTotal > 0 && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">
                        One-time charges
                      </span>
                      <span className="font-medium tabular-nums">
                        ${oneTimeTotal.toLocaleString()}
                      </span>
                    </div>
                  )}
                  {monthlyTotal > 0 && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">
                        Recurring (per month)
                      </span>
                      <span className="font-medium tabular-nums">
                        ${monthlyTotal.toLocaleString()}
                      </span>
                    </div>
                  )}
                </div>
                <div className="flex items-center justify-between border-t border-border pt-2 text-sm">
                  <span className="font-semibold">Due today</span>
                  <span className="font-semibold tabular-nums">
                    ${dueToday.toLocaleString()}
                  </span>
                </div>
                <Button
                  size="lg"
                  onClick={() => setCheckoutOpen(true)}
                  className="h-11 w-full gap-1.5 bg-orange-600 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
                  data-testid="button-checkout"
                >
                  <ShieldCheck className="size-4" /> Continue to WooCommerce
                  checkout
                </Button>
                <p className="text-center text-[11px] text-muted-foreground">
                  Production checkout completes on your Oversee site, secured by
                  WooCommerce.
                </p>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={clearCart}
                  className="text-xs"
                >
                  Clear cart
                </Button>
              </div>
            </SheetFooter>
          )}
        </SheetContent>
      </Sheet>

      {/* Checkout dialog — preserves variant selections + interval */}
      <Dialog open={checkoutOpen} onOpenChange={setCheckoutOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Confirm order</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="rounded-md border border-orange-200 bg-orange-50/60 p-3 text-xs dark:border-orange-900/40 dark:bg-orange-950/30">
              <p className="font-medium text-foreground">
                Handing off to WooCommerce
              </p>
              <p className="mt-1 text-muted-foreground">
                In production this hands off to WooCommerce native checkout for
                payment, tax, and recurring billing setup.
              </p>
            </div>
            <div className="rounded-md border border-border bg-muted/30 p-3 text-xs">
              <p className="text-muted-foreground">Charging card on file:</p>
              <p className="mt-0.5 font-mono text-sm">
                Stripe · Visa ending 4242
              </p>
            </div>
            <div className="space-y-2">
              {lines.map(({ line, product }) => {
                // Resolve the same image the cart line showed so the order
                // summary thumbnail stays consistent with the user's choice.
                const lineVariation = line.variationId
                  ? product.variations?.find((v) => v.id === line.variationId)
                  : undefined;
                const lineImg = getProductImage(product, lineVariation);
                return (
                <div
                  key={line.id}
                  className="flex items-start gap-2.5 rounded-md border border-border p-2.5 text-xs"
                  data-testid={`checkout-line-${line.id}`}
                >
                  <div className="size-10 shrink-0 overflow-hidden rounded border border-border">
                    <ServiceVisual
                      src={lineImg.src}
                      alt={lineImg.alt}
                      seed={`${product.category}-${product.id}`}
                      initials={product.name}
                      ratio="1/1"
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                      <span className="font-medium">{product.name}</span>
                      <span className="font-medium tabular-nums">
                        ${(line.unitPrice * line.qty).toLocaleString()}
                        {line.period === "month" ? "/mo" : ""}
                      </span>
                    </div>
                    {line.selectedAttrs && (
                      <ul className="mt-1 space-y-0.5 text-[10px] text-muted-foreground">
                        {Object.entries(line.selectedAttrs).map(([k, v]) => (
                          <li key={k}>
                            {k}: <span className="text-foreground">{v}</span>
                          </li>
                        ))}
                      </ul>
                    )}
                    {line.termMonths && (
                      <p className="mt-1 text-[10px] text-muted-foreground">
                        Billing term: {line.termMonths} months
                      </p>
                    )}
                  </div>
                </div>
                );
              })}
            </div>
            <div className="space-y-1 border-t border-border pt-2 text-sm">
              {oneTimeTotal > 0 && (
                <div className="flex justify-between">
                  <span>One-time</span>
                  <span className="font-medium tabular-nums">
                    ${oneTimeTotal.toLocaleString()}
                  </span>
                </div>
              )}
              {monthlyTotal > 0 && (
                <div className="flex justify-between">
                  <span>Then per month</span>
                  <span className="font-medium tabular-nums">
                    ${monthlyTotal.toLocaleString()}
                  </span>
                </div>
              )}
              <div className="flex justify-between border-t border-border pt-2 text-base">
                <span className="font-semibold">Due today</span>
                <span className="font-semibold tabular-nums">
                  ${dueToday.toLocaleString()}
                </span>
              </div>
            </div>
            <p className="text-[11px] text-muted-foreground">
              In production, this posts the cart to{" "}
              <code className="rounded bg-muted px-1 py-0.5 font-mono">
                /wp-json/wc/store/v1/checkout
              </code>{" "}
              on your Oversee site; an automation provisions your project
              workspace.
            </p>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setCheckoutOpen(false)}
            >
              Cancel
            </Button>
            <Button
              onClick={() => {
                const num = `#10${Math.floor(450 + Math.random() * 99)}`;
                setOrderConfirm(num);
                setCheckoutOpen(false);
                onOpenChange(false);
                clearCart();
                toast({
                  title: "Order placed",
                  description: `Production: WooCommerce ${num}.`,
                });
              }}
              className="gap-1.5 bg-orange-600 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
              data-testid="button-confirm-order"
            >
              <ShieldCheck className="size-4" /> Pay $
              {dueToday.toLocaleString()} via WooCommerce
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Order confirmation */}
      <Dialog
        open={!!orderConfirm}
        onOpenChange={(v) => !v && setOrderConfirm(null)}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="grid size-7 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                <Check className="size-4" />
              </span>
              Order confirmed
            </DialogTitle>
          </DialogHeader>
          <p className="text-sm">
            Order{" "}
            <span className="font-mono font-semibold">{orderConfirm}</span> is
            now in WooCommerce. Your project workspace will be provisioned and
            you'll get an email when it's ready.
          </p>
          <DialogFooter>
            <Button onClick={() => setOrderConfirm(null)}>Got it</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

// Note: the previous file also exported ClientSubscriptions and ClientBilling.
// Those are no longer routed (the Account page covers subscriptions, billing,
// invoices, payments, addresses, and profile). They have been removed to
// eliminate duplicate UI. `ClientShop` remains the only export consumed by
// client-dashboard.tsx routing.
