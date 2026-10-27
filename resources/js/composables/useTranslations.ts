import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps, TranslationTree } from '@/types';

type Replacements = Record<string, string | number>;

function lookup(tree: TranslationTree, key: string): string | null {
    const value = key.split('.').reduce<string | TranslationTree | undefined>(
        (carry, segment) =>
            carry && typeof carry === 'object' ? carry[segment] : undefined,
        tree,
    );

    return typeof value === 'string' ? value : null;
}

function substitute(line: string, replacements: Replacements): string {
    return Object.entries(replacements).reduce(
        (carry, [key, value]) => carry.replaceAll(`:${key}`, String(value)),
        line,
    );
}

/**
 * Picks the segment matching `count` from Laravel's pluralisation syntax,
 * for example `{0} none|{1} one|[2,*] :count items`.
 *
 * Arabic needs the explicit ranges this syntax provides, because it has
 * separate forms for one, two, a few and many.
 */
function choose(line: string, count: number): string {
    const segments = line.split('|');

    for (const segment of segments) {
        const exact = segment.match(/^\s*\{(\d+)\}\s*/);

        if (exact && Number(exact[1]) === count) {
            return segment.slice(exact[0].length);
        }

        const range = segment.match(/^\s*\[(\d+),\s*(\d+|\*)\]\s*/);

        if (range) {
            const from = Number(range[1]);
            const to = range[2] === '*' ? Number.POSITIVE_INFINITY : Number(range[2]);

            if (count >= from && count <= to) {
                return segment.slice(range[0].length);
            }
        }
    }

    // No explicit match: fall back to the plural form, then the only form.
    const plain = segments.filter((segment) => !/^\s*[{[]/.test(segment));

    return (count === 1 ? plain[0] : (plain[1] ?? plain[0])) ?? line;
}

/**
 * Reads the strings published by lang/{locale}/storefront.php, so the server
 * and the browser render the same wording from one source.
 */
export function useTranslations() {
    const page = usePage<SharedProps>();

    const tree = computed(() => page.props.translations);

    function t(key: string, replacements: Replacements = {}): string {
        const line = lookup(tree.value, key);

        // Showing the key is louder than showing nothing, which is what a
        // missing translation should be during development.
        return line === null ? key : substitute(line, replacements);
    }

    function tChoice(key: string, count: number, replacements: Replacements = {}): string {
        const line = lookup(tree.value, key);

        if (line === null) {
            return key;
        }

        return substitute(choose(line, count), { count, ...replacements });
    }

    return { t, tChoice };
}
