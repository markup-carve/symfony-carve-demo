<?php

declare(strict_types=1);

namespace App\Tests;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Include\FileResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every Carve source the demo shows is canonical 0.2 and lint-clean, except the samples whose
 * subject is a finding.
 */
final class ContentTest extends TestCase
{
    private const DELIBERATE = [
        'samples/unformatted.crv' => 'shows the formatter',
        'samples/lint-findings.crv' => 'shows lint findings',
        'broken.crv' => 'shows an include warning',
        'samples/account-overview.crv' => 'resolved by the host resolver, not files',
    ];

    /** @return iterable<string, array{string}> */
    public static function sources(): iterable
    {
        $root = dirname(__DIR__).'/content';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() === 'crv') {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                yield $relative => [$relative];
            }
        }
    }

    #[DataProvider('sources')]
    public function testSourceIsCanonicalAndLintClean(string $relative): void
    {
        $root = dirname(__DIR__).'/content';
        $source = (string) file_get_contents($root.'/'.$relative);
        if (isset(self::DELIBERATE[$relative])) {
            self::assertNotSame('', $source, self::DELIBERATE[$relative]);

            return;
        }

        self::assertSame(Carve::format($source), $source, "{$relative} is not canonical");
        $includes = ['resolver' => FileResolver::forRoot($root), 'file' => $relative];
        $codes = array_map(static fn ($diagnostic): string => $diagnostic->code, Carve::lint($source, ['includes' => $includes]));
        self::assertSame([], $codes, "{$relative} has lint findings");
    }

    public function testDeliberateSamplesStillShowWhatTheyAreFor(): void
    {
        $root = dirname(__DIR__).'/content';
        $unformatted = (string) file_get_contents($root.'/samples/unformatted.crv');
        self::assertNotSame(Carve::format($unformatted), $unformatted);
        self::assertNotSame([], Carve::lint((string) file_get_contents($root.'/samples/lint-findings.crv')));
    }
}
