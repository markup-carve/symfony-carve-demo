<?php

declare(strict_types=1);

namespace App\Tests;

use MarkupCarve\SymfonyCarve\CarveBundle;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use MarkupCarve\SymfonyCarve\CarveTools;
use MarkupCarve\SymfonyCarve\RenderedCarve;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\FileLocator;
use Twig\Environment;

/**
 * Guards the promise that the demo shows every public feature of the bundle: a new Twig filter,
 * service method, command or config key in symfony-carve fails here until a page uses it.
 */
final class FeatureCoverageTest extends KernelTestCase
{
    public function testEveryBundleTwigFilterAndFunctionIsUsedInATemplate(): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');
        $templates = $this->read(dirname(__DIR__).'/templates', 'twig');
        $names = [];
        foreach ($twig->getFilters() as $name => $filter) {
            if (str_starts_with($name, 'carve')) {
                $names[] = $name;
                self::assertMatchesRegularExpression('/\|'.$name.'\b/', $templates, "Twig filter {$name} is not shown on any page");
            }
        }
        foreach ($twig->getFunctions() as $name => $function) {
            if (str_starts_with($name, 'carve')) {
                $names[] = $name;
                self::assertMatchesRegularExpression('/\b'.$name.'\(/', $templates, "Twig function {$name}() is not shown on any page");
            }
        }
        self::assertContains('carve_document', $names);
    }

    public function testEveryPublicServiceMethodIsCalledByTheDemo(): void
    {
        $code = $this->read(dirname(__DIR__).'/src', 'php').$this->read(dirname(__DIR__).'/templates', 'twig');
        foreach ([CarveRenderer::class, CarveTools::class, RenderedCarve::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                if (str_starts_with($name, '__') || $name === 'jsonSerialize') {
                    continue;
                }
                $twigName = lcfirst((string) preg_replace('/^(get|is|has)/', '', $name));
                self::assertTrue(
                    str_contains($code, '->'.$name.'(') || str_contains($code, '.'.$name) || str_contains($code, '.'.$twigName),
                    "{$class}::{$name}() is not used by any page",
                );
            }
        }
    }

    public function testEveryBundleCommandRunsOnTheConsolePage(): void
    {
        $application = new Application(self::bootKernel());
        $controller = (string) file_get_contents(dirname(__DIR__).'/src/Controller/DemoController.php');
        $commands = array_filter(array_keys($application->all()), static fn (string $name): bool => str_starts_with($name, 'carve:'));

        self::assertNotEmpty($commands);
        foreach ($commands as $name) {
            self::assertStringContainsString("'{$name} ", $controller, "Command {$name} is not run on the console page");
        }
    }

    public function testEveryBundleConfigKeyIsSetInTheDemoConfig(): void
    {
        $treeBuilder = new TreeBuilder('carve');
        $file = (new ReflectionClass(CarveBundle::class))->getFileName();
        $loader = new DefinitionFileLoader($treeBuilder, new FileLocator(dirname((string) $file)));
        (new CarveBundle())->configure(new DefinitionConfigurator($treeBuilder, $loader, (string) $file, (string) $file));
        $root = $treeBuilder->buildTree();
        self::assertInstanceOf(ArrayNode::class, $root);

        $config = (string) file_get_contents(dirname(__DIR__).'/config/packages/carve.yaml');
        foreach ($root->getChildren() as $name => $child) {
            self::assertMatchesRegularExpression('/^    '.$name.':/m', $config, "Config key carve.{$name} is not in carve.yaml");
            if ($child instanceof ArrayNode && $name === 'cache') {
                foreach (array_keys($child->getChildren()) as $cacheKey) {
                    self::assertMatchesRegularExpression('/^        '.$cacheKey.':/m', $config, "Config key carve.cache.{$cacheKey} is not in carve.yaml");
                }
            }
        }
    }

    private function read(string $directory, string $extension): string
    {
        $code = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->getExtension() === $extension) {
                $code .= file_get_contents($file->getPathname());
            }
        }

        return $code;
    }
}
