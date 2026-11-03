<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;

/**
 * Starts the search of the website with an empty index (decision 0228):
 * entries left from an earlier installation of the module may be old,
 * the first search fills it anew.
 */
final class Version20261204100000 extends Migration
{
    public function getDescription(): string
    {
        return 'Empties the index of the search on the website';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM search_index WHERE state = ?', ['live']);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM search_index WHERE state = ?', ['live']);
    }
}
