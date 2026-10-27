<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CategoryCard from '@/components/CategoryCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import ProductCard from '@/components/ProductCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import PaginationNav from '@/components/PaginationNav.vue';
import type {
    CategoryCard as CategoryCardType,
    Paginated,
    ProductCard as ProductCardType,
    SeoMeta,
} from '@/types';

defineProps<{
    hero: { title: string; subtitle: string };
    categories: CategoryCardType[];
    featured: Paginated<ProductCardType>;
    latest: Paginated<ProductCardType>;
    links: { categories: string; products: string };
    seo: SeoMeta;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="seo.title" />

    <section class="border-b border-line bg-surface-raised">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <h1 class="max-w-3xl text-3xl font-bold leading-tight sm:text-5xl">{{ hero.title }}</h1>
            <p class="mt-4 max-w-2xl text-base text-ink-muted sm:text-lg">{{ hero.subtitle }}</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <Link
                    :href="links.categories"
                    class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-800"
                >
                    {{ t('home.browse_categories') }}
                </Link>
                <Link
                    :href="links.products"
                    class="rounded-lg border border-line px-5 py-2.5 text-sm font-medium transition hover:bg-surface-sunken"
                >
                    {{ t('nav.products') }}
                </Link>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <section v-if="categories.length" aria-labelledby="home-categories">
            <div class="flex items-end justify-between gap-4">
                <h2 id="home-categories" class="text-xl font-semibold sm:text-2xl">
                    {{ t('home.categories') }}
                </h2>
                <Link :href="links.categories" class="text-sm font-medium text-brand-700 hover:text-brand-900">
                    {{ t('home.view_all') }}
                </Link>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <CategoryCard
                    v-for="(category, index) in categories"
                    :key="category.id"
                    :category="category"
                    :eager="index < 3"
                />
            </div>
        </section>

        <EmptyState v-else :title="t('categories.empty')" class="mt-4" />

        <section v-if="featured.data.length" class="mt-14" aria-labelledby="home-featured">
            <h2 id="home-featured" class="text-xl font-semibold sm:text-2xl">{{ t('home.featured') }}</h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <ProductCard v-for="product in featured.data" :key="product.id" :product="product" />
            </div>

            <PaginationNav :meta="featured.meta" />
        </section>

        <section v-if="latest.data.length" class="mt-14" aria-labelledby="home-latest">
            <div class="flex items-end justify-between gap-4">
                <h2 id="home-latest" class="text-xl font-semibold sm:text-2xl">{{ t('home.latest') }}</h2>
                <Link :href="links.products" class="text-sm font-medium text-brand-700 hover:text-brand-900">
                    {{ t('home.view_all') }}
                </Link>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <ProductCard v-for="product in latest.data" :key="product.id" :product="product" />
            </div>

            <PaginationNav :meta="latest.meta" />
        </section>
    </div>
</template>
