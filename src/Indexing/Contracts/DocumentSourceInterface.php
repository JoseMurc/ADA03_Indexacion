<?php

declare(strict_types=1);

namespace App\Indexing\Contracts;

use App\Indexing\Dto\IndexedDocument;

interface DocumentSourceInterface
{
    /**
     * @return iterable<IndexedDocument>
     */
    public function getAll(): iterable;
}
