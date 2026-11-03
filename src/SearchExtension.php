<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

use Frootbox\Core\Action\ActionExecuted;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;

/**
 * The search on the website (decision 0228): the published state in the
 * index of the core, the query frootbox/search/website.find and the
 * block "Site search". Every source that describes what is published takes
 * part, from the core and from other extensions.
 */
final class SearchExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->query(FindOnWebsite::class);
        $registry->listen(ActionExecuted::class, UpdateWebsiteIndex::class);
    }
}
