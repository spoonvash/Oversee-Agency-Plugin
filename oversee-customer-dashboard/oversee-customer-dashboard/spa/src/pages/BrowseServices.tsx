import { useEffect, useMemo, useState } from "react";
import { Search } from "lucide-react";
import { apiGet, ApiError } from "@/lib/api";
import { ProductCard, CommerceProduct } from "@/components/ProductCard";
import { ActionNotice, EmptyState, SetupRequired, Skeleton } from "@/components/ActionNotice";

type CommerceResponse = {
    available: boolean;
    reason?: string;
    products: CommerceProduct[];
    cart_url: string;
    checkout_url: string;
};

export function BrowseServices() {
    const [data, setData] = useState<CommerceResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<{ status?: number; message: string } | null>(null);
    const [search, setSearch] = useState("");
    const [activeCategory, setActiveCategory] = useState<string | "">("");

    useEffect(() => {
        let active = true;
        setLoading(true);
        apiGet<CommerceResponse>(`commerce/products?per_page=100`, { namespace: "oversee/v1" })
            .then((res) => {
                if (!active) return;
                setData(res);
                setError(null);
            })
            .catch((err: unknown) => {
                if (!active) return;
                const status = err instanceof ApiError ? err.status : undefined;
                setError({ status, message: err instanceof Error ? err.message : "Could not load products" });
            })
            .finally(() => {
                if (active) setLoading(false);
            });
        return () => {
            active = false;
        };
    }, []);

    const allProducts = data?.products ?? [];
    const categories = useMemo(() => {
        const map = new Map<string, string>();
        for (const p of allProducts) {
            for (const c of p.categories) {
                if (!map.has(c.slug)) map.set(c.slug, c.name);
            }
        }
        return Array.from(map.entries()).map(([slug, name]) => ({ slug, name })).sort((a, b) => a.name.localeCompare(b.name));
    }, [allProducts]);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return allProducts.filter((p) => {
            if (activeCategory && !p.categories.some((c) => c.slug === activeCategory)) return false;
            if (q && !p.name.toLowerCase().includes(q) && !(p.short_description ?? "").toLowerCase().includes(q)) return false;
            return true;
        });
    }, [allProducts, search, activeCategory]);

    return (
        <div className="space-y-6 max-w-[1200px]">
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">Browse Services</h1>
                <p className="text-sm text-zinc-500">
                    Real-time WooCommerce catalog. Variants and subscription pricing are pulled from the live store.
                </p>
            </header>

            {/* Search + filter */}
            <div className="oversee-card p-4 flex flex-col md:flex-row gap-3 md:items-center">
                <div className="relative flex-1">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search services…"
                        aria-label="Search services"
                        className="w-full pl-9 pr-3 py-2 text-sm rounded-md border bg-white dark:bg-zinc-900"
                        style={{ borderColor: "var(--oversee-border)" }}
                    />
                </div>
                <div className="flex gap-2 flex-wrap items-center">
                    <button
                        type="button"
                        onClick={() => setActiveCategory("")}
                        className={`text-xs px-3 py-1 rounded-full border ${activeCategory === "" ? "bg-zinc-900 text-white border-zinc-900" : "bg-transparent"}`}
                        style={activeCategory === "" ? undefined : { borderColor: "var(--oversee-border)" }}
                    >
                        All
                    </button>
                    {categories.map((c) => {
                        const isActive = activeCategory === c.slug;
                        return (
                            <button
                                key={c.slug}
                                type="button"
                                onClick={() => setActiveCategory(c.slug)}
                                className={`text-xs px-3 py-1 rounded-full border ${isActive ? "bg-zinc-900 text-white border-zinc-900" : "bg-transparent"}`}
                                style={isActive ? undefined : { borderColor: "var(--oversee-border)" }}
                            >
                                {c.name}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Loading state */}
            {loading && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {Array.from({ length: 8 }).map((_, i) => (
                        <div key={i} className="oversee-card p-0 overflow-hidden">
                            <div className="aspect-[4/3] animate-pulse" style={{ background: "rgba(100,116,139,0.12)" }} />
                            <div className="p-4 space-y-2">
                                <Skeleton className="h-3 w-3/4" />
                                <Skeleton className="h-3 w-1/2" />
                                <Skeleton className="h-8 w-full" />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Error / unavailable states */}
            {!loading && error && error.status === 404 && (
                <SetupRequired title="Commerce endpoint not available yet" integration="WooCommerce">
                    The dashboard is talking to an older plugin build. Update the Oversee Dashboard plugin to the latest version
                    so the <code>oversee/v1/commerce/products</code> endpoint is registered.
                </SetupRequired>
            )}
            {!loading && error && error.status !== 404 && (
                <ActionNotice tone="warning" title="Could not load services">
                    {error.message}
                </ActionNotice>
            )}
            {!loading && !error && data && !data.available && (
                <SetupRequired title="WooCommerce isn't connected" integration="WooCommerce">
                    {data.reason ?? "WooCommerce needs to be active before products can render in the dashboard."}
                </SetupRequired>
            )}

            {/* Products grid */}
            {!loading && !error && data && data.available && (
                <>
                    {filtered.length === 0 ? (
                        <div className="oversee-card">
                            <EmptyState
                                title="No matching services"
                                description={search || activeCategory ? "Try clearing filters or searching for a different keyword." : "No published products."}
                                actions={
                                    (search || activeCategory) ? (
                                        <button
                                            type="button"
                                            onClick={() => { setSearch(""); setActiveCategory(""); }}
                                            className="oversee-btn-secondary text-xs"
                                        >
                                            Clear filters
                                        </button>
                                    ) : null
                                }
                            />
                        </div>
                    ) : (
                        <>
                            <div className="text-xs text-zinc-500">
                                Showing {filtered.length} of {allProducts.length} services
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                {filtered.map((p) => (
                                    <ProductCard key={p.id} product={p} />
                                ))}
                            </div>
                        </>
                    )}
                    <div className="flex justify-end gap-2">
                        <a href={data.cart_url} className="oversee-btn-secondary text-sm">View cart</a>
                        <a href={data.checkout_url} className="oversee-btn-primary text-sm">Checkout</a>
                    </div>
                </>
            )}
        </div>
    );
}
