<?php

// SPDX-FileCopyrightText: 2004-2023 Ryan Parman, Sam Sneddon, Ryan McCue
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace SimplePie\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use SimplePie\Cache;
use SimplePie\File;
use SimplePie\SimplePie;
use SimplePie\Tests\Fixtures\Cache\LegacyCacheMock;
use SimplePie\Tests\Fixtures\Cache\NewCacheMock;
use SimplePie\Tests\Fixtures\Exception\SuccessException;
use SimplePie\Tests\Fixtures\FileMock;
use SimplePie\Tests\Fixtures\FileWithRedirectMock;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class SimplePieTest extends TestCase
{
    use ExpectPHPException;

    public function testNamespacedClassExists()
    {
        $this->assertTrue(class_exists('SimplePie\SimplePie'));
    }

    public function testClassExists()
    {
        $this->assertTrue(class_exists(SimplePie::class));
    }

    /**
     * Run a test using a sprintf template and data
     *
     * @param string $template
     */
    private function createFeedWithTemplate(string $template, $data): SimplePie
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $xml = vsprintf($template, $data);
        $feed = new SimplePie();
        $feed->set_raw_data($xml);
        $feed->enable_cache(false);
        $feed->init();

        return $feed;
    }

    /**
     * @return array<array{string, string}>
     */
    public static function titleDataProvider(): array
    {
        return [
            ['Feed Title', 'Feed Title'],

            // RSS Profile tests
            ['AT&amp;T', 'AT&amp;T'],
            ['AT&#x26;T', 'AT&amp;T'],
            ["Bill &amp; Ted's Excellent Adventure", "Bill &amp; Ted's Excellent Adventure"],
            ["Bill &#x26; Ted's Excellent Adventure", "Bill &amp; Ted's Excellent Adventure"],
            ['The &amp; entity', 'The &amp; entity'],
            ['The &#x26; entity', 'The &amp; entity'],
            ['The &amp;amp; entity', 'The &amp;amp; entity'],
            ['The &#x26;amp; entity', 'The &amp;amp; entity'],
            ['I &lt;3 Phil Ringnalda', 'I &lt;3 Phil Ringnalda'],
            ['I &#x3C;3 Phil Ringnalda', 'I &lt;3 Phil Ringnalda'],
            ['A &lt; B', 'A &lt; B'],
            ['A &#x3C; B', 'A &lt; B'],
            ['A&lt;B', 'A&lt;B'],
            ['A&#x3C;B', 'A&lt;B'],
            ["Nice &lt;gorilla&gt; what's he weigh?", "Nice &lt;gorilla&gt; what's he weigh?"],
            ["Nice &#x3C;gorilla&gt; what's he weigh?", "Nice &lt;gorilla&gt; what's he weigh?"],
        ];
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20(string $title, string $expected): void
    {
        $data =
'<rss version="2.0">
	<channel>
		<title>%s</title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithDC10(string $title, string $expected): void
    {
        $data =
'<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>%s</dc:title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithDC11(string $title, string $expected): void
    {
        $data =
'<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>%s</dc:title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithAtom03(string $title, string $expected): void
    {
        $data =
'<rss version="2.0" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>%s</a:title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithAtom10(string $title, string $expected): void
    {
        $data =
'<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>%s</a:title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * Based on a test from old bug 18
     *
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithImageTitle(string $title, string $expected): void
    {
        $data =
'<rss version="2.0">
	<channel>
		<title>%s</title>
		<image>
			<title>Image title</title>
		</image>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    /**
     * Based on a test from old bug 18
     *
     * @dataProvider titleDataProvider
     */
    public function testTitleRSS20WithImageTitleReversed(string $title, string $expected): void
    {
        $data =
'<rss version="2.0">
	<channel>
		<image>
			<title>Image title</title>
		</image>
		<title>%s</title>
	</channel>
</rss>';
        $feed = $this->createFeedWithTemplate($data, $title);
        $this->assertSame($expected, $feed->get_title());
    }

    public function testItemWithEmptyContent()
    {
        $data =
'<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	<channel>
		<item>
			<description>%s</description>
			<content:encoded><![CDATA[ <script> ]]></content:encoded>
		</item>
	</channel>
</rss>';
        $content = 'item description';
        $feed = $this->createFeedWithTemplate($data, $content);
        $item = $feed->get_item();
        $this->assertSame($content, $item->get_content());
    }

    public function testSetPsr16Cache()
    {
        $psr16 = $this->createMock(CacheInterface::class);
        $psr16->expects($this->once())->method('get')->willReturn([]);
        $psr16->expects($this->once())->method('set')->willReturn(true);

        $feed = new SimplePie();
        $feed->set_cache($psr16);
        $feed->get_registry()->register(File::class, FileMock::class);
        $feed->set_feed_url('http://example.com/feed/');

        $feed->init();
    }

    public function testLegacyCallOfSetCacheClass()
    {
        $feed = new SimplePie();
        $this->expectDeprecation();
        $feed->set_cache_class(LegacyCacheMock::class);
        $feed->get_registry()->register(File::class, FileMock::class);
        $feed->set_feed_url('http://example.com/feed/');

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->expectException(SuccessException::class);
        } else {
            // PHP 8.0 will throw a `TypeError` for trying to call a non-static method statically.
            // This is no longer supported in PHP, so there is just no way to continue to provide BC
            // for the old non-static cache methods.
            $this->expectError();
        }

        $feed->init();
    }

    public function testDirectOverrideNew()
    {
        $this->expectException(SuccessException::class);

        $feed = new SimplePie();
        $feed->get_registry()->register(Cache::class, NewCacheMock::class);
        $feed->get_registry()->register(File::class, FileMock::class);
        $feed->set_feed_url('http://example.com/feed/');

        $feed->init();
    }

    public function testDirectOverrideLegacy()
    {
        $feed = new SimplePie();
        $feed->get_registry()->register(File::class, FileWithRedirectMock::class);
        $feed->enable_cache(false);
        $feed->set_feed_url('http://example.com/feed/');

        $feed->init();

        $this->assertSame('https://example.com/feed/2019-10-07', $feed->subscribe_url());
        $this->assertSame('https://example.com/feed/', $feed->subscribe_url(true));
    }

    /**
     * @return array<array{string, string}>
     */
    public function getCopyrightDataProvider(): array
    {
        return [
            'Test Atom 0.3 DC 1.0' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:rights>Example Copyright Information</dc:rights>
</feed>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test Atom 0.3 DC 1.1' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:rights>Example Copyright Information</dc:rights>
</feed>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test Atom 1.0 DC 1.0' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:rights>Example Copyright Information</dc:rights>
</feed>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test Atom 1.0 DC 1.1' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:rights>Example Copyright Information</dc:rights>
</feed>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test Atom 1.0 Rights' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<rights>Example Copyright Information</rights>
</feed>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.90 Atom 1.0 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.90 DC 1.0 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.90 DC 1.1 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Rights' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Netscape DC 1.0 Rights' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Netscape DC 1.1 Rights' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Netscape Copyright' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<copyright>Example Copyright Information</copyright>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Rights' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Userland DC 1.0 Rights' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Userland DC 1.1 Rights' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.91-Userland Copyright' => [
<<<EOT
<rss version="0.91">
	<channel>
		<copyright>Example Copyright Information</copyright>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.92 Atom 1.0 Rights' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.92 DC 1.0 Rights' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.92 DC 1.1 Rights' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 0.92 Copyright' => [
<<<EOT
<rss version="0.92">
	<channel>
		<copyright>Example Copyright Information</copyright>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 1.0 Atom 1.0 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 1.0 DC 1.0 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 1.0 DC 1.1 Rights' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rdf:RDF>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 2.0 Atom 1.0 Rights' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:rights>Example Copyright Information</a:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 2.0 DC 1.0 Rights' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 2.0 DC 1.1 Rights' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:rights>Example Copyright Information</dc:rights>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
            'Test RSS 2.0 Copyright' => [
<<<EOT
<rss version="2.0">
	<channel>
		<copyright>Example Copyright Information</copyright>
	</channel>
</rss>
EOT
                ,
                'Example Copyright Information',
            ],
        ];
    }

    /**
     * @dataProvider getCopyrightDataProvider
     */
    public function test_get_copyright(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_copyright());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getDescriptionDataProvider(): array
    {
        return [
            'Test Atom 0.3 DC 1.0 Description' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:description>Feed Description</dc:description>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test Atom 0.3 DC 1.1 Description' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:description>Feed Description</dc:description>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test Atom 0.3 Tagline' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#">
	<tagline>Feed Description</tagline>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test Atom 1.0 DC 1.0 Description' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:description>Feed Description</dc:description>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test Atom 1.0 DC 1.1 Description' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:description>Feed Description</dc:description>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test Atom 1.0 Subtitle' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<subtitle>Feed Description</subtitle>
</feed>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.90 Atom 0.3 Tagline' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.90 Atom 1.0 Subtitle' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.90 DC 1.0 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.90 DC 1.1 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.90 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<channel>
		<description>Feed Description</description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Netscape Atom 0.3 Tagline' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Subtitle' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Netscape DC 1.0 Description' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Netscape DC 1.1 Description' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Netscape Description' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<description>Feed Description</description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Userland Atom 0.3 Tagline' => [
<<<EOT
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Subtitle' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Userland DC 1.0 Description' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Userland DC 1.1 Description' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.91-Userland Description' => [
<<<EOT
<rss version="0.91">
	<channel>
		<description>Feed Description</description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.92 Atom 0.3 Tagline' => [
<<<EOT
<rss version="0.92" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.92 Atom 1.0 Subtitle' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.92 DC 1.0 Description' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.92 DC 1.1 Description' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 0.92 Description' => [
<<<EOT
<rss version="0.92">
	<channel>
		<description>Feed Description</description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 1.0 Atom 0.3 Tagline' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 1.0 Atom 1.0 Subtitle' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 1.0 DC 1.0 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 1.0 DC 1.1 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 1.0 Description' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<channel>
		<description>Feed Description</description>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 20 Atom 0.3 Tagline' => [
<<<EOT
<rss version="2.0" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:tagline>Feed Description</a:tagline>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 20 Atom 1.0 Subtitle' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:subtitle>Feed Description</a:subtitle>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 20 DC 1.0 Description' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 20 DC 1.1 Description' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:description>Feed Description</dc:description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
            'Test RSS 20 Description' => [
<<<EOT
<rss version="2.0">
	<channel>
		<description>Feed Description</description>
	</channel>
</rss>
EOT
                ,
                'Feed Description',
            ],
        ];
    }

    /**
     * @dataProvider getDescriptionDataProvider
     */
    public function test_get_description(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_description());
    }

    /**
     * @return array<array{string, int|null}>
     */
    public function getImageHeightDataProvider(): array
    {
        return [
            'Test Atom 1.0 Icon Default' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<icon>http://example.com/</icon>
</feed>
EOT				,
                null,
            ],
            'Test Atom 1.0 Logo Default' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<logo>http://example.com/</logo>
</feed>
EOT				,
                null,
            ],
            'Test RSS 0.90 Atom 1.0 Icon Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT				,
                null,
            ],
            'Test RSS 0.90 Atom 1.0 Logo Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT				,
                null,
            ],
            'Test RSS 0.90 URL Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT				,
                null,
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Icon Default' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Logo Default' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Netscape Height' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<height>100</height>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 0.91-Netscape URL Default' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                31,
            ],
            'Test RSS 0.91-Userland Atom 1.0 Icon Default' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Userland Atom 1.0 Logo Default' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Userland Height' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<height>100</height>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 0.91-Userland URL Default' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                31,
            ],
            'Test RSS 0.92 Atom 1.0 Icon Default' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.92 Atom 1.0 Logo Default' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.92 Height' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<height>100</height>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 0.92 URL Default' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                31,
            ],
            'Test RSS 1.0 Atom 1.0 Icon Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 1.0 Atom 1.0 Logo Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 1.0 URL Default' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 2.0 Atom 1.0 Icon Default' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 2.0 Atom 1.0 Logo Default' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 2.0 Height' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<height>100</height>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 2.0 URL Default' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                31,
            ],
        ];
    }

    /**
     * @dataProvider getImageHeightDataProvider
     */
    public function test_get_image_height(string $data, ?int $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_image_height());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getImageLinkDataProvider(): array
    {
        return [
            'Test RSS 0.90 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<image>
		<link>http://example.com/</link>
	</image>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Link' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<link>http://example.com/</link>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Link' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<link>http://example.com/</link>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Link' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<link>http://example.com/</link>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<image>
		<link>http://example.com/</link>
	</image>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Link' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<link>http://example.com/</link>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
        ];
    }

    /**
     * @dataProvider getImageLinkDataProvider
     */
    public function test_get_image_link(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_image_link());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getImageTitleDataProvider(): array
    {
        return [
            'Test RSS 0.90 DC 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<image>
		<dc:title>Image Title</dc:title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.90 DC 1.1 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<image>
		<dc:title>Image Title</dc:title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.90 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<image>
		<title>Image Title</title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Netscape DC 1.0 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Netscape DC 1.1 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Netscape Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<title>Image Title</title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Userland DC 1.0 Title' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Userland DC 1.1 Title' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.91-Userland Title' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<title>Image Title</title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.92 DC 1.0 Title' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.92 DC 1.1 Title' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 0.92 Title' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<title>Image Title</title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 1.0 DC 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<image>
		<dc:title>Image Title</dc:title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 1.0 DC 1.1 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<image>
		<dc:title>Image Title</dc:title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<image>
		<title>Image Title</title>
	</image>
</rdf:RDF>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 2.0 DC 1.0 Title' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 2.0 DC 1.1 Title' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<image>
			<dc:title>Image Title</dc:title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
            'Test RSS 2.0 Title' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<title>Image Title</title>
		</image>
	</channel>
</rss>
EOT
                ,
                'Image Title',
            ],
        ];
    }

    /**
     * @dataProvider getImageTitleDataProvider
     */
    public function test_get_image_title(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_image_title());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getImageUrlDataProvider(): array
    {
        return [
            'Test Atom 1.0 Icon' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<icon>http://example.com/</icon>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test Atom 1.0 Logo' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<logo>http://example.com/</logo>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 Atom 1.0 Icon' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 Atom 1.0 Logo' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 URL' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Icon' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Logo' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape URL' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Icon' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Logo' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland URL' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Atom 1.0 Icon' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Atom 1.0 Logo' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 URL' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Atom 1.0 Icon' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Atom 1.0 Logo' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 URL' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Atom 1.0 Icon' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Atom 1.0 Logo' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 URL' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
        ];
    }

    /**
     * @dataProvider getImageUrlDataProvider
     */
    public function test_get_image_url(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_image_url());
    }

    /**
     * @return array<array{string, int|null}>
     */
    public function getImageWidthDataProvider(): array
    {
        return [
            'Test Atom 1.0 Icon Default' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<icon>http://example.com/</icon>
</feed>
EOT
                ,
                null,
            ],
            'Test Atom 1.0 Logo Default' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<logo>http://example.com/</logo>
</feed>
EOT
                ,
                null,
            ],
            'Test RSS 0.90 Atom 1.0 Icon' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 0.90 Atom 1.0 Logo' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 0.90' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Icon' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Logo' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Netscape' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                88,
            ],
            'Test RSS 0.91-Netscape Width' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<image>
			<width>100</width>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 0.91-Userland Atom 1.0 Icon' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Userland Atom 1.0 Logo' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.91-Userland' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                88,
            ],
            'Test RSS 0.91-Userland Width' => [
<<<EOT
<rss version="0.91">
	<channel>
		<image>
			<width>100</width>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 0.92 Atom 1.0 Icon' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.92 Atom 1.0 Logo' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 0.92' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                88,
            ],
            'Test RSS 0.92 Width' => [
<<<EOT
<rss version="0.92">
	<channel>
		<image>
			<width>100</width>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
            'Test RSS 1.0 Atom 1.0 Icon' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 1.0 Atom 1.0 Logo' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 1.0' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<image>
		<url>http://example.com/</url>
	</image>
</rdf:RDF>
EOT
                ,
                null,
            ],
            'Test RSS 2.0 Atom 1.0 Icon' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:icon>http://example.com/</a:icon>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 2.0 Atom 1.0 Logo' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:logo>http://example.com/</a:logo>
	</channel>
