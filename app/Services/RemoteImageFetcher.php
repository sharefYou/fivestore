<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Downloads catalog photographs from a fixed, allowlisted host.
 *
 * Every rule here exists because a URL fetched by the server is a request the
 * server makes on someone else's behalf:
 *
 * - the host is an allowlist, never anything derived from input, so the
 *   fetcher cannot be pointed at an internal address;
 * - redirects may not leave that host, which is where an open redirect would
 *   otherwise smuggle the request somewhere private;
 * - the response must be 200, must look like an image, and is capped in size
 *   and time so a slow or enormous body cannot tie up the process;
 * - the bytes are validated and stored by ImageStorage, so a remote file ends
 *   up under the same checks and the same UUID naming as an upload.
 *
 * Nothing here accepts a URL from a user. The seeder passes literals.
 */
class RemoteImageFetcher
{
    /** Only this host may be contacted. */
    public const ALLOWED_HOST = 'images.unsplash.com';

    public const TIMEOUT_SECONDS = 10;

    public const MAX_BYTES = 4 * 1024 * 1024;

    /** @var array<string, string> url hash => cached temp file */
    protected array $cache = [];

    public function __construct(protected ImageStorage $images) {}

    /**
     * Fetch a remote image and store it on the catalog disk.
     *
     * Returns the stored path, or null when anything at all went wrong — the
     * caller is expected to fall back rather than fail.
     *
     * @param  'categories'|'products'|'site'  $bucket
     */
    public function fetchAndStore(string $url, string $bucket): ?string
    {
        try {
            $temporary = $this->download($url);

            return $this->images->storeFromPath($temporary, $bucket);
        } catch (\Throwable $e) {
            Log::warning('Remote catalog image could not be fetched.', [
                'url' => $url,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Download to a temporary file, reusing one already fetched in this run.
     *
     * @throws RuntimeException
     */
    public function download(string $url): string
    {
        $key = hash('sha256', $url);

        if (isset($this->cache[$key]) && is_file($this->cache[$key])) {
            return $this->cache[$key];
        }

        $this->assertAllowed($url);

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::TIMEOUT_SECONDS)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['https'],
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                        if ($uri->getHost() !== self::ALLOWED_HOST) {
                            throw new RuntimeException('Redirect left the allowed host.');
                        }
                    },
                ],
            ])
            ->get($url);

        if ($response->status() !== 200) {
            throw new RuntimeException('Unexpected status '.$response->status().'.');
        }

        $declared = (int) ($response->header('Content-Length') ?: 0);

        if ($declared > self::MAX_BYTES) {
            throw new RuntimeException('Declared size exceeds the limit.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Downloaded body exceeds the limit.');
        }

        if ($body === '') {
            throw new RuntimeException('Empty response body.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'catalog-image-');

        if ($temporary === false || file_put_contents($temporary, $body) === false) {
            throw new RuntimeException('Could not buffer the download.');
        }

        return $this->cache[$key] = $temporary;
    }

    /**
     * @throws RuntimeException
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Malformed URL.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new RuntimeException('Only HTTPS is allowed.');
        }

        if (strtolower($parts['host']) !== self::ALLOWED_HOST) {
            throw new RuntimeException('Host is not on the allowlist.');
        }

        // A port or credentials in the URL would be a way to reach something
        // other than the allowlisted service on its normal port.
        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new RuntimeException('Non-standard port is not allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Credentials in the URL are not allowed.');
        }
    }

    /**
     * Remove the temporary files buffered during this run.
     */
    public function flushCache(): void
    {
        foreach ($this->cache as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->cache = [];
    }
}
