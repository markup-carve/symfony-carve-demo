# symfony-carve demo

A small, runnable Symfony application that showcases every feature of the
[markup-carve/symfony-carve](https://github.com/markup-carve/symfony-carve) bundle, which renders
[Carve](https://github.com/markup-carve/carve) markup to HTML through the
[carve-php](https://github.com/markup-carve/carve-php) reference implementation.

Carve is "Djot minus the footguns": a lightweight markup language with consistent, unambiguous syntax.

> [!NOTE]
> Neither carve-php nor symfony-carve is tagged on Packagist yet. Until they are, this demo pulls both as
> `dev-main` straight from GitHub. The required VCS repositories are already declared in `composer.json`, so a
> plain `composer install` just works.

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
| Live Editor | `/form` | Type Carve in a form, submit, and see the rendered preview |
| Safe Mode | `/safe-mode` | Raw-HTML `strip` / `escape` / `allow` and disabled, side by side, against an XSS payload |
| Syntax | `/syntax` | A gallery of Carve constructs and their HTML output |

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
use Carve\Symfony\CarveRenderer;

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
```

| Key         | Type | Default | Description                                                                 |
|-------------|------|---------|-----------------------------------------------------------------------------|
| `safe_mode` | bool | `true`  | Enable HTML sanitization. Keep on for untrusted input.                      |
| `raw_html`  | enum | `strip` | How raw HTML is handled when `safe_mode` is on: `strip`, `escape`, `allow`. |

The Safe Mode page renders the same untrusted input under each setting so you can see the difference directly.

## Carve syntax cheat sheet

Carve's inline markers differ from Markdown - this is the most common surprise:

| Markup            | Renders as           | HTML        |
|-------------------|----------------------|-------------|
| `/italic/`        | italic               | `<em>`      |
| `*bold*`          | bold                 | `<strong>`  |
| `_underline_`     | underline            | `<u>`       |
| `=highlight=`     | highlighted          | `<mark>`    |
| `~strikethrough~` | struck through       | `<s>`       |
| `^superscript^`   | superscript          | `<sup>`     |

Block constructs (`# heading`, `- list`, `> quote`, fenced code, tables, `::: note` admonitions) follow the
[Carve specification](https://github.com/markup-carve/carve). The Syntax page renders a live example of each.

## How it is wired

- `config/bundles.php` registers `Carve\Symfony\CarveBundle`.
- `config/packages/carve.yaml` holds the bundle configuration.
- `src/Controller/DemoController.php` autowires `CarveRenderer` and renders each page.
- No database: the Live Editor uses a plain, non-persisted `Article` object.

## License

MIT
