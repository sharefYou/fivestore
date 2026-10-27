<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProductCard from '@/components/ProductCard.vue';
import ProductGallery from '@/components/ProductGallery.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ProductCard as ProductCardType, ProductDetail, SeoMeta } from '@/types';

defineProps<{
    product: ProductDetail;
    related: ProductCardType[];
    links: { category: string };
    seo: SeoMeta;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="seo.title" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav class="text-xs text-ink-muted" :aria-label="t('products.category')">
            <Link v-if="product.category" :href="links.category" class="hover:text-ink">
                {{ product.category.name }}
            </Link>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-ink">{{ product.name }}</span>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            <ProductGallery :images="product.images" :name="product.name" />

            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ product.name }}</h1>

                <dl class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <div v-if="product.category" class="flex items-center gap-2">
                        <dt class="text-ink-muted">{{ t('products.category') }}:</dt>
                        <dd>
                            <Link :href="links.category" class="font-medium text-brand-700 hover:text-brand-900">
                                {{ product.category.name }}
                            </Link>
                        </dd>
                    </div>
                    <div v-if="product.sku" class="flex items-center gap-2">
                        <dt class="text-ink-muted">{{ t('products.sku') }}:</dt>
                        <dd class="numeric font-medium">{{ product.sku }}</dd>
                    </div>
                </dl>

                <section class="mt-6" aria-labelledby="product-description">
                    <h2 id="product-description" class="text-sm font-semibold">
                        {{ t('products.description') }}
                    </h2>
                    <!--
                        Interpolation, never v-html: the description is written
                        in the admin panel and is rendered as text.
                    -->
                    <p v-if="product.description" class="mt-2 whitespace-pre-line text-sm leading-7 text-ink-muted">
                        {{ product.description }}
                    </p>
                    <p v-else class="mt-2 text-sm text-ink-muted">{{ t('products.no_description') }}</p>
                </section>

                <Link
                    v-if="product.category"
                    :href="links.category"
                    class="mt-8 inline-flex items-center gap-2 rounded-lg border border-line px-4 py-2 text-sm transition hover:bg-surface-sunken"
                >
                    <span aria-hidden="true" class="inline-block rtl:-scale-x-100">←</span>
                    {{ t('products.back_to_category', { name: product.category.name ?? '' }) }}
                </Link>
            </div>
        </div>

        <section v-if="related.length" class="mt-16" aria-labelledby="related-products">
            <h2 id="related-products" class="text-xl font-semibold">{{ t('products.related') }}</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <ProductCard v-for="item in related" :key="item.id" :product="item" />
            </div>
        </section>
    </div>
</template>
