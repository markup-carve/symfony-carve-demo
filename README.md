# symfony-carve demo

A runnable Symfony application demonstrating the
[markup-carve/symfony-carve](https://github.com/markup-carve/symfony-carve) bundle, which renders
[Carve](https://github.com/markup-carve/carve) markup to HTML through the
[carve-php](https://github.com/markup-carve/carve-php) reference implementation.

**[View the live demo →](https://markup-carve.github.io/symfony-carve-demo/)**

Carve is a lightweight markup language for structured documents, with clear, consistent syntax.

> [!NOTE]
> This branch (`next2`) previews Carve 0.2. It installs `markup-carve/symfony-carve` `dev-next2` and pins
> `markup-carve/carve-php` to the `next2` commit `e84ca218`. Neither is a registry release. The Carve 0.1 demo
> lives on `main`.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
git clone https://github.com/markup-carve/symfony-carve-demo.git
cd symfony-carve-demo
composer install
```

## Running

```bash
php -S localhost:8000 -t public
```

Then open <http://localhost:8000>.

## What the demo shows

Every public feature of symfony-carve `next2` is shown on a page, and `tests/FeatureCoverageTest.php` fails when
the bundle gains a Twig filter or function, a service method, a command or a config key that no page uses.

| Page | Route | Demonstrates |
|------|-------|--------------|
| Home | `/` | Overview and links to every page |
| Twig Filter | `/twig-filter` | `{{ source\|carve }}` and per-call render options |
| Twig Function | `/twig-function` | `{{ carve(source) }}` and `{{ carve_file('parts/checklist.crv') }}` |
| Twig Tools | `/twig-tools` | `carve_document()` (table of contents, frontmatter `meta()`), `carve_format`, `carve_lint()` |
| Service | `/service` | `CarveRenderer`: `render()`, `renderResult()`, `parse()`, `withOptions()` |
| CarveTools | `/tools` | `format()`, `isFormatted()`, `lint()` with and without an include root, import from HTML, Markdown and Djot |
| Console | `/console` | `carve:render`, `carve:fmt`, `carve:lint`, `carve:convert`, each run while the page renders |
| Includes | `/includes` | `renderFileWithReport()`, `renderFileAs()`, `getIncludeRoot()`, include warnings, a named region, code and CSV includes narrowed with `@rows` and `@cols`, and a host `IncludeResolver` |
| Outputs & Profiles | `/outputs-profiles` | HTML, plain text, Markdown and ANSI, `renderFileAs()`, the four profiles, `carve_text` and `carve_markdown` |
| Render Options | `/options` | `heading_ids`, `sections`, `source_lines`, `smart_typography`, `soft_break_mode`, `mode`, `symbols`, `labels` and the render cache |
| Extensions | `/extensions` | `tabs` and `wikilinks`, static mode, and the `heading_permalinks` and `autolink` shortcuts |
| Live Editor | `/form` | A form validated by `ValidCarve` (`strict`, `lint`, `preset`, `maxLength`) with a rendered preview |
| Safe Output | `/safe-mode` | `raw_html` and `url_policy` against an XSS payload |
| Syntax | `/syntax` | A gallery of Carve 0.2 constructs |
| Diagrams | `/diagrams` | The `diagrams` option turning fences into hydration elements, with all eight presets drawn live in the browser |

Every Carve source the pages show lives in `content/`. `tests/ContentTest.php` checks that each one is canonical
and lint-clean, apart from the few samples whose subject is a formatter change, a lint finding or an include warning.

## Screenshots

See the [screenshot gallery](docs/screenshots/) for Includes, diagrams, Twig, the editor, safe output, syntax, and home.

### Twig

```twig
{# filter, with optional per-call render options #}
{{ article.body|carve }}
{{ comment.body|carve({profile: 'comment'}) }}

{# function #}
{{ carve('= Inline /snippet/') }}

{# a file below include_root #}
{{ carve_file('parts/intro.crv') }}

{# headings and frontmatter #}
{% set doc = carve_document(article.body) %}
{{ doc.meta('title') }}
```

HTML output is marked safe, so Twig does not double-escape it. The renderer escapes raw HTML and blanks denied
link schemes first.

### Service

```php
use MarkupCarve\SymfonyCarve\CarveRenderer;

public function show(CarveRenderer $carve): Response
{
    return new Response($carve->render('= Hello *world*'));
}
```

## Configuration

The bundle is configured in `config/packages/carve.yaml`, which sets every key the bundle accepts:

```yaml
carve:
    raw_html: false
    url_policy: []
    profile: full
    mode: interactive
    sections: true
    heading_ids: keep-case
    source_lines: false
    smart_typography: true
    soft_break_mode: newline
    symbols: { carve: '🪶', tm: '™' }
    labels: {}
    include_root: '%kernel.project_dir%/content'
    extensions:
        - tabs
        - { type: wikilinks, url_template: 'https://github.com/markup-carve/symfony-carve/wiki/{page}' }
    diagrams: ['mermaid', 'plantuml', 'graphviz', 'd2', 'vega_lite', 'wavedrom', 'chart', 'abc']
    cache: { enabled: true, pool: cache.app, ttl: 3600, prefix: carve, version: 'demo-1' }
```

| Key | Default | Description |
|-----|---------|-------------|
| `raw_html` | `false` | Pass raw HTML blocks and spans through. Keep it off for untrusted input. |
| `url_policy` | `[]` | URL scheme policy (`allow` or `deny` lists). Empty uses the engine's secure default. |
| `profile` | `full` | `full`, `article`, `comment` or `minimal`. |
| `mode` | `interactive` | `interactive` or `static`. |
| `sections` | `true` | Wrap each heading and its content in `<section>`. |
| `heading_ids` | `keep-case` | `off`, `keep-case`, `lower`, `ascii`, `ascii-lower`, `ascii-strict`, `ascii-strict-lower`. |
| `source_lines` | `false` | Add `data-source-line` attributes. |
| `smart_typography` | `true` | Curly quotes, dashes and ellipsis. |
| `soft_break_mode` | `newline` | How a line break inside a paragraph renders: `newline`, `space` or `br`. |
| `symbols`, `labels` | `{}` | Engine maps for `:name:` symbols and built-in labels. |
| `extensions` | `[]` | Carve extension objects or the shortcuts `tabs`, `wikilinks`, `heading_permalinks`, `autolink`, `mermaid`, `plantuml`. |
| `diagrams` | `[]` | Diagram fence presets. The enum spells Vega-Lite `vega_lite`; the fence word is `vega-lite`. |
| `include_root` | `null` | Absolute root for file includes and `carve_file()`. |
| `cache` | off | Cache rendered HTML in a cache pool. Set `version` when an extension holds a closure. |

### Diagrams

With a preset listed under `diagrams`, a matching fence renders as a hydration element for a client-side
renderer instead of a plain code block. A ```` ```mermaid ```` fence becomes
`<pre class="mermaid" role="img" aria-label="mermaid">...</pre>` with the fence body escaped inside; every preset
uses the same shape with its own class (`vega_lite` for Vega-Lite). The bundle only emits the markup and ships no
renderer, so each preset needs its own browser library on the page.

| Preset | Emitted element | Browser renderer | Runtime prerequisite |
|--------|-----------------|------------------|----------------------|
| `mermaid`   | `<pre class="mermaid">`   | mermaid.js                     | CDN load; draws client-side |
| `graphviz`  | `<pre class="graphviz">`  | `@viz-js/viz` (WebAssembly)    | CDN load; then renders offline |
| `d2`        | `<pre class="d2">`        | [Kroki](https://kroki.io) server (deflate-encoded) | CDN load + network to Kroki at render time |
| `plantuml`  | `<pre class="plantuml">`  | [PlantUML server](https://www.plantuml.com/plantuml) via its `~h` hex scheme | network to the PlantUML server at render time |
| `vega_lite` | `<pre class="vega_lite">` | vega + vega-lite + vega-embed  | CDN load; draws client-side |
| `wavedrom`  | `<pre class="wavedrom">`  | wavedrom + default skin        | CDN load; draws client-side |
| `chart`     | `<pre class="chart">`     | chart.js (onto a `<canvas>`)   | CDN load; draws client-side |
| `abc`       | `<pre class="abc">`       | abcjs                          | CDN load; draws client-side |

D2 and PlantUML have no small in-browser build, so they are drawn through public servers (Kroki and the PlantUML
server). Those servers receive your diagram source, so self-host them for sensitive content. If a library or
server is unreachable, the diagram source stays visible as text.

## Carve syntax cheat sheet

Carve's inline markers differ from Markdown. Carve 0.2 writes the rarer spans in braces:

| Markup            | Renders as           | HTML        |
|-------------------|----------------------|-------------|
| `/italic/`        | italic               | `<em>`      |
| `*bold*`          | bold                 | `<strong>`  |
| `{_underline_}`   | underline            | `<u>`       |
| `{=highlight=}`   | highlighted          | `<mark>`    |
| `{~strikethrough~}` | struck through     | `<s>`       |
| `{^superscript^}` | superscript          | `<sup>`     |
| `{,subscript,}`   | subscript            | `<sub>`     |
| `` !`literal` ``  | verbatim characters, no code styling | plain text |

Headings use `=` markers (`= Title`, `== Section`). Blocks inside a list item are indented under the item after a
blank line. Table cell alignment goes in braces glued to the cell marker: `|={<}`, `|={~}` and `|={>}` on header
cells, `|{>}` on a body cell. A `%%` comment line is erased before layout, so it does not split the paragraph
around it. The Syntax page renders a live example of each construct.

To move a 0.1 document to 0.2, run the carve-js `carve upgrade` tool, review each rewrite, then `carve fmt` and
`carve lint`.

## Console commands

The bundle registers four commands in the demo's console:

```bash
php bin/console carve:render content/samples/release-notes.crv --format=markdown
php bin/console carve:fmt --check content/syntax
php bin/console carve:lint content/broken.crv --include-root=content
php bin/console carve:convert content/import/legacy.md
```

The Console page runs each of them and shows the output.

## Tests

```bash
composer test
```

The suite requests every page, posts the form with valid and invalid input, checks the feature coverage, and
checks every Carve source in `content/`.

## How it is wired

- `config/bundles.php` registers `MarkupCarve\SymfonyCarve\CarveBundle`.
- `config/packages/carve.yaml` holds the bundle configuration.
- `src/Controller/DemoController.php` autowires `CarveRenderer` and `CarveTools` and renders each page.
- `src/Carve/ConfigIncludeResolver.php` is a Carve `IncludeResolver` backed by configuration.
- No database: the Live Editor uses a plain, non-persisted `Article` object.
