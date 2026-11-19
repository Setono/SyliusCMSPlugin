<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Exception\PlaywrightException;
use Playwright\Network\RequestInterface;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * The Downloads block, whose links lead to each document's permanent URL, and the Video block with a YouTube URL. See
 * "Video" and "Downloads" in docs/spec/blocks.md and "Permanent URLs" in docs/spec/media.md
 */
final class VideoAndDownloadsTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_admin_user_add_a_downloads_block_with_a_pdf_that_a_shopper_clicks_and_gets(): void
    {
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        $this->signInAsAdmin();
        $entry = self::createPage('Help', 'help');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Downloads', 'exact' => true])->click();
        $page->getByLabel('Heading')->fill('Guides');

        // The field's button opens the media library as a picker of documents
        $page->getByLabel('Documents', ['exact' => true])->click();
        $picker = $page->getByRole('dialog', ['name' => 'Choose from the media library']);
        Expect::locator($picker->getByRole('combobox', ['name' => 'Kind']))->toHaveValue('document');
        $picker->locator('[data-media-item]')->filter(['hasText' => 'size-guide'])->click();
        $picker->getByRole('button', ['name' => 'Choose 1', 'exact' => true])->click();
        Expect::locator($picker)->toHaveCount(0);

        Expect::locator($page->frameLocator('iframe')->locator('.cms-tree > .cms-downloads .cms-downloads__item'))->toHaveText('size-guide');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/help');
        Expect::locator($shop->locator('.cms-tree > .cms-downloads .cms-downloads__heading'))->toHaveText('Guides');
        $link = $shop->locator('.cms-tree > .cms-downloads a.cms-downloads__item');
        Expect::locator($link)->toHaveText('size-guide');
        Expect::locator($link)->toHaveAttribute('href', sprintf('http://localhost/media/%s/size-guide.pdf', $sizeGuide->getUuid()));

        // Following the link, the permanent URL redirects to the document's current file, which the browser gets. The
        // permanent URL is checked by its request, not its response: playwright-symfony stands in for the redirect with
        // a 204 and then sends the page to the file, and that navigation can cancel the 204 before the browser reports
        // it. PermanentUrlTest checks the redirect itself
        $requests = [];
        $shop->events()->onRequest(static function (RequestInterface $request) use (&$requests): void {
            $requests[] = $request->url();
        });
        $responses = [];
        $shop->events()->onResponse(static function (ResponseInterface $response) use (&$responses): void {
            $responses[$response->url()] = $response;
        });

        try {
            $link->click();
        } catch (PlaywrightException $exception) {
            // A headless browser downloads a PDF it's led to, where a desktop browser shows it
            self::assertStringContainsString('Download is starting', $exception->getMessage());
        }

        $permanentUrl = sprintf('http://localhost/media/%s/size-guide.pdf', $sizeGuide->getUuid());
        $fileUrl = 'http://localhost/media/cms/' . $sizeGuide->getPath();
        self::awaitResponses($shop, $responses, $fileUrl);

        self::assertContains($permanentUrl, $requests, 'The link leads to the permanent URL');
        $pdf = $responses[$fileUrl] ?? null;
        self::assertNotNull($pdf, 'The permanent URL redirects to the current file');
        self::assertSame(200, $pdf->status());
        self::assertStringStartsWith('application/pdf', (string) $pdf->headerValue('content-type'));
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_add_a_video_block_with_a_youtube_url_and_see_its_embed(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPage('Our story', 'our-story');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Video', 'exact' => true])->click();

        // Until a video is set, the preview shows a grey 16:9 box where it goes
        $preview = $page->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.cms-tree > .cms-video > .cms-video__frame > .cms-image-placeholder'))->toBeVisible();

        $page->getByLabel('YouTube or Vimeo URL')->fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $embed = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
        Expect::locator($preview->locator('.cms-tree > .cms-video > .cms-video__frame > iframe'))->toHaveAttribute('src', $embed);

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/our-story');
        $iframe = $shop->locator('.cms-tree > .cms-video > .cms-video__frame > iframe');
        Expect::locator($iframe)->toHaveAttribute('src', $embed);
        Expect::locator($iframe)->toHaveAttribute('title', 'Video');

        // The frame is 16:9 and the embed fills it
        $frame = $shop->locator('.cms-tree .cms-video__frame');
        $size = $frame->evaluate('frame => [frame.clientWidth, frame.clientHeight, frame.firstElementChild.clientHeight]');
        self::assertIsArray($size);
        [$width, $height, $embedHeight] = $size;
        self::assertIsInt($width);
        self::assertIsInt($height);
        self::assertEqualsWithDelta($width * 9 / 16, $height, 1);
        self::assertSame($height, $embedHeight);
    }

    /**
     * Lets the page's events in until the responses of these URLs have come in, or 10 seconds have passed.
     * playwright-php hands the test a page's events only while it waits for the answer to a command, and a click that
     * leads to a download leaves no page to wait for, so its responses can come in after the click's answer
     *
     * @param array<string, ResponseInterface> $responses by URL, which the page's response listener fills
     */
    private static function awaitResponses(PageInterface $page, array &$responses, string ...$urls): void
    {
        $until = microtime(true) + 10;
        while ([] !== array_diff($urls, array_keys($responses)) && microtime(true) < $until) {
            $page->evaluate('() => true');
            usleep(50_000);
        }
    }
}
