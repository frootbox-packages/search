<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

/**
 * The hits of one page of a search on the website and how many there are
 * in all (decision 0228).
 */
final readonly class WebsiteResults
{
    /**
     * @param list<WebsiteHit> $hits
     */
    public function __construct(
        public int $total,
        public array $hits,
    ) {}
}
