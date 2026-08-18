# symfony-carve demo

A small, runnable Symfony application that showcases every feature of the
[markup-carve/symfony-carve](https://github.com/markup-carve/symfony-carve) bundle, which renders
[Carve](https://github.com/markup-carve/carve) markup to HTML through the
[carve-php](https://github.com/markup-carve/carve-php) reference implementation.

Carve is "Djot minus the footguns": a lightweight markup language with consistent, unambiguous syntax.

> [!NOTE]
> This demo tracks the released packages from Packagist: `markup-carve/symfony-carve` `^0.1.4` (which pulls
> `markup-carve/carve-php` `^0.1.5`). A plain `composer install` just works.

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

| Page | Route | Demonstrates |
|------|-------|--------------|
| Home | `/` | Overview and links to every page |
| Twig Filter | `/twig-filter` | `{{ source\|carve }}` - render a Carve string in a template |
| Twig Function | `/twig-function` | `{{ carve(source) }}` - the function form |
| Service | `/service` | Inject `CarveRenderer` and call `render()` from PHP |
| Outputs & Profiles | `/outputs-profiles` | HTML, plain-text, and Markdown targets plus feature-restriction profiles (0.1.4) |
| Live Editor | `/form` | Type Carve in a form, submit, and see the rendered preview |
| Safe Mode | `/safe-mode` | Raw-HTML `strip` / `escape` / `allow` and disabled, side by side, against an XSS payload |
| Syntax | `/syntax` | A gallery of Carve constructs, including the inline literal `` !`...` ``, definition lists, footnotes, smart typography, tight vs loose lists, and the strict column-0 rule |
| Diagrams | `/diagrams` | The `diagrams` config option turning fences into diagram hydration elements, with all eight presets drawn live in the browser |

## Screenshots

See the **[screenshot gallery](docs/screenshots/)** for a visual tour - the live diagram gallery (all eight fenced-render presets drawn live), the Twig filter, the live editor, safe mode, the syntax gallery, and the home page.


### Twig

```twig
{# filter #}
{{ article.body|carve }}

{# function #}
{{ carve('# Inline /snippet/') }}
```

Output is marked safe, so Twig does not double-escape it - the renderer sanitizes input according to the
configured safe mode first.

### Service

```php
use MarkupCarve\SymfonyCarve\CarveRenderer;

public function show(CarveRenderer $carve): Response
{
    return new Response($carve->render('# Hello *world*'));
}
```

## Configuration

The bundle is configured in `config/packages/carve.yaml`:

```yaml
carve:
    safe_mode: true   # sanitize HTML (default)
    raw_html: strip   # strip | escape | allow
    # All eight presets; note the enum spells Vega-Lite `vega_lite` (underscore).
    diagrams: ['mermaid', 'plantuml', 'graphviz', 'd2', 'vega_lite', 'wavedrom', 'chart', 'abc']   # default: []
```

| Key         | Type     | Default | Description                                                                 |
|-------------|----------|---------|-----------------------------------------------------------------------------|
| `safe_mode` | bool     | `true`  | Enable HTML sanitization. Keep on for untrusted input.                      |
| `raw_html`  | enum     | `strip` | How raw HTML is handled when `safe_mode` is on: `strip`, `escape`, `allow`. |
| `diagrams`  | string[] | `[]`    | Diagram fenced-block presets to enable: `mermaid`, `plantuml`, `graphviz`, `d2`, `wavedrom`, `vega_lite`, `chart`, `abc`. Off by default. The enum spells Vega-Lite `vega_lite` (underscore); the fence word authors type is `vega-lite` (hyphen). |

The Safe Mode page renders the same untrusted input under each setting so you can see the difference directly.

### Diagrams

With a preset listed under `diagrams`, a matching fence renders as a hydration element for a
client-side renderer instead of a plain code block - for example a ` ``` mermaid ` block becomes
`<pre class="mermaid">...</pre>`, and a ` ``` vega-lite ` block becomes
`<div class="vega-lite"><script type="application/json">...</script></div>`. The bundle only emits
the markup; it never draws the diagram or ships a renderer, so each preset needs its own browser
library on the page.

The Diagrams page wires one renderer per type (all from public CDNs) and draws every preset live.
Each card shows the Carve source, the emitted markup, and the drawn result. Every renderer degrades
gracefully: if a library or server is unreachable, the diagram source stays visible as text.

| Preset | Emitted element | Browser renderer | Runtime prerequisite |
|--------|-----------------|------------------|----------------------|
| `mermaid`   | `<pre class="mermaid">`   | mermaid.js                     | CDN load; draws client-side |
| `graphviz`  | `<pre class="graphviz">`  | `@viz-js/viz` (WebAssembly)    | CDN load; then renders offline |
| `d2`        | `<pre class="d2">`        | [Kroki](https://kroki.io) server (deflate-encoded) | CDN load + network to Kroki at render time |
| `plantuml`  | `<pre class="plantuml">`  | [PlantUML server](https://www.plantuml.com/plantuml) via its `~h` hex scheme | network to the PlantUML server at render time |
| `vega_lite` | `<div class="vega-lite">` | vega + vega-lite + vega-embed  | CDN load; draws client-side |
| `wavedrom`  | `<pre class="wavedrom">`  | wavedrom + default skin        | CDN load; draws client-side |
| `chart`     | `<div class="chart">`     | chart.js (onto a `<canvas>`)   | CDN load; draws client-side |
| `abc`       | `<pre class="abc">`       | abcjs                          | CDN load; draws client-side |

D2 and PlantUML have no small in-browser build, so they are drawn through public servers (Kroki and
the PlantUML server). Those servers receive your diagram source, so self-host them for sensitive
content. Every other preset draws entirely client-side once its library has loaded from the CDN.

The Diagrams page also shows the same fence with the option off (plain code block) and on (hydration
element) side by side.

## Carve syntax cheat sheet

Carve's inline markers differ from Markdown - this is the most common surprise:

| Markup            | Renders as           | HTML        |
|-------------------|----------------------|-------------|
| `/italic/`        | italic               | `<em>`      |
| `*bold*`          | bold                 | `<strong>`  |
| `_underline_`     | underline            | `<u>`       |
| `=highlight=`     | highlighted          | `<mark>`    |
| `~strikethrough~` | struck through       | `<s>`       |
| `{^superscript^}` | superscript          | `<sup>`     |
| `{,subscript,}`   | subscript            | `<sub>`     |
| `` !`literal` ``  | verbatim characters, no code styling | plain text |

Sup/sub are braced only (`{^x^}` / `{,x,}`) - Carve does not treat bare `^`/`,` as markers. The inline
literal `` !`...` `` shows the characters between the backticks exactly, with no code styling and no further
markup processing.

Block constructs follow the [Carve specification](https://github.com/markup-carve/carve): `# heading`,
`- list` (tight vs loose), `> quote`, fenced code, tables, `::: note` admonitions, definition lists
(`:: term` / `:  definition`), and footnotes (`[^ref]` with a `[^ref]:` body). Carve also applies smart
typography - `--` becomes an en dash, `---` an em dash, `...` an ellipsis, and straight quotes become curly.
Block markers are strict about column 0: an indented `#` or `-` stays literal text rather than opening a
heading or list. The Syntax page renders a live example of each.

## How it is wired

- `config/bundles.php` registers `MarkupCarve\SymfonyCarve\CarveBundle`.
- `config/packages/carve.yaml` holds the bundle configuration.
- `src/Controller/DemoController.php` autowires `CarveRenderer` and renders each page.
- No database: the Live Editor uses a plain, non-persisted `Article` object.

## License

MIT