</rss>
EOT
                ,
                null,
            ],
            'Test RSS 2.0' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<url>http://example.com/</url>
		</image>
	</channel>
</rss>
EOT
                ,
                88,
            ],
            'Test RSS 2.0 Width' => [
<<<EOT
<rss version="2.0">
	<channel>
		<image>
			<width>100</width>
		</image>
	</channel>
</rss>
EOT
                ,
                100,
            ],
        ];
    }

    /**
     * @dataProvider getImageWidthDataProvider
     */
    public function test_get_image_width(string $data, ?int $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_image_width());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getLanguageDataProvider(): array
    {
        return [
            'Test Atom 0.3 DC 1.0 Language' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:language>en-GB</dc:language>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test Atom 0.3 DC 1.1 Language' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:language>en-GB</dc:language>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test Atom 0.3 xmllang' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xml:lang="en-GB">
	<title>Feed Title</title>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test Atom 1.0 DC 1.0 Language' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:language>en-GB</dc:language>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test Atom 1.0 DC 1.1 Language' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:language>en-GB</dc:language>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test Atom 1.0 xmllang' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en-GB">
	<title>Feed Title</title>
</feed>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.90 DC 1.0 Language' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rdf:RDF>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.90 DC 1.1 Language' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rdf:RDF>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Netscape DC 1.0 Language' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Netscape DC 1.1 Language' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Netscape Language' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<language>en-GB</language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Userland DC 1.0 Language' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Userland DC 1.1 Language' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.91-Userland Language' => [
<<<EOT
<rss version="0.91">
	<channel>
		<language>en-GB</language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.92 DC 1.0 Language' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.92 DC 1.1 Language' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 0.92 Language' => [
<<<EOT
<rss version="0.92">
	<channel>
		<language>en-GB</language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 1.0 DC 1.0 Language' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rdf:RDF>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 1.0 DC 1.1 Language' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rdf:RDF>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 2.0 DC 1.0 Language' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 2.0 DC 1.1 Language' => [
<<<EOT
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:language>en-GB</dc:language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
            'Test RSS 2.0 Language' => [
<<<EOT
<rss version="2.0">
	<channel>
		<language>en-GB</language>
	</channel>
</rss>
EOT
                ,
                'en-GB',
            ],
        ];
    }

    /**
     * @dataProvider getLanguageDataProvider
     */
    public function test_get_language(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_language());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getLinkDataProvider(): array
    {
        return [
            'Test Atom 0.3 Link' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#">
	<link href="http://example.com/"/>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test Atom 0.3 Link Alternate' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#">
	<link href="http://example.com/" rel="alternate"/>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test Atom 1.0 Link' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<link href="http://example.com/"/>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test Atom 1.0 Link Absolute IRI' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<link href="http://example.com/" rel="http://www.iana.org/assignments/relation/alternate"/>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test Atom 1.0 Link Relative IRI' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<link href="http://example.com/" rel="alternate"/>
</feed>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 Atom 0.3 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 Atom 1.0 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.90 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Atom 0.3 Link' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Link' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Netscape Link' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Atom 0.3 Link' => [
<<<EOT
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Link' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.91-Userland Link' => [
<<<EOT
<rss version="0.91">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Atom 0.3 Link' => [
<<<EOT
<rss version="0.92" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Atom 1.0 Link' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 0.92 Link' => [
<<<EOT
<rss version="0.92">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Atom 0.3 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Atom 1.0 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 1.0 Link' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rdf:RDF>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Atom 0.3 Link' => [
<<<EOT
<rss version="2.0" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Atom 1.0 Link' => [
<<<EOT
<rss version="2.0" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:link href="http://example.com/"/>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
            'Test RSS 2.0 Link' => [
<<<EOT
<rss version="2.0">
	<channel>
		<link>http://example.com/</link>
	</channel>
</rss>
EOT
                ,
                'http://example.com/',
            ],
        ];
    }

    /**
     * @dataProvider getLinkDataProvider
     */
    public function test_get_link(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_link());
    }

    /**
     * @return array<array{string, string}>
     */
    public function getTitleDataProvider(): array
    {
        return [
            'Test Atom 0.3 DC 1.0 Title' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:title>Feed Title</dc:title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Atom 0.3 DC 1.1 Title' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:title>Feed Title</dc:title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Atom 0.3 Title' => [
<<<EOT
<feed version="0.3" xmlns="http://purl.org/atom/ns#">
	<title>Feed Title</title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Atom 1.0 DC 1.0 Title' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<dc:title>Feed Title</dc:title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Atom 1.0 DC 1.1 Title' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<dc:title>Feed Title</dc:title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Atom 1.0 Title' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Feed Title</title>
</feed>
EOT
                ,
                'Feed Title',
            ],
            'Test Bug 16 Test 0' => [
<<<EOT
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN"
"http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Feed with DOCTYPE</title>
	</channel>
</rss>
EOT
                ,
                'Feed with DOCTYPE',
            ],
            'Test Bug 174 Test 0' => [
<<<EOT
<?xml version = "1.0" encoding = "UTF-8" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Spaces in prolog</title>
</feed>
EOT
                ,
                'Spaces in prolog',
            ],
            'Test Bug 20 Test 0' => [
<<<EOT
<a:feed xmlns:a="http://www.w3.org/2005/Atom" xmlns="http://www.w3.org/1999/xhtml">
	<a:title>Non-default namespace</a:title>
</a:feed>
EOT
                ,
                'Non-default namespace',
            ],
            'Test Bug 20 Test 1' => [
<<<EOT
<a:feed xmlns:a="http://www.w3.org/2005/Atom" xmlns="http://www.w3.org/1999/xhtml">
	<a:title type="xhtml"><div>Non-default namespace</div></a:title>
</a:feed>
EOT
                ,
                'Non-default namespace',
            ],
            'Test Bug 20 Test 2' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:h="http://www.w3.org/1999/xhtml">
	<title type="xhtml"><h:div>Non-default namespace</h:div></title>
</feed>
EOT
                ,
                'Non-default namespace',
            ],
            'Test Bug 272 Test 0' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Ampersand: <![CDATA[&]]></title>
</feed>
EOT
                ,
                'Ampersand: &amp;',
            ],
            'Test Bug 272 Test 1' => [
<<<EOT
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><![CDATA[&]]>: Ampersand</title>
</feed>
EOT
                ,
                '&amp;: Ampersand',
            ],
            'Test RSS 0.90 Atom 0.3 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.90 Atom 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.90 DC 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.90 DC 1.1 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.90 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://my.netscape.com/rdf/simple/0.9/">
	<channel>
		<title>Feed Title</title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Netscape Atom 0.3 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Netscape Atom 1.0 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Netscape DC 1.0 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Netscape DC 1.1 Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Netscape Title' => [
<<<EOT
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Feed Title</title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Userland Atom 0.3 Title' => [
<<<EOT
<rss version="0.91" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Userland Atom 1.0 Title' => [
<<<EOT
<rss version="0.91" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Userland DC 1.0 Title' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Userland DC 1.1 Title' => [
<<<EOT
<rss version="0.91" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.91-Userland Title' => [
<<<EOT
<rss version="0.91">
	<channel>
		<title>Feed Title</title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.92 Atom 0.3 Title' => [
<<<EOT
<rss version="0.92" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.92 Atom 1.0 Title' => [
<<<EOT
<rss version="0.92" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.92 DC 1.0 Title' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.92 DC 1.1 Title' => [
<<<EOT
<rss version="0.92" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 0.92 Title' => [
<<<EOT
<rss version="0.92">
	<channel>
		<title>Feed Title</title>
	</channel>
</rss>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 1.0 Atom 0.3 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://purl.org/atom/ns#">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 1.0 Atom 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:a="http://www.w3.org/2005/Atom">
	<channel>
		<a:title>Feed Title</a:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 1.0 DC 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.0/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 1.0 DC 1.1 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<dc:title>Feed Title</dc:title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
            'Test RSS 1.0 Title' => [
<<<EOT
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/">
	<channel>
		<title>Feed Title</title>
	</channel>
</rdf:RDF>
EOT
                ,
                'Feed Title',
            ],
        ];
    }

    /**
     * @dataProvider getTitleDataProvider
     */
    public function test_get_title(string $data, string $expected): void
    {
        $feed = new SimplePie();
        $feed->set_raw_data($data);
        $feed->enable_cache(false);
        $feed->init();

        $this->assertSame($expected, $feed->get_title());
    }
}
