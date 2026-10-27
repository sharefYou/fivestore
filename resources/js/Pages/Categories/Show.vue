<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import ProductCard from '@/components/ProductCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryCard, Paginated, ProductCard as ProductCardType, SeoMeta } from '@/types';

defineProps<{
    category: CategoryCard;
    products: Paginated<ProductCardType>;
    links: { categories: string };
    seo: SeoMeta;
}>();

const { t, tChoice } = useTranslations();
</script>

<template>
    <Head :title="seo.title" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav class="text-xs text-ink-muted" :aria-label="t('categories.title')">
            <Link :href="links.categories" class="hover:text-ink">{{ t('categories.title') }}</Link>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-ink">{{ category.name }}</span>
        </nav>

        <header class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-start">
            <img
                v-if="category.image"
                :src="category.image"
                :alt="category.name ?? ''"
                class="card h-40 w-full object-cover sm:h-32 sm:w-56"
                loading="eager"
                decoding="async"
                width="224"
                height="128"
            />
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ category.name }}</h1>
                <p v-if="category.description" class="mt-2 max-w-2xl text-sm text-ink-muted">
                    {{ category.description }}
                </p>
                <p class="mt-2 text-xs text-ink-muted">
                    {{ tChoice('categories.product_count', products.meta.total) }}
                </p>
            </div>
        </header>

        <div v-if="products.data.length" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <ProductCard
                v-for="(product, index) in products.data"
                :key="product.id"
                :product="product"
                :eager="index < 4"
            />
        </div>

        <EmptyState v-else class="mt-8" :title="t('products.empty')" />

        <PaginationNav :meta="products.meta" />
    </div>
</template>
