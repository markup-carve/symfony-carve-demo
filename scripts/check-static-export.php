<?php

declare(strict_types=1);

[$script, $directory, $basePath] = $argv;
$errors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'html') {
        continue;
    }
    $html = (string) file_get_contents($file->getPathname());
    if (! str_contains($html, 'Static preview')) {
        $errors[] = "{$file->getPathname()}: missing static-preview declaration";
    }
    if (! str_contains($html, 'href="https://markup-carve.github.io/carve/"')) {
        $errors[] = "{$file->getPathname()}: missing Carve website link";
    }
    preg_match_all('/(?:href|src|action)=(["\'])([^"\'#]+)\1/', $html, $matches);
    foreach ($matches[2] as $url) {
        if (str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/')) {
            $errors[] = "{$file->getPathname()}: root link escapes {$basePath}: {$url}";
        }
        if (str_starts_with($url, $basePath.'/')) {
            $path = parse_url($url, PHP_URL_PATH);
            $relative = ltrim(substr((string) $path, strlen($basePath)), '/');
            $target = pathinfo($relative, PATHINFO_EXTENSION) === ''
                ? rtrim($directory.'/'.$relative, '/').'/index.html'
                : $directory.'/'.$relative;
            if (! is_file($target)) {
                $errors[] = "{$file->getPathname()}: missing internal target: {$url}";
            }
        }
    }
    if (preg_match('/<nav\b.*?<\/nav>/s', $html, $nav) && str_contains($nav[0], '://localhost')) {
        $errors[] = "{$file->getPathname()}: local URL in navigation";
    }
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo "Static export links are scoped to {$basePath}.".PHP_EOL;
