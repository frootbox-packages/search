<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

use Frootbox\Core\Action\ActionExecuted;
use Frootbox\Core\Action\ActionLog;
use Frootbox\Core\Search\SearchIndex;
use Frootbox\Core\Search\SearchIndexer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the published entries again that an action changed, e.g. after
 * publishing a page or when an article goes offline by itself (decision
 * 0228). A failure is logged and never stops the action;
 * "search:rebuild --website" repairs the index.
 */
final readonly class UpdateWebsiteIndex
{
    public function __construct(
        private ActionLog $log,
        private SearchIndexer $indexer,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ActionExecuted $event): void
    {
        try {
            $this->indexer->changed($this->log->changes($event->entryId), SearchIndex::LIVE);
        } catch (Throwable $error) {
            $this->logger->error('The search of the website could not be updated after {action}: {message}', [
                'action' => $event->descriptor->name,
                'message' => $error->getMessage(),
                'exception' => $error,
            ]);
        }
    }
}
