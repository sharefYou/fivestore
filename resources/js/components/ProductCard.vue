<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import type { ProductCard } from '@/types';

defineProps<{
    product: ProductCard;
    /** The first row of a grid is visible immediately, so it is not lazy. */
    eager?: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <article class="card group flex h-full flex-col overflow-hidden transition hover:shadow-md">
        <Link :href="product.url" class="block aspect-4/3 overflow-hidden bg-surface-sunken">
            <img
                v-if="product.image?.url"
                :src="product.image.url"
                :alt="product.image.alt ?? ''"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                :loading="eager ? 'eager' : 'lazy'"
                decoding="async"
                width="400"
                height="300"
            />
            <span v-else class="grid h-full place-items-center text-xs text-ink-muted">
                {{ t('products.no_image') }}
            </span>
        </Link>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <span
                v-if="product.is_featured"
                class="w-fit rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-medium text-brand-800"
            >
                {{ t('home.featured') }}
            </span>

            <h3 class="text-sm font-semibold leading-6">
                <Link :href="product.url" class="hover:text-brand-700">{{ product.name }}</Link>
            </h3>

            <Link
                v-if="product.category"
                :href="product.category.url"
                class="text-xs text-ink-muted hover:text-ink"
            >
                {{ product.category.name }}
            </Link>

            <!-- Rendered only when the server sent a price, so an unpriced
                 product shows nothing rather than a misleading zero. -->
            <p v-if="product.price" class="numeric text-sm font-semibold text-brand-800">
                {{ product.price }}
            </p>

            <Link
                :href="product.url"
                class="mt-auto pt-2 text-xs font-medium text-brand-700 hover:text-brand-900"
            >
                {{ t('products.view_details') }}
                <!-- Mirrored in Arabic so the arrow points the way reading goes. -->
                <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
            </Link>
        </div>
    </article>
</template>
