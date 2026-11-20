<?php

declare(strict_types=1);

namespace App\Controller;

use App\Carve\ConfigIncludeResolver;
use App\Entity\Article;
use App\Form\ArticleType;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Node;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\Command\ConvertCommand;
use MarkupCarve\SymfonyCarve\Command\FormatCommand;
use MarkupCarve\SymfonyCarve\Command\LintCommand;
use MarkupCarve\SymfonyCarve\Command\RenderCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    /** Syntax gallery: label => file below content/syntax. */
    private const SYNTAX = [
        'Headings' => 'headings',
        'Emphasis' => 'emphasis',
        'Superscript & subscript' => 'sup-sub',
        'Lists' => 'lists',
        'Task list' => 'task-list',
        'Tight vs loose lists' => 'tight-loose',
        'Definition list' => 'definition-list',
        'Blockquote' => 'blockquote',
        'Link & image' => 'link-image',
        'Table' => 'table',
        'Table alignment' => 'table-alignment',
        'Table spans and continuation rows' => 'table-spans',
        'Admonition' => 'admonition',
        'Footnotes' => 'footnotes',
        'Smart typography' => 'smart-typography',
        'Comment lines' => 'comment-lines',
        'Comment line in a list' => 'comment-in-list',
        'Escaped block markers' => 'escaped-markers',
        'Blocks inside list items' => 'list-item-blocks',
        'CSV and TSV tables' => 'csv-tsv',
    ];

    private const DIAGRAMS = [
        'Mermaid' => [
            'file' => 'mermaid',
            'renderer' => 'mermaid.js (CDN, client-side)',
            'note' => 'Rendered live by mermaid.js. The library loads from a CDN and draws entirely in the browser.',
        ],
        'Graphviz' => [
            'file' => 'graphviz',
            'renderer' => '@viz-js/viz WebAssembly (CDN, offline after load)',
            'note' => 'The dot source is rendered to SVG fully in-browser by the @viz-js/viz WebAssembly build. No server call.',
        ],
        'D2' => [
            'file' => 'd2',
            'renderer' => 'Kroki server (public kroki.io, needs network)',
            'note' => 'D2 has no small in-browser build, so the source is deflate-encoded and drawn through the public Kroki server. If the network is blocked the source stays visible.',
        ],
        'PlantUML' => [
            'file' => 'plantuml',
            'renderer' => 'plantuml.com server (~h hex encoding, needs network)',
            'note' => 'The source is hex-encoded and drawn by the public PlantUML server via its ~h scheme. If the network is blocked the source stays visible.',
        ],
        'Vega-Lite' => [
            'file' => 'vega-lite',
            'renderer' => 'vega + vega-lite + vega-embed (CDN, client-side)',
            'note' => 'A Vega-Lite JSON spec drawn by vega-embed. The fence word is vega-lite (hyphen); the config enum spells it vega_lite.',
        ],
        'WaveDrom' => [
            'file' => 'wavedrom',
            'renderer' => 'wavedrom + default skin (CDN, client-side)',
            'note' => 'A WaveDrom JSON timing spec, drawn to SVG by the wavedrom library.',
        ],
        'Chart.js' => [
            'file' => 'chart',
            'renderer' => 'chart.js (CDN, client-side)',
            'note' => 'A Chart.js JSON config, drawn onto a <canvas> by chart.js.',
        ],
        'ABC' => [
            'file' => 'abc',
            'renderer' => 'abcjs (CDN, client-side)',
            'note' => 'ABC music notation rendered to a score (SVG) by abcjs.',
        ],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('demo/index.html.twig');
    }

    #[Route('/twig-filter', name: 'twig_filter')]
    public function twigFilter(): Response
    {
        return $this->render('demo/twig_filter.html.twig', [
            'source' => $this->source('samples/welcome.crv'),
        ]);
    }

    #[Route('/twig-function', name: 'twig_function')]
    public function twigFunction(): Response
    {
        return $this->render('demo/twig_function.html.twig', [
            'source' => $this->source('samples/welcome.crv'),
            'file_source' => $this->source('parts/checklist.crv'),
        ]);
    }

    #[Route('/twig-tools', name: 'twig_tools')]
    public function twigTools(): Response
    {
        return $this->render('demo/twig_tools.html.twig', [
            'article' => $this->source('samples/article.crv'),
            'unformatted' => $this->source('samples/unformatted.crv'),
            'lint_source' => $this->source('samples/lint-findings.crv'),
        ]);
    }

    #[Route('/service', name: 'service')]
    public function service(CarveRenderer $carve): Response
    {
        $source = $this->source('samples/welcome.crv');
        $article = $this->source('samples/article.crv');
        $result = $carve->renderResult($article);

        return $this->render('demo/service.html.twig', [
            'source' => $source,
            'html' => $carve->render($source),
            'article' => $article,
            'result' => $result,
            'tree' => $this->outline($carve->parse($source)),
            'static_html' => $carve->withOptions(['sections' => false, 'heading_ids' => 'lower'])->render("= Hello *world*\n"),
        ]);
    }

    #[Route('/tools', name: 'tools')]
    public function tools(CarveTools $tools): Response
    {
        $unformatted = $this->source('samples/unformatted.crv');
        $lintSource = $this->source('samples/lint-findings.crv');
        $imports = [];
        foreach (['html' => 'import/page.html', 'gfm' => 'import/legacy.md', 'djot' => 'import/note.djot'] as $from => $file) {
            $input = $this->source($file);
            $imports[$from] = ['input' => $input, 'carve' => $tools->import($input, $from)];
        }

        return $this->render('demo/tools.html.twig', [
            'unformatted' => $unformatted,
            'formatted' => $tools->format($unformatted),
            'aligned' => $tools->format($unformatted, alignTables: true),
            'is_formatted_before' => $tools->isFormatted($unformatted),
            'is_formatted_after' => $tools->isFormatted($tools->format($unformatted)),
            'lint_source' => $lintSource,
            'findings' => $tools->lint($lintSource),
            'include_findings' => $tools->lint($this->source('broken.crv'), $this->contentDir(), 'broken.crv'),
            'imports' => $imports,
            'from_html' => $tools->fromHtml('<h2>Hi</h2><p>An <em>imported</em> paragraph.</p>'),
            'from_markdown' => $tools->fromMarkdown("## Hi\n\nA **Markdown** paragraph.\n"),
            'from_djot' => $tools->fromDjot("## Hi\n\nA _Djot_ paragraph.\n"),
        ]);
    }

    #[Route('/console', name: 'console')]
    public function console(CarveRenderer $carve, CarveTools $tools): Response
    {
        $runs = [
            'carve:render content/samples/release-notes.crv' => [new RenderCommand($carve), ['path' => 'content/samples/release-notes.crv']],
            'carve:render content/samples/release-notes.crv --format=text' => [new RenderCommand($carve), ['path' => 'content/samples/release-notes.crv', '--format' => 'text']],
            'carve:render content/samples/release-notes.crv --format=markdown --preset=comment' => [new RenderCommand($carve), ['path' => 'content/samples/release-notes.crv', '--format' => 'markdown', '--preset' => 'comment']],
            'carve:fmt content/samples/unformatted.crv --align-tables' => [new FormatCommand($tools), ['paths' => ['content/samples/unformatted.crv'], '--align-tables' => true]],
            'carve:fmt --check content/syntax' => [new FormatCommand($tools), ['paths' => ['content/syntax'], '--check' => true]],
            'carve:lint content/samples/lint-findings.crv' => [new LintCommand($tools), ['paths' => ['content/samples/lint-findings.crv']]],
            'carve:lint content/broken.crv --include-root=content' => [new LintCommand($tools), ['paths' => ['content/broken.crv'], '--include-root' => 'content']],
            'carve:convert content/import/legacy.md' => [new ConvertCommand($tools), ['path' => 'content/import/legacy.md']],
            'carve:convert content/import/page.html' => [new ConvertCommand($tools), ['path' => 'content/import/page.html']],
            'carve:convert content/import/note.djot --from=djot' => [new ConvertCommand($tools), ['path' => 'content/import/note.djot', '--from' => 'djot']],
        ];

        $results = [];
        foreach ($runs as $label => [$command, $arguments]) {
            $results[$label] = $this->runCommand($command, $arguments);
        }

        return $this->render('demo/console.html.twig', ['runs' => $results]);
    }

    #[Route('/includes', name: 'includes')]
    public function includes(CarveRenderer $carve, ConfigIncludeResolver $snippetResolver): Response
    {
        $path = $this->contentDir().'/main.crv';
        $fileReport = $carve->renderFileWithReport($path);
        $brokenReport = $carve->renderFileWithReport($this->contentDir().'/broken.crv');

        // A host resolver goes straight to the engine; the bundle's own include path is the file resolver.
        $dynamicSource = $this->source('samples/account-overview.crv');
        $includes = ['resolver' => $snippetResolver];
        $dynamic = Carve::renderWithDiagnostics($dynamicSource, ['includes' => $includes]);

        return $this->render('demo/file_includes.html.twig', [
            'include_root' => substr((string) $carve->getIncludeRoot(), strlen($this->projectDir) + 1),
            'file_source' => $this->source('main.crv'),
            'file_html' => $fileReport['value'],
            'file_dependencies' => $fileReport['dependencies'],
            'file_warnings' => $fileReport['warnings'],
            'file_text' => $carve->renderFileAs($path, 'text'),
            'broken_source' => $this->source('broken.crv'),
            'broken_html' => $brokenReport['value'],
            'broken_dependencies' => $brokenReport['dependencies'],
            'broken_warnings' => $brokenReport['warnings'],
            'dynamic_source' => $dynamicSource,
            'dynamic_html' => $dynamic->html,
            'dynamic_dependencies' => Carve::includeDependencies($dynamicSource, ['includes' => $includes]),
            'dynamic_warnings' => array_map(static fn ($diagnostic): string => $diagnostic->message, $dynamic->diagnostics),
        ]);
    }

    #[Route('/outputs-profiles', name: 'outputs_profiles')]
    public function outputsProfiles(CarveRenderer $carve): Response
    {
        $source = $this->source('samples/release-notes.crv');
        $profiles = [];
        foreach (['full', 'article', 'comment', 'minimal'] as $profile) {
            $profiles[$profile] = $carve->withOptions(['profile' => $profile])->render($source);
        }

        return $this->render('demo/outputs_profiles.html.twig', [
            'source' => $source,
            'html' => $carve->render($source),
            'text' => $carve->renderText($source),
            'markdown' => $carve->renderMarkdown($source),
            'ansi' => $this->ansiToHtml($carve->renderAnsi($source)),
            'file_markdown' => $carve->renderFileAs($this->contentDir().'/samples/release-notes.crv', 'markdown'),
            'profiles' => $profiles,
            'comment_html' => (new CarveRenderer(profile: 'comment'))->render($source),
        ]);
    }

    #[Route('/options', name: 'options')]
    public function options(CarveRenderer $carve): Response
    {
        $source = $this->source('samples/options.crv');
        $variants = [
            'Configured defaults' => [[], 'The service as configured in config/packages/carve.yaml.'],
            "heading_ids: 'ascii-lower'" => [['heading_ids' => 'ascii-lower'], 'Lowercase ASCII heading ids.'],
            "heading_ids: 'off'" => [['heading_ids' => 'off'], 'No generated heading ids.'],
            'sections: false' => [['sections' => false], 'Headings without section wrappers; the id moves onto the heading.'],
            'source_lines: true' => [['source_lines' => true], 'Each block carries data-source-line.'],
            'smart_typography: false' => [['smart_typography' => false], 'Quotes, dashes and ellipsis stay as typed.'],
            "mode: 'static'" => [['mode' => 'static'], 'Static output for pages without scripts.'],
            "profile: 'minimal'" => [['profile' => 'minimal'], 'The smallest profile; headings and admonitions fall back to text.'],
        ];
        $rendered = [];
        foreach ($variants as $label => [$options, $note]) {
            $rendered[$label] = ['options' => $options, 'note' => $note, 'html' => $carve->withOptions($options)->render($source)];
        }

        $labelled = new CarveRenderer(labels: ['admonitionNote' => 'Hinweis'], symbols: ['carve' => '🪶', 'tm' => '™']);
        $noSymbols = new CarveRenderer();

        $pool = new ArrayAdapter();
        $cached = new CarveRenderer(cache: $pool, cacheTtl: 60, cachePrefix: 'demo');
        $first = $cached->render($source);
        $second = $cached->render($source);

        return $this->render('demo/options.html.twig', [
            'source' => $source,
            'variants' => $rendered,
            'labels_html' => $labelled->render($source),
            'no_symbols_html' => $noSymbols->render($source),
            'cache_keys' => array_keys($pool->getValues()),
            'cache_same' => $first === $second,
        ]);
    }

    #[Route('/extensions', name: 'extensions')]
    public function extensions(CarveRenderer $carve): Response
    {
        $source = $this->source('samples/extensions.crv');
        $permalinks = new CarveRenderer(extensions: [['type' => 'heading_permalinks', 'symbol' => '#'], 'autolink']);

        return $this->render('demo/extensions.html.twig', [
            'source' => $source,
            'html' => $carve->render($source),
            'static_html' => $carve->withOptions(['mode' => 'static'])->render($source),
            'plain_html' => (new CarveRenderer())->render($source),
            'permalinks_html' => $permalinks->render($source),
        ]);
    }

    #[Route('/form', name: 'form')]
    public function form(Request $request, CarveRenderer $carve): Response
    {
        $article = new Article();
        $article->setBody($this->source('samples/welcome.crv'));

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        $preview = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $preview = [
                'title' => $article->getTitle(),
                'body_html' => $carve->render($article->getBody()),
                'comment_html' => $article->getComment() !== null && $article->getComment() !== ''
                    ? $carve->withOptions(['profile' => 'comment'])->render($article->getComment())
                    : null,
            ];
        }

        return $this->render('demo/form.html.twig', [
            'form' => $form,
            'preview' => $preview,
        ]);
    }

    #[Route('/safe-mode', name: 'safe_mode')]
    public function safeMode(CarveRenderer $carve): Response
    {
        $source = $this->source('samples/untrusted.crv');

        return $this->render('demo/safe_mode.html.twig', [
            'source' => $source,
            'escaped' => $carve->render($source),
            'trusted' => $carve->withOptions(['raw_html' => true])->render($source),
            'https_only' => $carve->withOptions(['url_policy' => ['allow' => ['https']]])->render($source),
        ]);
    }

    #[Route('/diagrams', name: 'diagrams')]
    public function diagrams(CarveRenderer $carve): Response
    {
        $rendered = [];
        foreach (self::DIAGRAMS as $label => $example) {
            $source = $this->source('diagrams/'.$example['file'].'.crv');
            $rendered[$label] = [
                'renderer' => $example['renderer'],
                'note' => $example['note'],
                'source' => $source,
                'html' => $carve->render($source),
            ];
        }

        return $this->render('demo/diagrams.html.twig', [
            'examples' => $rendered,
            'disabled_html' => (new CarveRenderer())->render($rendered['Mermaid']['source']),
        ]);
    }

    #[Route('/syntax', name: 'syntax')]
    public function syntax(CarveRenderer $carve): Response
    {
        $rendered = [];
        foreach (self::SYNTAX as $label => $file) {
            $source = $this->source('syntax/'.$file.'.crv');
            $rendered[$label] = ['source' => $source, 'html' => $carve->render($source)];
        }
        $literal = $this->source('syntax/inline-literal.crv');

        return $this->render('demo/syntax.html.twig', [
            'examples' => $rendered,
            'inline_literal' => ['source' => $literal, 'html' => $carve->render($literal)],
        ]);
    }

    private function contentDir(): string
    {
        return $this->projectDir.'/content';
    }

    private function source(string $file): string
    {
        return (string) file_get_contents($this->contentDir().'/'.$file);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{exit: int, output: string}
     */
    private function runCommand(Command $command, array $arguments): array
    {
        $cwd = (string) getcwd();
        chdir($this->projectDir);
        try {
            $input = new ArrayInput($arguments, $command->getDefinition());
            $output = new BufferedOutput();
            $exit = $command->run($input, $output);
        } finally {
            chdir($cwd);
        }

        return ['exit' => $exit, 'output' => str_replace($this->projectDir.'/', '', $output->fetch())];
    }

    /** @return list<string> */
    private function outline(Node $node, int $depth = 0): array
    {
        $lines = [str_repeat('  ', $depth).$node->type];
        foreach ($node->children ?? [] as $child) {
            $lines = [...$lines, ...$this->outline($child, $depth + 1)];
        }

        return $lines;
    }

    /** Turns the ANSI writer's SGR codes into spans so the page can show the terminal output. */
    private function ansiToHtml(string $ansi): string
    {
        $styles = ['1' => 'font-weight:bold', '3' => 'font-style:italic', '4' => 'text-decoration:underline', '95' => 'color:#d67ae8', '96' => 'color:#5fd7d7', '2' => 'opacity:.7'];
        $open = 0;
        $html = preg_replace_callback('/\e\[([0-9;]*)m/', static function (array $match) use ($styles, &$open): string {
            if ($match[1] === '0' || $match[1] === '') {
                $close = str_repeat('</span>', $open);
                $open = 0;

                return $close;
            }
            $css = [];
            foreach (explode(';', $match[1]) as $code) {
                $css[] = $styles[$code] ?? '';
            }
            ++$open;

            return '<span style="'.implode(';', array_filter($css)).'">';
        }, htmlspecialchars($ansi, ENT_QUOTES));

        return (string) $html.str_repeat('</span>', $open);
    }
}
