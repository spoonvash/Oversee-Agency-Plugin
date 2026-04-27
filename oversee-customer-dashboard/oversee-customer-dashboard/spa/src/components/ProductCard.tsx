import { Link } from "react-router-dom";
import { Repeat, ShoppingBag, ExternalLink } from "lucide-react";

export type CommerceProduct = {
    id: number;
    name: string;
    slug?: string;
    type: string;
    is_variable: boolean;
    is_subscription: boolean;
    is_purchasable: boolean;
    in_stock: boolean;
    sku?: string;
    short_description?: string;
    price_html: string;
    image: string;
    image_large?: string;
    permalink: string;
    add_to_cart: string;
    categories: { id: number; slug: string; name: string }[];
    requires_selection: boolean;
    variation_count?: number;
    subscription?: {
        period: string;
        interval: number;
        length: number;
    } | null;
};

export function ProductCard({ product }: { product: CommerceProduct }) {
    const subscriptionBadge = product.is_subscription;
    const variableBadge = product.is_variable;

    return (
        <article className="oversee-card flex flex-col overflow-hidden hover:shadow-md transition-shadow">
            <Link to={`/services/${product.id}`} className="block aspect-[4/3] bg-zinc-100 dark:bg-zinc-900 relative overflow-hidden">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={product.name}
                        loading="lazy"
                        className="w-full h-full object-cover transition-transform group-hover:scale-105"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-zinc-400">
                        <ShoppingBag size={32} />
                    </div>
                )}
                <div className="absolute top-2 left-2 flex flex-wrap gap-1.5">
                    {subscriptionBadge && (
                        <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-white/90 text-zinc-700 backdrop-blur">
                            <Repeat size={10} /> Subscription
                        </span>
                    )}
                    {variableBadge && (
                        <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-white/90 text-zinc-700 backdrop-blur">
                            Options
                        </span>
                    )}
                </div>
            </Link>
            <div className="p-4 flex flex-col gap-2 flex-1">
                <div className="flex flex-wrap gap-1.5">
                    {product.categories.slice(0, 2).map((c) => (
                        <span
                            key={c.id}
                            className="text-[10px] uppercase tracking-wider text-zinc-500"
                        >
                            {c.name}
                        </span>
                    ))}
                </div>
                <Link to={`/services/${product.id}`} className="font-medium text-sm leading-snug hover:underline">
                    {product.name}
                </Link>
                {product.short_description ? (
                    <p className="text-xs text-zinc-500 line-clamp-2">{product.short_description}</p>
                ) : null}
                <div
                    className="text-sm font-semibold mt-auto"
                    // price_html includes WC's markup (subscription suffixes, sale prices, etc.)
                    // — sanitised by WC; safe to dangerouslySetInnerHTML for formatting.
                    dangerouslySetInnerHTML={{ __html: product.price_html }}
                />
                <div className="flex gap-2 mt-2">
                    {product.requires_selection ? (
                        <Link
                            to={`/services/${product.id}`}
                            className="oversee-btn-primary text-xs flex-1 text-center"
                        >
                            Choose options
                        </Link>
                    ) : product.is_purchasable ? (
                        <a
                            href={product.add_to_cart}
                            className="oversee-btn-primary text-xs flex-1 text-center"
                        >
                            Add to cart
                        </a>
                    ) : (
                        <span className="oversee-btn-secondary text-xs flex-1 text-center opacity-60 cursor-not-allowed">
                            Not available
                        </span>
                    )}
                    <a
                        href={product.permalink}
                        target="_blank"
                        rel="noreferrer"
                        className="oversee-btn-secondary text-xs flex items-center justify-center px-3"
                        title="View on overseeagency.com"
                    >
                        <ExternalLink size={12} />
                    </a>
                </div>
            </div>
        </article>
    );
}
