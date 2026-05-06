<?php

namespace Emad\FeeCollection\Services;

use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
use Emad\FeeCollection\FeeCollectionException;
use Emad\FeeCollection\Models\AccountStatement;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StatementPdfService implements StatementPdfServiceInterface
{
    public function __construct(private readonly ViewFactory $viewFactory)
    {
    }

    public function make(AccountStatement $statement): mixed
    {
        if (!app()->bound('dompdf.wrapper')) {
            throw new FeeCollectionException('PDF driver is not installed. Install barryvdh/laravel-dompdf.');
        }

        $view = $this->resolveView($statement);
        if (!$this->viewFactory->exists($view)) {
            throw new FeeCollectionException("Configured PDF view [{$view}] does not exist.");
        }

        $paper = (string) config('fee_collection.pdf.paper', 'a4');
        $orientation = (string) config('fee_collection.pdf.orientation', 'portrait');

        $pdf = app('dompdf.wrapper');
        $pdf->loadView($view, [
            'statement' => $statement->loadMissing(['upcomingPayments', 'accountable']),
            'accountable' => $statement->accountable,
            'upcomingPayments' => $statement->upcomingPayments,
        ]);
        $pdf->setPaper($paper, $orientation);

        return $pdf;
    }

    public function store(AccountStatement $statement): string
    {
        $pdf = $this->make($statement);
        $disk = (string) config('fee_collection.pdf.disk', 'public');
        $basePath = trim((string) config('fee_collection.pdf.path', 'fee-collection/documents'), '/');
        $type = $statement->type?->value ?? 'statement';
        $safeNumber = Str::slug((string) ($statement->formatted_number ?: $statement->id));
        $filename = "{$type}-{$safeNumber}-{$statement->id}.pdf";
        $relativePath = $basePath . '/' . $filename;

        Storage::disk($disk)->put($relativePath, $pdf->output());

        return $relativePath;
    }

    private function resolveView(AccountStatement $statement): string
    {
        return match ($statement->type) {
            AccountStatementType::INVOICE => (string) config('fee_collection.invoice_view', 'fee-collection::pdf.invoice'),
            AccountStatementType::RECEIPT => (string) config('fee_collection.receipt_view', 'fee-collection::pdf.receipt'),
        };
    }
}
