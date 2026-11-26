<?php

declare(strict_types=1);

namespace App\Carve;

use MarkupCarve\Carve\Include\IncludeResolver;

/**
 * Resolves `{{ name }}` includes from trusted application configuration instead of the file system.
 */
final class ConfigIncludeResolver implements IncludeResolver
{
    /** @param array<string, string> $snippets */
    public function __construct(private readonly array $snippets)
    {
    }

    /**
     * @param array{path: string, options: array<mixed>, section?: mixed, from?: string, budget: int} $request
     *
     * @return array{text: string, id: string}|array{error: string}
     */
    public function resolve(array $request): array
    {
        $source = $this->snippets[$request['path']] ?? null;

        return $source === null ? ['error' => 'not_found'] : ['text' => $source, 'id' => 'config:'.$request['path']];
    }
}
