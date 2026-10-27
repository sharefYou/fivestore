<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import ProductCard from '@/components/ProductCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated, ProductCard as ProductCardType, SeoMeta } from '@/types';

const props = defineProps<{
    filters: { q: string | null; category: string | null };
    categories: { slug: string | null; name: string | null }[];
    products: Paginated<ProductCardType>;
    links: { self: string };
    seo: SeoMeta;
}>();

const { t } = useTranslations();

const term = ref(props.filters.q ?? '');
const category = ref(props.filters.category ?? '');
const searching = ref(false);

// The server owns the result set; the browser only asks for it.
function submit(): void {
    router.get(
        props.links.self,
        {
            ...(term.value.trim() ? { q: term.value.trim() } : {}),
            ...(category.value ? { category: category.value } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        },
    );
}

function clear(): void {
    term.value = '';
    category.value = '';
    submit();
}

// Keep the controls in step when the visitor uses the browser's back button.
watch(
    () => props.filters,
    (filters) => {
        term.value = filters.q ?? '';
        category.value = filters.category ?? '';
    },
);
</script>

<template>
    <Head :title="seo.title" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <header>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ t('products.title') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ t('products.subtitle') }}</p>
        </header>

        <form class="mt-6 flex flex-wrap items-end gap-3" role="search" @submit.prevent="submit">
            <div class="min-w-56 flex-1">
                <label for="search-term" class="block text-xs font-medium text-ink-muted">
                    {{ t('products.search') }}
                </label>
                <input
                    id="search-term"
                    v-model="term"
                    type="search"
                    name="q"
                    :placeholder="t('products.search_placeholder')"
                    maxlength="100"
                    class="mt-1 w-full rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm"
                />
            </div>

            <div v-if="categories.length">
                <label for="search-category" class="block text-xs font-medium text-ink-muted">
                    {{ t('products.category') }}
                </label>
                <select
                    id="search-category"
                    v-model="category"
                    name="category"
                    class="mt-1 w-full rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm"
                    @change="submit"
                >
                    <option value="">{{ t('home.view_all') }}</option>
                    <option v-for="option in categories" :key="option.slug ?? ''" :value="option.slug ?? ''">
                        {{ option.name }}
                    </option>
                </select>
            </div>

            <button
                type="submit"
                class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-800 disabled:opacity-60"
                :disabled="searching"
            >
                {{ t('products.search') }}
            </button>

            <button
                v-if="filters.q || filters.category"
                type="button"
                class="rounded-lg border border-line px-4 py-2 text-sm transition hover:bg-surface-sunken"
                @click="clear"
            >
                {{ t('products.clear_search') }}
            </button>
        </form>

        <p v-if="filters.q" class="mt-5 text-sm text-ink-muted" aria-live="polite">
            {{ t('products.results_for', { term: filters.q }) }}
        </p>

        <div
            v-if="products.data.length"
            class="mt-6 grid gap-5 transition-opacity sm:grid-cols-2 lg:grid-cols-4"
            :class="{ 'opacity-60': searching }"
        >
            <ProductCard
                v-for="(product, index) in products.data"
                :key="product.id"
                :product="product"
                :eager="index < 4"
            />
        </div>

        <EmptyState
            v-else
            class="mt-6"
            :title="filters.q ? t('products.empty_search', { term: filters.q }) : t('products.empty_all')"
        />

        <PaginationNav :meta="products.meta" />
    </div>
</template>
