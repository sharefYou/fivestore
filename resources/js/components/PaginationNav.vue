<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import type { PaginationMeta } from '@/types';

defineProps<{ meta: PaginationMeta }>();

const { t } = useTranslations();
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="mt-8 flex flex-wrap items-center justify-between gap-3"
        :aria-label="t('pagination.label')"
    >
        <p class="text-xs text-ink-muted">
            {{
                t('pagination.showing', {
                    from: meta.from ?? 0,
                    to: meta.to ?? 0,
                    total: meta.total,
                })
            }}
        </p>

        <div class="flex items-center gap-2">
            <component
                :is="meta.prev_url ? Link : 'span'"
                :href="meta.prev_url ?? undefined"
                class="rounded-lg border border-line px-3 py-1.5 text-sm"
                :class="meta.prev_url ? 'hover:bg-surface-sunken' : 'cursor-not-allowed text-ink-muted opacity-60'"
                :aria-disabled="meta.prev_url ? undefined : 'true'"
                preserve-scroll
            >
                {{ t('pagination.previous') }}
            </component>

            <span class="numeric text-xs text-ink-muted">
                {{ meta.current_page }} / {{ meta.last_page }}
            </span>

            <component
                :is="meta.next_url ? Link : 'span'"
                :href="meta.next_url ?? undefined"
                class="rounded-lg border border-line px-3 py-1.5 text-sm"
                :class="meta.next_url ? 'hover:bg-surface-sunken' : 'cursor-not-allowed text-ink-muted opacity-60'"
                :aria-disabled="meta.next_url ? undefined : 'true'"
                preserve-scroll
            >
                {{ t('pagination.next') }}
            </component>
        </div>
    </nav>
</template>
