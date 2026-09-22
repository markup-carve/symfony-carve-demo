<?php

declare(strict_types=1);

namespace App\Carve;

use MarkupCarve\Carve\Transform\IncludeContext;
use MarkupCarve\Carve\Transform\IncludeResolverInterface;
use MarkupCarve\Carve\Transform\ResolvedInclude;

final class ConfigIncludeResolver implements IncludeResolverInterface
{
    /** @param array<string, string> $snippets */
    public function __construct(private readonly array $snippets)
    {
    }

    public function resolve(string $path, IncludeContext $context): ResolvedInclude|string|null
    {
        $source = $this->snippets[$path] ?? null;

        return $source === null ? null : new ResolvedInclude($source, 'config:' . $path);
    }
}
