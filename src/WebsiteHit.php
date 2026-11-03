<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

/**
 * A page, article, event or other thing a visitor found (decision 0228).
 */
final readonly class WebsiteHit
{
    public function __construct(
        public string $source,
        public string $item,
        public string $title,
        public string $excerpt,
        public string $url,
        public int $score,
    ) {}
}
