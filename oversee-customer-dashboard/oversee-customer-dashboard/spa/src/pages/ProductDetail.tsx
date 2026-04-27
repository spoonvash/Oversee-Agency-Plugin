import { useEffect, useMemo, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { ArrowLeft, ExternalLink, ShoppingCart, Repeat } from "lucide-react";
import { apiGet, apiPost, ApiError } from "@/lib/api";
import { ActionNotice, SetupRequired, Skeleton } from "@/components/ActionNotice";
import { useToast } from "@/components/Toast";
import { CommerceProduct } from "@/components/ProductCard";

type Variation = {
    id: number;
    attributes: Record<string, string>;
    price_html: string;
    is_in_stock: boolean;
    is_purchasable: boolean;
    add_to_cart: string;
};

type DetailResponse = CommerceProduct & {
    description: string;
    images: string[];
    attributes: {
        key: string;
        name: string;
        label: string;
        taxonomy: boolean;
        options: { slug: string; label: string }[];
    }[];
    variations?: Variation[];
};

type ResolveResponse = {
    variation_id: number;
    product_id: number;
    attributes: Record<string, string>;
    price_html: string;
    add_to_cart: string;
    permalink: string;
};

export function ProductDetail() {
    const { id } = useParams<{ id: string }>();
    const productId = Number(id);
    const toast = useToast();

    const [data, setData] = useState<DetailResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<{ status?: number; message: string } | null>(null);
    const [selected, setSelected] = useState<Record<string, string>>({});
    const [resolving, setResolving] = useState(false);
    const [resolved, setResolved] = useState<ResolveResponse | null>(null);
    const [resolveError, setResolveError] = useState<string | null>(null);
    const [activeImage, setActiveImage] = useState(0);

    useEffect(() => {
        let active = true;
        setLoading(true);
        apiGet<DetailResponse>(`commerce/products/${productId}`, { namespace: "oversee/v1" })
            .then((res) => { if (active) { setData(res); setError(null); } })
            .catch((err: unknown) => {
                if (!active) return;
                const status = err instanceof ApiError ? err.status : undefined;
                setError({ status, message: err instanceof Error ? err.message : "Could not load product" });
            })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [productId]);

    const allRequired = useMemo(() => {
        if (!data?.is_variable || !data.attributes) return true;
        return data.attributes.every((a) => !!selected[a.key]);
    }, [data, selected]);

    // Auto-resolve once all attributes are picked.
    useEffect(() => {
        if (!data?.is_variable || !allRequired) {
            setResolved(null);
            setResolveError(null);
            return;
        }
        let active = true;
        setResolving(true);
        setResolveError(null);
        apiPost<ResolveResponse>(`commerce/products/${productId}/resolve-variation`, { attributes: selected }, { namespace: "oversee/v1" })
            .then((res) => { if (active) setResolved(res); })
            .catch((err: unknown) => {
                if (!active) return;
                setResolveError(err instanceof Error ? err.message : "Could not resolve variation");
                setResolved(null);
            })
            .finally(() => { if (active) setResolving(false); });
        return () => { active = false; };
    }, [data, selected, allRequired, productId]);

    if (loading) {
        return (
            <div className="space-y-4 max-w-[1100px]">
                <Skeleton className="h-4 w-32" />
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Skeleton className="aspect-square w-full" />
                    <div className="space-y-3">
                        <Skeleton className="h-6 w-3/4" />
                        <Skeleton className="h-4 w-full" lines={3} />
                        <Skeleton className="h-10 w-full" />
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        if (error.status === 404) {
            return (
                <ActionNotice tone="warning" title="Service not found"
                    actions={<Link to="/services" className="oversee-btn-secondary text-xs">Back to services</Link>}
                >
                    This product no longer exists or isn't published.
                </ActionNotice>
            );
        }
        if (error.status === 503) {
            return <SetupRequired title="WooCommerce isn't active" integration="WooCommerce" />;
        }
        return <ActionNotice tone="warning" title="Could not load product">{error.message}</ActionNotice>;
    }

    if (!data) return null;

    const heroImage = data.images?.[activeImage] ?? data.image_large ?? data.image;
    const ctaUrl = resolved?.add_to_cart ?? (!data.is_variable ? data.add_to_cart : null);
    const ctaPriceHtml = resolved?.price_html ?? data.price_html;

    const handleAddToCart = (e: React.MouseEvent<HTMLAnchorElement>) => {
        // Let the browser navigate to the WC cart URL — that flow runs the
        // native add-to-cart logic and lands the customer on /cart/.
        if (!ctaUrl) {
            e.preventDefault();
            toast.push("Please choose all required options first.", "info");
            return;
        }
        toast.push("Adding to cart…");
    };

    return (
        <div className="space-y-6 max-w-[1100px]">
            <Link to="/services" className="text-xs text-zinc-500 inline-flex items-center gap-1 hover:underline">
                <ArrowLeft size={12} /> Back to services
            </Link>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Gallery */}
                <div className="space-y-2">
                    <div className="oversee-card overflow-hidden aspect-square bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center">
                        {heroImage ? (
                            <img src={heroImage} alt={data.name} className="w-full h-full object-contain" />
                        ) : (
                            <div className="text-zinc-400 text-sm">No image</div>
                        )}
                    </div>
                    {data.images?.length > 1 && (
                        <div className="flex gap-2 overflow-x-auto">
                            {data.images.map((src, i) => (
                                <button
                                    key={src + i}
                                    type="button"
                                    onClick={() => setActiveImage(i)}
                                    className={`w-16 h-16 rounded border overflow-hidden shrink-0 ${i === activeImage ? "border-zinc-900" : ""}`}
                                    style={i === activeImage ? undefined : { borderColor: "var(--oversee-border)" }}
                                >
                                    <img src={src} alt="" className="w-full h-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Details */}
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-1.5">
                        {data.categories.map((c) => (
                            <span key={c.id} className="text-[10px] uppercase tracking-wider text-zinc-500">
                                {c.name}
                            </span>
                        ))}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">{data.name}</h1>
                    {data.is_subscription && (
                        <div className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                            <Repeat size={12} /> Recurring subscription
                        </div>
                    )}
                    <div className="text-xl font-semibold" dangerouslySetInnerHTML={{ __html: ctaPriceHtml }} />

                    {data.short_description && (
                        <p className="text-sm text-zinc-600 dark:text-zinc-300">{data.short_description}</p>
                    )}

                    {/* Variant selectors */}
                    {data.is_variable && (
                        <div className="space-y-3 oversee-card p-4">
                            <div className="text-xs font-semibold uppercase tracking-wider text-zinc-500">Choose options</div>
                            {data.attributes.map((attr) => (
                                <AttributePicker
                                    key={attr.key}
                                    attribute={attr}
                                    value={selected[attr.key] ?? ""}
                                    onChange={(v) => setSelected((cur) => ({ ...cur, [attr.key]: v }))}
                                />
                            ))}
                            {resolveError && (
                                <ActionNotice tone="warning" title="That combination isn't available">
                                    {resolveError}
                                </ActionNotice>
                            )}
                            {resolving && <p className="text-xs text-zinc-500">Checking availability…</p>}
                            {!allRequired && (
                                <p className="text-xs text-zinc-500">Pick every option to see the matching price.</p>
                            )}
                        </div>
                    )}

                    <div className="flex gap-2">
                        {ctaUrl ? (
                            <a
                                href={ctaUrl}
                                onClick={handleAddToCart}
                                className="oversee-btn-primary text-sm flex-1 inline-flex items-center justify-center gap-2"
                            >
                                <ShoppingCart size={14} /> Add to cart
                            </a>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="oversee-btn-secondary text-sm flex-1 opacity-60 cursor-not-allowed inline-flex items-center justify-center gap-2"
                                title="Choose every option to enable add-to-cart"
                            >
                                <ShoppingCart size={14} /> Choose options
                            </button>
                        )}
                        <a
                            href={data.permalink}
                            target="_blank"
                            rel="noreferrer"
                            className="oversee-btn-secondary text-sm inline-flex items-center gap-2"
                        >
                            <ExternalLink size={14} /> View on site
                        </a>
                    </div>

                    {data.sku && (
                        <p className="text-xs text-zinc-500">SKU: {data.sku}</p>
                    )}
                </div>
            </div>

            {data.description && (
                <section className="oversee-card p-6">
                    <h2 className="font-medium mb-3">About this service</h2>
                    <div
                        className="prose prose-sm max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: data.description }}
                    />
                </section>
            )}
        </div>
    );
}

function AttributePicker({
    attribute,
    value,
    onChange,
}: {
    attribute: { key: string; label: string; options: { slug: string; label: string }[] };
    value: string;
    onChange: (v: string) => void;
}) {
    const isCompact = attribute.options.length <= 8;
    if (isCompact) {
        return (
            <div>
                <div className="text-xs font-medium mb-1.5">{attribute.label}</div>
                <div className="flex flex-wrap gap-2">
                    {attribute.options.map((opt) => {
                        const active = value === opt.slug;
                        return (
                            <button
                                key={opt.slug}
                                type="button"
                                onClick={() => onChange(opt.slug)}
                                className={`text-xs px-3 py-1.5 rounded border ${active ? "bg-zinc-900 text-white border-zinc-900" : "bg-transparent"}`}
                                style={active ? undefined : { borderColor: "var(--oversee-border)" }}
                            >
                                {opt.label}
                            </button>
                        );
                    })}
                </div>
            </div>
        );
    }
    return (
        <div>
            <label className="text-xs font-medium mb-1.5 block" htmlFor={`attr-${attribute.key}`}>
                {attribute.label}
            </label>
            <select
                id={`attr-${attribute.key}`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full text-sm px-3 py-2 rounded-md border bg-white dark:bg-zinc-900"
                style={{ borderColor: "var(--oversee-border)" }}
            >
                <option value="">Select…</option>
                {attribute.options.map((opt) => (
                    <option key={opt.slug} value={opt.slug}>{opt.label}</option>
                ))}
            </select>
        </div>
    );
}
