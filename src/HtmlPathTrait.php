<?php

declare(strict_types=1);

namespace WilyCode\Form;

trait HtmlPathTrait
{
    /**
     * @var list<int|string>
     */
    private array $path = [];

    /**
     * @return list<int|string>
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * @param list<int|string> $path
     */
    protected function assignPath(array $path): void
    {
        $this->path = $path;
    }

    public function getPathString(string $separator = '.'): string
    {
        return implode($separator, array_map(static fn (int|string $segment): string => (string) $segment, $this->path));
    }

    public function getHtmlName(): string
    {
        if ($this->path === []) {
            return '';
        }

        $segments = $this->path;
        $name = (string) array_shift($segments);

        foreach ($segments as $segment) {
            $name .= '[' . $segment . ']';
        }

        return $name;
    }

    public function getHtmlId(string $separator = '_'): string
    {
        $parts = array_map(
            static fn (int|string $segment): string => trim(preg_replace('/[^a-zA-Z0-9_-]+/', $separator, (string) $segment) ?? '', $separator),
            $this->path,
        );

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode($separator, $parts);
    }
}
