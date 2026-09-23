# Screenshots

A visual tour of the symfony-carve demo app. Boot it (`symfony serve` or
`php -S 127.0.0.1:8000 -t public`) and click through the pages in the nav.

## Home

Overview of all demo pages.

![Home page](home.png)

## Includes

Trusted file-backed content and application-provided snippets, with each
directive's resolved dependency report.

![Includes](includes.png)

## Twig filter

Carve source and rendered HTML side by side.

![Twig filter](twig-filter.png)

## Live editor and safe mode

| Live editor | Safe mode |
|---|---|
| ![Live editor form](form.png) | ![Safe-mode comparison](safe-mode.png) |

## Syntax gallery

The new and easy-to-miss constructs (inline literal, definition lists,
footnotes, braced subscript, tight vs loose lists, smart typography, and
escaped block markers).

![Syntax gallery](syntax.png)

## Live Diagram Gallery

All eight fenced-render presets drawn live in the browser - mermaid, plantuml,
graphviz, d2, vega-lite, wavedrom, chart, abc. Each shows the Carve source, the
emitted hydration markup, and the drawn result (the source stays visible if a
renderer fails to load).

![Live Diagram Gallery](diagram-gallery.png)
