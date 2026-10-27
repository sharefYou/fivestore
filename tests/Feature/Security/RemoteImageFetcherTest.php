<?php

namespace Tests\Feature\Security;

use App\Services\ImageStorage;
use App\Services\RemoteImageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * A URL fetched by the server is a request the server makes on someone
 * else's behalf. These tests pin down where it may point and what it may
 * bring back.
 */
class RemoteImageFetcherTest extends TestCase
{
    use RefreshDatabase;

    protected RemoteImageFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
        $this->fetcher = app(RemoteImageFetcher::class);
    }

    protected function allowedUrl(string $id = 'photo-1555041469-a586c61ea9bc'): string
    {
        return 'https://'.RemoteImageFetcher::ALLOWED_HOST.'/'.$id;
    }

    /**
     * Bytes of a real, small JPEG produced locally.
     */
    protected function jpegBytes(int $width = 600, int $height = 400): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 120, 140, 160));
        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    #[Test]
    public function a_host_outside_the_allowlist_is_refused(): void
    {
        foreach ([
            'https://evil.example.com/photo.jpg',
            'https://images.unsplash.com.evil.example.com/photo.jpg',
            'https://169.254.169.254/latest/meta-data/',
            'https://localhost/photo.jpg',
            'https://127.0.0.1/photo.jpg',
        ] as $url) {
            try {
                $this->fetcher->assertAllowed($url);
                $this->fail("Host should have been refused: {$url}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('allowlist', $e->getMessage());
            }
        }
    }

    #[Test]
    public function plain_http_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fetcher->assertAllowed('http://'.RemoteImageFetcher::ALLOWED_HOST.'/photo.jpg');
    }

    #[Test]
    public function a_non_standard_port_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fetcher->assertAllowed('https://'.RemoteImageFetcher::ALLOWED_HOST.':2375/photo.jpg');
    }

    #[Test]
    public function credentials_embedded_in_the_url_are_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fetcher->assertAllowed('https://user:pass@'.RemoteImageFetcher::ALLOWED_HOST.'/photo.jpg');
    }

    #[Test]
    public function the_allowed_host_over_https_is_accepted(): void
    {
        $this->fetcher->assertAllowed($this->allowedUrl());

        $this->assertTrue(true, 'No exception means the URL passed the gate.');
    }

    #[Test]
    public function a_non_200_response_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response('nope', 404),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
    }

    #[Test]
    public function a_response_that_is_not_an_image_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response('<?php echo shell_exec($_GET["c"]); ?>', 200),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
        $this->assertEmpty(Storage::disk(config('catalog.images.disk'))->allFiles());
    }

    #[Test]
    public function an_svg_payload_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 200, [
                'Content-Type' => 'image/svg+xml',
            ]),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
    }

    #[Test]
    public function a_body_beyond_the_size_limit_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response(str_repeat('a', RemoteImageFetcher::MAX_BYTES + 1024), 200),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
    }

    #[Test]
    public function a_declared_length_beyond_the_limit_is_rejected_before_use(): void
    {
        Http::fake([
            '*' => Http::response('small body', 200, [
                'Content-Length' => (string) (RemoteImageFetcher::MAX_BYTES + 5000),
            ]),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
    }

    #[Test]
    public function an_image_smaller_than_the_allowed_dimensions_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response($this->jpegBytes(40, 40), 200),
        ]);

        $this->assertNull($this->fetcher->fetchAndStore($this->allowedUrl(), 'products'));
    }

    #[Test]
    public function a_valid_image_is_stored_under_a_server_generated_name(): void
    {
        Http::fake([
            '*' => Http::response($this->jpegBytes(), 200),
        ]);

        $path = $this->fetcher->fetchAndStore($this->allowedUrl(), 'products');

        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression('#^products/[0-9a-f-]{36}\.(jpg|png|webp)$#', $path);
        // Nothing of the remote filename survives into the stored path.
        $this->assertStringNotContainsString('photo-', $path);
        Storage::disk(config('catalog.images.disk'))->assertExists($path);
    }

    #[Test]
    public function the_request_goes_only_to_the_allowed_host(): void
    {
        Http::fake([
            '*' => Http::response($this->jpegBytes(), 200),
        ]);

        $this->fetcher->fetchAndStore($this->allowedUrl(), 'products');

        Http::assertSent(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) === RemoteImageFetcher::ALLOWED_HOST);
    }

    #[Test]
    public function a_repeated_url_is_downloaded_once(): void
    {
        Http::fake([
            '*' => Http::response($this->jpegBytes(), 200),
        ]);

        $first = $this->fetcher->fetchAndStore($this->allowedUrl(), 'products');
        $second = $this->fetcher->fetchAndStore($this->allowedUrl(), 'products');

        // Two separate stored files, but only one trip over the network.
        $this->assertNotSame($first, $second);
        Http::assertSentCount(1);

        $this->fetcher->flushCache();
    }

    #[Test]
    public function storage_rejects_a_local_file_that_is_not_an_image(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'not-an-image-');
        file_put_contents($temporary, 'definitely not an image');

        $this->expectException(RuntimeException::class);

        try {
            app(ImageStorage::class)->storeFromPath($temporary, 'products');
        } finally {
            @unlink($temporary);
        }
    }
}
