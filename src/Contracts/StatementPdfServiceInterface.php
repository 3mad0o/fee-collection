<?php

namespace Emad\FeeCollection\Contracts;

use Emad\FeeCollection\Models\AccountStatement;

interface StatementPdfServiceInterface
{
    /**
     * Build a PDF object for invoice/receipt account statement.
     */
    public function make(AccountStatement $statement): mixed;

    /**
     * Generate and store the statement PDF, then return saved path.
     */
    public function store(AccountStatement $statement): string;
}
