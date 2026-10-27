export type Direction = 'rtl' | 'ltr';

export interface LocaleOption {
    code: string;
    name: string;
    direction: Direction;
}

export interface LocaleInfo {
    current: string;
    direction: Direction;
    html_lang: string;
    available: LocaleOption[];
}

export interface SiteInfo {
    name: string;
    logo: string | null;
    footer_text: string | null;
    meta_description: string | null;
    contact: Partial<Record<'email' | 'phone' | 'address', string>>;
    social: Partial<Record<'facebook' | 'instagram' | 'whatsapp', string>>;
}

export interface SeoMeta {
    title: string;
    description: string | null;
    canonical: string;
    /** One absolute URL per supported locale, used for hreflang and the switcher. */
    alternates: Record<string, string>;
    image: string | null;
    schema: Record<string, unknown> | null;
}

export interface ImageData {
    url: string | null;
    alt: string | null;
    is_primary: boolean;
}

export interface CategoryCard {
    id: number;
    name: string | null;
    description: string | null;
    url: string;
    image: string | null;
    products_count: number | null;
}

export interface ProductCard {
    id: number;
    name: string | null;
    url: string;
    is_featured: boolean;
    /** Already formatted for the active locale, or null when unpriced. */
    price: string | null;
    image: ImageData | null;
    category: { name: string | null; url: string } | null;
}

export interface ProductDetail extends ProductCard {
    description: string | null;
    sku: string | null;
    images: ImageData[];
    updated_at: string | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_url: string | null;
    next_url: string | null;
}

export interface Paginated<T> {
    data: T[];
    meta: PaginationMeta;
}

/** Mirrors lang/{locale}/storefront.php; values are strings at the leaves. */
export type TranslationTree = { [key: string]: string | TranslationTree };

export interface SharedProps {
    locale: LocaleInfo;
    translations: TranslationTree;
    site: SiteInfo;
    flash: { status: string | null };
    [key: string]: unknown;
}
