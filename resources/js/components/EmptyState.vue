<script setup lang="ts">
defineProps<{
    title: string;
    description?: string | null;
}>();
</script>

<template>
    <div class="card grid place-items-center gap-2 px-6 py-14 text-center">
        <svg class="h-10 w-10 text-ink-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z" stroke-linejoin="round" />
            <path d="M3.5 7.5 12 12m0 0 8.5-4.5M12 12v9" stroke-linejoin="round" />
        </svg>
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-md text-sm text-ink-muted">{{ description }}</p>
        <slot />
    </div>
</template>
