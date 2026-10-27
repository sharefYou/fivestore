import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SeoMeta, SharedProps } from '@/types';

/**
 * The active locale, plus the URL of the current page in every other locale.
 *
 * Those URLs come from the server with the record's slug for each language,
 * so switching language keeps the visitor on the page they were reading
 * instead of dropping them on the home page.
 */
export function useLocale() {
    const page = usePage<SharedProps>();

    const locale = computed(() => page.props.locale);
    const isRtl = computed(() => locale.value.direction === 'rtl');

    const alternates = computed<Record<string, string>>(() => {
        const seo = page.props.seo as SeoMeta | undefined;

        return seo?.alternates ?? {};
    });

    const switcher = computed(() =>
        locale.value.available.map((option) => ({
            ...option,
            url: alternates.value[option.code] ?? `/${option.code}`,
            current: option.code === locale.value.current,
        })),
    );

    return { locale, isRtl, alternates, switcher };
}
