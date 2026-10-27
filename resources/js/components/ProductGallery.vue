<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ImageData } from '@/types';

const props = defineProps<{
    images: ImageData[];
    name: string | null;
}>();

const { t } = useTranslations();

const activeIndex = ref(0);

// Navigating to another product reuses this component, so reset the selection.
watch(
    () => props.images,
    () => (activeIndex.value = 0),
);

const active = computed<ImageData | null>(() => props.images[activeIndex.value] ?? null);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="card aspect-square overflow-hidden bg-surface-sunken">
            <img
                v-if="active?.url"
                :src="active.url"
                :alt="active.alt ?? name ?? ''"
                class="h-full w-full object-cover"
                loading="eager"
                decoding="async"
                width="800"
                height="800"
            />
            <p v-else class="grid h-full place-items-center text-sm text-ink-muted">
                {{ t('products.no_image') }}
            </p>
        </div>

        <!-- Thumbnails are buttons, not links: they change what this page shows. -->
        <div
            v-if="images.length > 1"
            class="flex gap-2 overflow-x-auto pb-1"
            role="group"
            :aria-label="t('products.gallery')"
        >
            <button
                v-for="(image, index) in images"
                :key="index"
                type="button"
                class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition"
                :class="index === activeIndex ? 'border-brand-700' : 'border-line hover:border-brand-200'"
                :aria-label="t('products.show_image', { number: index + 1 })"
                :aria-pressed="index === activeIndex"
                @click="activeIndex = index"
            >
                <img
                    v-if="image.url"
                    :src="image.url"
                    :alt="image.alt ?? ''"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                    width="64"
                    height="64"
                />
            </button>
        </div>
    </div>
</template>
