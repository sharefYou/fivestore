<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { t } = useTranslations();
const { locale } = useLocale();

const site = computed(() => page.props.site);
const menuOpen = ref(false);

const links = computed(() => [
    { label: t('nav.home'), href: `/${locale.value.current}` },
    { label: t('nav.categories'), href: `/${locale.value.current}/categories` },
    { label: t('nav.products'), href: `/${locale.value.current}/products` },
]);

const year = new Date().getFullYear();

// A page change must not leave the mobile menu hanging open over the new page.
watch(() => page.url, () => (menuOpen.value = false));

function isCurrent(href: string): boolean {
    const path = page.url.split('?')[0];

    return href === `/${locale.value.current}` ? path === href : path.startsWith(href);
}
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:start-3 focus:z-50 focus:rounded-lg focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-white"
        >
            {{ t('nav.skip_to_content') }}
        </a>

        <header class="sticky top-0 z-40 border-b border-line bg-surface-raised/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6">
                <Link :href="`/${locale.current}`" class="flex items-center gap-2 font-semibold">
                    <img
                        v-if="site.logo"
                        :src="site.logo"
                        :alt="site.name"
                        class="h-9 w-9 rounded-lg object-cover"
                        width="36"
                        height="36"
                    />
                    <span
                        v-else
                        class="grid h-9 w-9 place-items-center rounded-lg bg-brand-700 text-sm font-bold text-white"
                        aria-hidden="true"
                    >
                        {{ site.name.slice(0, 1) }}
                    </span>
                    <span class="text-base sm:text-lg">{{ site.name }}</span>
                </Link>

                <nav class="ms-auto hidden items-center gap-1 md:flex" :aria-label="t('nav.home')">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-ink-muted transition hover:bg-surface-sunken hover:text-ink"
                        :class="{ 'bg-surface-sunken text-ink': isCurrent(link.href) }"
                        :aria-current="isCurrent(link.href) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="ms-auto md:ms-0">
                    <LanguageSwitcher />
                </div>

                <button
                    type="button"
                    class="rounded-lg border border-line p-2 md:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-menu"
                    :aria-label="menuOpen ? t('nav.close_menu') : t('nav.open_menu')"
                    @click="menuOpen = !menuOpen"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path v-if="!menuOpen" d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round" />
                        <path v-else d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <nav v-if="menuOpen" id="mobile-menu" class="border-t border-line bg-surface-raised md:hidden">
                <div class="mx-auto flex max-w-6xl flex-col px-4 py-2 sm:px-6">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2.5 text-sm font-medium"
                        :class="isCurrent(link.href) ? 'bg-surface-sunken text-ink' : 'text-ink-muted'"
                        :aria-current="isCurrent(link.href) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </nav>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>

        <footer class="mt-16 border-t border-line bg-surface-raised">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
                <div>
                    <p class="text-base font-semibold">{{ site.name }}</p>
                    <p v-if="site.footer_text" class="mt-2 text-sm text-ink-muted">
                        {{ site.footer_text }}
                    </p>
                </div>

                <div>
                    <h2 class="text-sm font-semibold">{{ t('footer.browse') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="link in links" :key="link.href">
                            <Link :href="link.href" class="text-ink-muted hover:text-ink">{{ link.label }}</Link>
                        </li>
                    </ul>
                </div>

                <div v-if="Object.keys(site.contact).length || Object.keys(site.social).length">
                    <h2 class="text-sm font-semibold">{{ t('footer.contact') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                        <li v-if="site.contact.email">
                            <a :href="`mailto:${site.contact.email}`" class="hover:text-ink">{{ site.contact.email }}</a>
                        </li>
                        <li v-if="site.contact.phone">
                            <a :href="`tel:${site.contact.phone}`" class="numeric hover:text-ink">{{ site.contact.phone }}</a>
                        </li>
                        <li v-if="site.contact.address">{{ site.contact.address }}</li>
                    </ul>

                    <ul v-if="Object.keys(site.social).length" class="mt-4 flex gap-3 text-sm">
                        <li v-for="(url, network) in site.social" :key="network">
                            <a
                                :href="url"
                                class="rounded-lg border border-line px-3 py-1.5 capitalize text-ink-muted hover:text-ink"
                                rel="noopener noreferrer"
                                target="_blank"
                            >
                                {{ network }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-line">
                <p class="mx-auto max-w-6xl px-4 py-4 text-xs text-ink-muted sm:px-6">
                    <span class="numeric">{{ year }}</span> © {{ site.name }} — {{ t('footer.rights') }}
                </p>
            </div>
        </footer>
    </div>
</template>
