<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryCard } from '@/types';

defineProps<{
    category: CategoryCard;
    eager?: boolean;
}>();

const { t, tChoice } = useTranslations();
</script>

<template>
    <article class="card group flex h-full flex-col overflow-hidden transition hover:shadow-md">
        <Link :href="category.url" class="block aspect-16/9 overflow-hidden bg-surface-sunken">
            <img
                v-if="category.image"
                :src="category.image"
                :alt="category.name ?? ''"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                :loading="eager ? 'eager' : 'lazy'"
                decoding="async"
                width="480"
                height="270"
            />
            <span v-else class="grid h-full place-items-center text-xs text-ink-muted">
                {{ t('products.no_image') }}
            </span>
        </Link>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <h3 class="text-base font-semibold">
                <Link :href="category.url" class="hover:text-brand-700">{{ category.name }}</Link>
            </h3>

            <p v-if="category.description" class="line-clamp-2 text-sm text-ink-muted">
                {{ category.description }}
            </p>

            <p v-if="category.products_count !== null" class="mt-auto pt-2 text-xs text-ink-muted">
                {{ tChoice('categories.product_count', category.products_count) }}
            </p>
        </div>
    </article>
</template>
