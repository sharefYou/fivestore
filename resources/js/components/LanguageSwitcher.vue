<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';

const { switcher } = useLocale();
const { t } = useTranslations();
</script>

<template>
    <!--
        A plain link per language, not a client-side toggle: each language has
        its own URL, so switching is a normal navigation that can be opened in
        a new tab, bookmarked and crawled. A full page load also lets the
        server send the right `dir` and font for the new language.
    -->
    <nav :aria-label="t('nav.switch_language')" class="flex items-center gap-1 rounded-lg border border-line p-0.5">
        <a
            v-for="option in switcher"
            :key="option.code"
            :href="option.url"
            :lang="option.code"
            :dir="option.direction"
            :aria-current="option.current ? 'true' : undefined"
            class="rounded-md px-2.5 py-1.5 text-xs font-medium transition"
            :class="
                option.current
                    ? 'bg-brand-700 text-white'
                    : 'text-ink-muted hover:bg-surface-sunken hover:text-ink'
            "
        >
            {{ option.name }}
        </a>
    </nav>
</template>
