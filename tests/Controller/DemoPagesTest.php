<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoPagesTest extends WebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function routes(): iterable
    {
        $pages = require dirname(__DIR__, 2).'/config/demo_pages.php';
        foreach (array_keys($pages) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('routes')]
    public function testEveryPageRendersWithRepositoryLinks(string $route): void
    {
        $client = static::createClient();
        $client->request('GET', $this->url($client, $route));

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('href="https://markup-carve.github.io/carve/"', $html);
        self::assertStringContainsString('href="https://github.com/markup-carve/symfony-carve"', $html);
        self::assertStringNotContainsString('class="exception', $html);
    }

    public function testTwigFilterRendersWithAndWithoutOptions(): void
    {
        $html = $this->page('twig_filter');

        self::assertStringContainsString('<h1>Welcome to Carve</h1>', $html);
        self::assertStringContainsString('<u>underline</u>', $html);
        self::assertMatchesRegularExpression('#id="filter-options"><p>\# Welcome to Carve</p>#', $html);
    }

    public function testTwigFunctionAndCarveFile(): void
    {
        $html = $this->page('twig_function');

        self::assertStringContainsString('<section id="welcome-to-carve">', $html);
        self::assertMatchesRegularExpression('#id="carve-file-output">\s*<section id="Release-checklist">#', $html);
        self::assertStringContainsString('<li>Publish a release</li>', $html);
    }

    public function testTwigToolsShowDocumentFormatAndLint(): void
    {
        $html = $this->page('twig_tools');

        self::assertStringContainsString('<th>meta(\'title\')</th><td>Shipping Carve 0.2</td>', $html);
        self::assertStringContainsString('<th>meta(\'tags\')</th><td>carve, symfony</td>', $html);
        self::assertStringContainsString('<a href="#Checklist">Checklist</a>', $html);
        self::assertStringContainsString("= A Markdown habit\n\n|= Item |= Qty |", $html);
        self::assertStringContainsString('|= Item       |= Qty |', $html);
        self::assertStringContainsString('<code>markdown-strong-double-star</code>', $html);
        self::assertStringContainsString('<code>block-opener-without-blank-line</code>', $html);
    }

    public function testServiceShowsRenderParseResultAndOptions(): void
    {
        $html = $this->page('service');

        self::assertStringContainsString('&lt;h1&gt;Welcome to Carve&lt;/h1&gt;', $html);
        self::assertMatchesRegularExpression('#id="service-tree"><code>document\n  heading\n    text#', $html);
        self::assertStringContainsString('2: What changed (#What-changed)', $html);
        self::assertStringContainsString('source, html, toc, frontmatter', $html);
        self::assertStringContainsString('&lt;h1 id=&quot;hello-world&quot;&gt;Hello &lt;strong&gt;world&lt;/strong&gt;&lt;/h1&gt;', $html);
    }

    public function testCarveToolsFormatLintAndImport(): void
    {
        $html = $this->page('tools');

        self::assertStringContainsString('<span id="is-formatted-before">false</span>', $html);
        self::assertStringContainsString('<span id="is-formatted-after">true</span>', $html);
        self::assertStringContainsString('<code>include-not-found</code>', $html);
        self::assertStringContainsString('= Imported page', $html);
        self::assertStringContainsString('Markdown uses *double asterisks* for strong and /underscores/ for emphasis.', $html);
        self::assertStringContainsString('= A Djot note', $html);
        self::assertStringContainsString('An /imported/ paragraph.', $html);
    }

    public function testMarkdownImportConvertsAGitHubFlavoredTable(): void
    {
        $html = $this->page('tools');

        self::assertStringContainsString("import(..., 'gfm')", $html);
        self::assertStringContainsString('|= Construct |={&gt;} Carve |', $html);
        self::assertStringContainsString('| {-Strike-} |', $html);
        self::assertStringContainsString('- [x] Convert the file', $html);
    }

    public function testConsoleRunsEveryBundleCommand(): void
    {
        $html = $this->page('console');

        foreach (['carve:render', 'carve:fmt', 'carve:lint', 'carve:convert'] as $command) {
            self::assertStringContainsString('$ php bin/console '.$command, $html);
        }
        self::assertStringContainsString('&lt;h1&gt;Release notes&lt;/h1&gt;', $html);
        self::assertStringContainsString('2 Carve problem(s) found.', $html);
        self::assertStringContainsString('content/broken.crv:5:1', $html);
        self::assertStringContainsString('= Migration notes', $html);
    }

    public function testIncludesExpandFilesReportWarningsAndUseAHostResolver(): void
    {
        $html = $this->page('includes');

        self::assertStringContainsString('<code>getIncludeRoot()</code>: <code>content</code>', $html);
        self::assertStringContainsString('<li>PHP 8.2 or newer</li>', $html);
        self::assertStringContainsString('<code class="language-js">const server', $html);
        self::assertStringContainsString('<td>Enterprise, annual</td>', $html);
        self::assertStringContainsString('<tr><td>Team</td><td>10</td></tr>', $html);
        self::assertStringNotContainsString('This intro stays out of the handbook', $html);
        self::assertStringContainsString('<code>parts/missing.crv</code>: missing', $html);
        self::assertStringContainsString('<code>include-unresolved</code> at broken.crv:5:1', $html);
        self::assertStringContainsString('You have <strong>3 pending reviews</strong>', $html);
        self::assertStringContainsString('Support is available Monday to Friday', $html);
        self::assertStringContainsString('Enterprise, annual | unlimited | on request', $html);
    }

    public function testOutputsAndProfiles(): void
    {
        $html = $this->page('outputs_profiles');

        self::assertMatchesRegularExpression('#id="output-text"><code>Release notes\n#', $html);
        self::assertMatchesRegularExpression('#id="output-markdown"><code>\# Release notes\n#', $html);
        self::assertStringContainsString('<span style="font-weight:bold">', $html);
        self::assertMatchesRegularExpression('#id="output-file-markdown"><code>\# Release notes#', $html);
        foreach (['full', 'article', 'comment', 'minimal'] as $profile) {
            self::assertStringContainsString('id="profile-'.$profile.'"', $html);
        }
        self::assertMatchesRegularExpression('#id="comment-profile"><p>\# Release notes</p>#', $html);
        self::assertMatchesRegularExpression('#id="twig-text-output"><code>Release notes#', $html);
        self::assertMatchesRegularExpression('#id="twig-markdown-output"><code>\# Release notes#', $html);
    }

    public function testRenderOptionsSymbolsLabelsAndCache(): void
    {
        $html = $this->page('options');

        self::assertStringContainsString('Carve 🪶 and Symfony ™.', $html);
        self::assertStringContainsString('&lt;section id=&quot;render-options&quot;&gt;', $html);
        self::assertStringContainsString('&lt;h1 id=&quot;Render-options&quot;&gt;', $html);
        self::assertStringContainsString('data-source-line=&quot;1&quot;', $html);
        self::assertStringContainsString('&quot;Smart quotes&quot; -- and dashes...', $html);
        self::assertStringContainsString('aria-label=&quot;Hinweis&quot;', $html);
        self::assertStringContainsString('Carve :carve: and Symfony :tm:.', $html);
        self::assertStringContainsString('<th>Entries in the pool</th><td>1</td>', $html);
        self::assertStringContainsString('<th>Both calls returned the same HTML</th><td>yes</td>', $html);
    }

    public function testRenderOptionsShowTheSoftBreakMode(): void
    {
        $html = $this->page('options');

        self::assertStringContainsString("A line break inside a paragraph\nfollows soft_break_mode.", $html);
        self::assertStringContainsString('A line break inside a paragraph<br>', $html);
    }

    public function testExtensionsRenderTabsAndWikiLinks(): void
    {
        $html = $this->page('extensions');

        self::assertMatchesRegularExpression('#id="extensions-on">.*?<div class="tabs" role="group"#s', $html);
        self::assertStringContainsString('<a href="https://github.com/markup-carve/symfony-carve/wiki/getting-started" class="wikilink" data-wikilink="Getting Started">Getting Started</a>', $html);
        self::assertMatchesRegularExpression('#id="extensions-static">.*?<section class="tabs-panel">#s', $html);
        self::assertMatchesRegularExpression('#id="extensions-off">.*?<span class="ext-wiki">Getting Started</span>#s', $html);
        self::assertStringContainsString('<h2>Linking <a href="#Linking" class="permalink" aria-label="Permalink">#</a></h2>', $html);
        self::assertStringContainsString('<a href="https://markup-carve.github.io/carve/">https://markup-carve.github.io/carve/</a>', $html);
    }

    public function testFormShowsTheValidatedEditor(): void
    {
        $html = $this->page('form');

        self::assertStringContainsString('#[ValidCarve(strict: true, lint: true, maxLength: 5000)]', $html);
        self::assertStringContainsString('= Welcome to Carve', $html);
    }

    public function testFormRendersAValidSubmission(): void
    {
        $client = static::createClient();
        $client->request('POST', $this->url($client, 'form'), ['article' => [
            'title' => 'Hello',
            'body' => "= Hello\n\nSome *bold* text.\n",
            'comment' => 'A /short/ comment.',
        ]]);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Preview: Hello', $html);
        self::assertStringContainsString('<p>A <em>short</em> comment.</p>', $html);
    }

    public function testFormRejectsBlankFields(): void
    {
        $client = static::createClient();
        $client->request('POST', $this->url($client, 'form'), ['article' => ['title' => '', 'body' => '']]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('This value should not be blank', (string) $client->getResponse()->getContent());
    }

    public function testFormRejectsLintFindingsInTheBody(): void
    {
        $client = static::createClient();
        $client->request('POST', $this->url($client, 'form'), ['article' => ['title' => 'T', 'body' => "Some **bold** text.\n"]]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('is literal text in Carve', (string) $client->getResponse()->getContent());
    }

    public function testFormRejectsAHeadingInTheComment(): void
    {
        $client = static::createClient();
        $client->request('POST', $this->url($client, 'form'), ['article' => [
            'title' => 'T',
            'body' => "Fine.\n",
            'comment' => "= A heading\n",
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('markup is not allowed by the comment preset', (string) $client->getResponse()->getContent());
    }

    public function testFormRejectsAnOverlongComment(): void
    {
        $client = static::createClient();
        $client->request('POST', $this->url($client, 'form'), ['article' => [
            'title' => 'T',
            'body' => "Fine.\n",
            'comment' => str_repeat('a', 501),
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('must not exceed 500 characters', (string) $client->getResponse()->getContent());
    }

    public function testSafeOutputEscapesRawHtmlAndBlanksDeniedUrls(): void
    {
        $html = $this->page('safe_mode');

        self::assertStringNotContainsString('<script>document.location', $html);
        self::assertStringContainsString('&lt;a href=&quot;&quot;&gt;script link&lt;/a&gt;', $html);
        self::assertStringContainsString('&lt;a href=&quot;mailto:team@example.com&quot;&gt;mail link', $html);
        self::assertMatchesRegularExpression('#id="safe-https-only"><code>.*?&lt;a href=&quot;&quot;&gt;mail link#s', $html);
        self::assertMatchesRegularExpression('#id="safe-trusted"><code>.*?\n&lt;script&gt;document.location#s', $html);
    }

    public function testSyntaxGallery(): void
    {
        $html = $this->page('syntax');

        self::assertStringContainsString('Inline literal', $html);
        self::assertStringContainsString('<dl>', $html);
        self::assertStringContainsString('doc-noteref', $html);
        self::assertStringContainsString('<li>first</li>', $html);
        self::assertStringContainsString('<th scope="col">Lang</th>', $html);
        self::assertStringContainsString('alt="Demo diagram"', $html);
        self::assertStringContainsString('<p>= This stays literal text, not a heading.</p>', $html);
        self::assertStringContainsString('<td>Pear, green</td>', $html);
        self::assertMatchesRegularExpression('#<li>Install the bundle\s*<blockquote>#', $html);
        self::assertStringContainsString('<th scope="col" style="text-align: right;">Qty</th>', $html);
        self::assertStringContainsString('<td style="text-align: center;">fresh</td>', $html);
        self::assertStringContainsString("<p>A paragraph keeps going\nacross the comment line.</p>", $html);
        self::assertStringContainsString('<td rowspan="2">Apple</td>', $html);
        self::assertStringContainsString('<td style="text-align: right;">0.90 per kilo</td>', $html);
        self::assertStringContainsString('<td colspan="3">Three varieties in stock</td>', $html);
        self::assertStringContainsString('<mark>highlight</mark>', $html);
        self::assertStringContainsString('<s>strikethrough</s>', $html);
    }

    public function testDiagramsEmitHydrationMarkupForEveryPreset(): void
    {
        $html = $this->page('diagrams');

        foreach (['mermaid', 'graphviz', 'd2', 'plantuml', 'vega_lite', 'wavedrom', 'chart', 'abc'] as $preset) {
            self::assertStringContainsString('<pre class="'.$preset.'" role="img"', $html);
        }
        self::assertStringContainsString('&lt;pre&gt;&lt;code class=&quot;language-mermaid&quot;&gt;', $html);
        self::assertStringContainsString("theme: 'neutral'", $html);
    }

    public function testCodeLinesPassLineOptionsToPre(): void
    {
        $html = $this->page('code_lines');

        self::assertStringContainsString('<pre title="src/Invoice.php" class="line-numbers" data-line-start="40" data-highlight="3,6-7">', $html);
        self::assertStringContainsString('<pre data-highlight="2"><code class="language-js">', $html);
        self::assertStringContainsString('enhanceCodeBlocks(', $html);
    }

    private function page(string $route): string
    {
        $client = static::createClient();
        $client->request('GET', $this->url($client, $route));
        self::assertResponseIsSuccessful();

        return (string) $client->getResponse()->getContent();
    }

    private function url(KernelBrowser $client, string $route): string
    {
        return $client->getContainer()->get('router')->generate($route);
    }
}
