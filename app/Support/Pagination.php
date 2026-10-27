<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * One place that decides what a page number may be and what a listing tells
 * the browser about itself.
 */
final class Pagination
{
    /**
     * Reads and validates a page number from the query string.
     *
     * Anything that is not a plain positive integer is a 404 rather than a
     * silent fallback to page 1 or a 500 from a bad cast: a malformed page is
     * an address that does not exist.
     */
    public static function pageNumber(Request $request, string $pageName = 'page'): int
    {
        $raw = $request->query($pageName);

        if ($raw === null || $raw === '') {
            return 1;
        }

        if (! is_string($raw) || preg_match('/^\d{1,6}$/', $raw) !== 1) {
            abort(404);
        }

        $page = (int) $raw;

        if ($page < 1) {
            abort(404);
        }

        return $page;
    }

    /**
     * A page past the end of a listing is a 404.
     *
     * Without this the paginator quietly returns an empty page, which reads
     * as "this category has no products" on a URL that simply does not exist.
     *
     * An empty listing still answers on page 1, because `lastPage()` is 1 even
     * with nothing to show, so page 1 is never "past the end".
     *
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     * @return LengthAwarePaginator<int, T>
     */
    public static function guardWithinRange(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        if ($paginator->currentPage() > $paginator->lastPage()) {
            abort(404);
        }

        return $paginator;
    }

    /**
     * The pagination block sent to the browser: what a pager needs to draw
     * itself, and nothing more.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'prev_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
        ];
    }
}
