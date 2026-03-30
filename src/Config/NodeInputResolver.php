<?php declare(strict_types=1);

namespace PhpTuic\Config;

final class NodeInputResolver
{
    public function resolve(?string $inlineNode, ?string $configPath, ?string $nodeName = null): NodeConfig
    {
        if ($inlineNode !== null && $inlineNode !== '') {
            return NodeLoader::fromString($inlineNode, $nodeName);
        }

        if ($configPath !== null && $configPath !== '') {
            return NodeLoader::fromFile($configPath, $nodeName);
        }

        throw new \RuntimeException('Provide either --node or --config.');
    }
}
