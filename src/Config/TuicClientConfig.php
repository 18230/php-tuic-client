<?php

declare(strict_types=1);

namespace TuicClient\Config;

final readonly class TuicClientConfig
{
    public function __construct(
        public TuicNodeConfig $node,
        public TuicRuntimeConfig $runtime,
    ) {
    }
}
