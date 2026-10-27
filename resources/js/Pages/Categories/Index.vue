<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryCard from '@/components/CategoryCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryCard as CategoryCardType, SeoMeta } from '@/types';

defineProps<{
    categories: CategoryCardType[];
    seo: SeoMeta;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="seo.title" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <header>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ t('categories.title') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ t('categories.subtitle') }}</p>
        </header>

        <div v-if="categories.length" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <CategoryCard
                v-for="(category, index) in categories"
                :key="category.id"
                :category="category"
                :eager="index < 3"
            />
        </div>

        <EmptyState v-else class="mt-8" :title="t('categories.empty')" />
    </div>
</template>
