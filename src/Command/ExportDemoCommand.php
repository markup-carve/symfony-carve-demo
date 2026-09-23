<?php

declare(strict_types=1);

namespace App\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(name: 'demo:export', description: 'Export the declared demo pages as a static site')]
final class ExportDemoCommand extends Command
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly RouterInterface $router,
        private readonly string $projectDir,
        private readonly bool $staticExport,
        private readonly string $staticBasePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('output', InputArgument::OPTIONAL, 'Output directory', 'dist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->staticExport) {
            throw new RuntimeException('Set STATIC_EXPORT=true before exporting.');
        }

        $pages = require $this->projectDir.'/config/demo_pages.php';
        $routes = $this->publicRoutes();
        $declaredNames = array_keys($pages);
        $routeNames = array_keys($routes);
        sort($declaredNames);
        sort($routeNames);
        if ($declaredNames !== $routeNames) {
            $missing = array_diff($routeNames, $declaredNames);
            $extra = array_diff($declaredNames, $routeNames);
            throw new RuntimeException(sprintf(
                'Route manifest mismatch. Missing: %s. Extra: %s.',
                implode(', ', $missing) ?: 'none',
                implode(', ', $extra) ?: 'none',
            ));
        }

        $outputArgument = (string) $input->getArgument('output');
        if (str_starts_with($outputArgument, '/') || str_contains($outputArgument, '..')) {
            throw new RuntimeException('The output must be a relative path inside the project.');
        }
        $target = $this->projectDir.'/'.trim($outputArgument, '/');
        if (is_dir($target)) {
            throw new RuntimeException("Output directory already exists: {$target}");
        }
        mkdir($target, 0777, true);
        file_put_contents($target.'/.nojekyll', '');

        foreach ($pages as $name => $mode) {
            if (! in_array($mode, ['static', 'browser-enhanced', 'local-only'], true)) {
                throw new RuntimeException("Unknown mode for {$name}: {$mode}");
            }
            $path = $routes[$name];
            $request = Request::create($path, 'GET', server: ['HTTP_HOST' => 'localhost']);
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("{$path} returned {$response->getStatusCode()}");
            }
            $html = preg_replace(
                '/((?:href|src|action)=(["\']))\/(?!\/|'.preg_quote(ltrim($this->staticBasePath, '/'), '/').'\/)/',
                '$1'.rtrim($this->staticBasePath, '/').'/',
                (string) $response->getContent(),
            );
            $directory = $path === '/' ? $target : $target.$path;
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/index.html', $html);
            $output->writeln("{$path} [{$mode}]");
        }

        copy($this->projectDir.'/public/diagram.svg', $target.'/diagram.svg');

        return Command::SUCCESS;
    }

    /** @return array<string, string> */
    private function publicRoutes(): array
    {
        $routes = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, '_')) {
                continue;
            }
            $methods = $route->getMethods();
            if ($methods === [] || in_array('GET', $methods, true)) {
                $routes[$name] = $route->getPath();
            }
        }

        return $routes;
    }
}
