<?php

namespace Emad\FeeCollection\Services;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Contracts\WalletServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementStatus;
use Emad\FeeCollection\Enums\AccountStatementType;
use Emad\FeeCollection\Events\CreditNoteCreated;
use Emad\FeeCollection\Events\InvoiceCreated;
use Emad\FeeCollection\Events\InvoiceVoided;
use Emad\FeeCollection\Events\ReceiptCreated;
use Emad\FeeCollection\FeeCollectionException;
use Emad\FeeCollection\Models\AccountStatement;
use Emad\FeeCollection\Models\UpcomingPayment;
use Emad\FeeCollection\Traits\UseFeeable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountStatementService implements AccountStatementServiceInterface
{
    public function __construct(
        private readonly WalletServiceInterface $walletService,
        private readonly StatementPdfServiceInterface $statementPdfService
    ) {}

    public function createInvoice(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null
    ): AccountStatement {
        $date ??= Carbon::now();
        if ($upcomingPayment->invoice()->exists()) {
            throw new FeeCollectionException('Invoice already created');
        }

        /** @var Model&UseFeeable $payable */
        $payable = $upcomingPayment->payable;
        $amount = (float) $upcomingPayment->amount;

        return DB::transaction(function () use ($upcomingPayment, $description, $date, $document, $payable, $amount): AccountStatement {
            $accountStatement = AccountStatement::create([
                'accountable_id' => $upcomingPayment->payable_id,
                'accountable_type' => $upcomingPayment->payable_type,
                'amount' => $amount,
                'description' => $description,
                'date' => $date,
                'debit' => $amount,
                'credit' => 0,
                'balance' => 0,
                'type' => AccountStatementType::INVOICE,
                'status' => AccountStatementStatus::Issued,
                'number' => $payable->lastInvoiceNumber() + 1,
                'document' => $document,
            ]);

            $accountStatement->upcomingPayments()->syncWithoutDetaching([$upcomingPayment->id]);
            $this->walletService->record($payable, AccountStatementType::INVOICE, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            $accountStatement = $accountStatement->refresh();
            event(new InvoiceCreated($accountStatement));

            return $accountStatement;
        });
    }

    public function createUpcomingPaymentReceipt(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null,
        ?bool $autoInvoice = null
    ): AccountStatement {
        $date ??= Carbon::now();
        $autoInvoice ??= (bool) config('fee_collection.auto_invoice_on_receipt', true);

        if ($upcomingPayment->receipt()->exists()) {
            throw new FeeCollectionException('Receipt already created');
        }

        /** @var Model&UseFeeable $payable */
        $payable = $upcomingPayment->payable;
        $amount = (float) $upcomingPayment->remaining_amount;
        if ($amount <= 0) {
            throw new FeeCollectionException('Upcoming payment already closed');
        }

        return DB::transaction(function () use ($upcomingPayment, $description, $date, $document, $payable, $amount, $autoInvoice): AccountStatement {
            $accountStatement = $this->createReceiptStatement(
                $payable,
                $amount,
                $description,
                $date,
                $document
            );

            $upcomingPayment->update([
                'remaining_amount' => 0,
            ]);

            if ($autoInvoice && !$upcomingPayment->invoice()->exists()) {
                $this->createInvoice($upcomingPayment, 'Auto-generated invoice during receipt settlement', $date, $document);
            }

            $accountStatement->upcomingPayments()->syncWithoutDetaching([$upcomingPayment->id]);
            $this->markPaymentsPaid([$upcomingPayment->id]);
            $this->walletService->record($payable, AccountStatementType::RECEIPT, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            $accountStatement = $accountStatement->refresh();
            event(new ReceiptCreated($accountStatement));

            return $accountStatement;
        });
    }

    /**
     * @param Model&UseFeeable $payable
     */
    public function createReceipt(
        Model $payable,
        float $amount,
        string $description,
        ?Carbon $date = null,
        ?string $document = null,
        ?bool $autoInvoice = null
    ): AccountStatement {
        $date ??= Carbon::now();
        $autoInvoice ??= (bool) config('fee_collection.auto_invoice_on_receipt', true);

        if ($amount <= 0) {
            throw new FeeCollectionException('Amount must be greater than zero');
        }

        return DB::transaction(function () use ($payable, $amount, $description, $date, $document, $autoInvoice): AccountStatement {
            $remainingToSettle = $amount;
            $upcomingPaymentIds = [];

            $openUpcomingPayments = UpcomingPayment::query()
                ->wherePayableId($payable->getKey())
                ->wherePayableType($payable::class)
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            /** @var UpcomingPayment $upcomingPayment */
            foreach ($openUpcomingPayments as $upcomingPayment) {
                if ($remainingToSettle <= 0) {
                    break;
                }

                if ($autoInvoice && !$upcomingPayment->invoice()->exists()) {
                    $this->createInvoice($upcomingPayment, 'Auto-generated invoice during receipt settlement', $date, $document);
                }

                $appliedAmount = min((float) $upcomingPayment->remaining_amount, $remainingToSettle);
                $newRemaining = (float) $upcomingPayment->remaining_amount - $appliedAmount;
                $upcomingPayment->update(['remaining_amount' => $newRemaining]);

                if ($appliedAmount > 0) {
                    $upcomingPaymentIds[] = $upcomingPayment->id;
                    $remainingToSettle -= $appliedAmount;
                }
            }

            $accountStatement = $this->createReceiptStatement(
                $payable,
                $amount,
                $description,
                $date,
                $document
            );

            $accountStatement->upcomingPayments()->syncWithoutDetaching(array_unique($upcomingPaymentIds));
            $this->markPaymentsPaid($upcomingPaymentIds);
            $this->walletService->record($payable, AccountStatementType::RECEIPT, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            $accountStatement = $accountStatement->refresh();
            event(new ReceiptCreated($accountStatement));

            return $accountStatement;
        });
    }

    public function createCreditNote(
        AccountStatement $invoice,
        string $description,
        ?Carbon $date = null
    ): AccountStatement {
        $date ??= Carbon::now();
        $this->ensureCanReverseInvoice($invoice, 'credited');

        /** @var Model&UseFeeable $payable */
        $payable = $invoice->accountable;
        $amount = (float) $invoice->amount;

        return DB::transaction(function () use ($invoice, $description, $date, $payable, $amount): AccountStatement {
            $creditNote = AccountStatement::create([
                'accountable_id' => $invoice->accountable_id,
                'accountable_type' => $invoice->accountable_type,
                'reference_id' => $invoice->id,
                'amount' => -$amount,
                'description' => $description,
                'date' => $date,
                'debit' => 0,
                'credit' => $amount,
                'balance' => 0,
                'type' => AccountStatementType::CREDIT_NOTE,
                'status' => AccountStatementStatus::Issued,
                'number' => $this->lastNumber($payable, AccountStatementType::CREDIT_NOTE) + 1,
            ]);

            $creditNote->upcomingPayments()->syncWithoutDetaching($invoice->upcomingPayments()->pluck('upcoming_payments.id')->all());
            $invoice->update(['status' => AccountStatementStatus::Credited]);
            $this->walletService->record($payable, AccountStatementType::CREDIT_NOTE, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($creditNote, null);

            $creditNote = $creditNote->refresh();
            event(new CreditNoteCreated($creditNote));

            return $creditNote;
        });
    }

    public function voidInvoice(AccountStatement $invoice, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new FeeCollectionException('Void reason is required');
        }

        $this->ensureCanReverseInvoice($invoice, 'voided');

        if ($invoice->upcomingPayments()->whereHas('receipt')->exists()) {
            throw new FeeCollectionException('Cannot void an invoice that has a receipt');
        }

        /** @var Model&UseFeeable $payable */
        $payable = $invoice->accountable;

        return DB::transaction(function () use ($invoice, $reason, $payable): bool {
            $voided = $invoice->update([
                'voided_at' => Carbon::now(),
                'void_reason' => $reason,
                'status' => AccountStatementStatus::Voided,
            ]);

            $this->walletService->record($payable, AccountStatementType::CREDIT_NOTE, (float) $invoice->amount);
            $this->recalculateBalances($payable);
            event(new InvoiceVoided($invoice->refresh()));

            return $voided;
        });
    }

    /**
     * @param Model&UseFeeable $payable
     * @return Collection<int, AccountStatement>
     */
    public function generateDueInvoices(Model $payable, ?Carbon $date = null): Collection
    {
        $date ??= Carbon::today();

        $payments = UpcomingPayment::query()
            ->wherePayableId($payable->getKey())
            ->wherePayableType($payable::class)
            ->whereDate('due_date', $date->toDateString())
            ->where('remaining_amount', '>', 0)
            ->get()
            ->reject(fn (UpcomingPayment $payment): bool => $payment->invoice()->exists());

        $invoices = new Collection;

        foreach ($payments as $payment) {
            $invoices->push($this->createInvoice($payment, 'Auto-generated invoice for due payment', $date));
        }

        return $invoices;
    }

    /**
     * @param Model&UseFeeable $model
     * @return Collection<int, AccountStatement>
     */
    public function list(Model $model): Collection
    {
        return $model->accountStatements()
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * @param Model&UseFeeable $payable
     */
    public function recalculateBalances(Model $payable): void
    {
        $runningBalance = 0.0;
        $statements = $payable->accountStatements()
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        /** @var AccountStatement $statement */
        foreach ($statements as $statement) {
            if ($statement->status === AccountStatementStatus::Voided) {
                $statement->update(['balance' => $runningBalance]);
                continue;
            }

            $runningBalance += (float) ($statement->debit ?? 0);
            $runningBalance -= (float) ($statement->credit ?? 0);
            $statement->update(['balance' => $runningBalance]);
        }
    }

    /**
     * @param Model&UseFeeable $payable
     */
    private function createReceiptStatement(
        Model $payable,
        float $amount,
        string $description,
        Carbon $date,
        ?string $document
    ): AccountStatement {
        return AccountStatement::create([
            'accountable_id' => $payable->getKey(),
            'accountable_type' => $payable::class,
            'amount' => -$amount,
            'description' => $description,
            'date' => $date,
            'debit' => 0,
            'credit' => $amount,
            'balance' => 0,
            'type' => AccountStatementType::RECEIPT,
            'status' => AccountStatementStatus::Paid,
            'number' => $payable->lastReceiptNumber() + 1,
            'document' => $document,
        ]);
    }

    /**
     * @param array<int, int> $paymentIds
     */
    private function markPaymentsPaid(array $paymentIds): void
    {
        if ($paymentIds === []) {
            return;
        }

        AccountStatement::query()
            ->where('type', AccountStatementType::INVOICE)
            ->whereHas('upcomingPayments', fn ($query) => $query->whereIn('upcoming_payments.id', array_unique($paymentIds)))
            ->update(['status' => AccountStatementStatus::Paid]);
    }

    private function ensureCanReverseInvoice(AccountStatement $invoice, string $action): void
    {
        if ($invoice->type !== AccountStatementType::INVOICE) {
            throw new FeeCollectionException("Only invoices can be {$action}");
        }

        if ($invoice->status === AccountStatementStatus::Voided || $invoice->voided_at !== null) {
            throw new FeeCollectionException('Invoice is already voided');
        }

        if ($invoice->creditNote()->exists()) {
            throw new FeeCollectionException('Invoice already has a credit note');
        }
    }

    /**
     * @param Model&UseFeeable $payable
     */
    private function lastNumber(Model $payable, AccountStatementType $type): int
    {
        return (int) ($payable->accountStatements()
            ->whereType($type)
            ->max('number') ?? 0);
    }

    private function assignDocument(AccountStatement $statement, ?string $document): void
    {
        if (is_string($document) && $document !== '') {
            $statement->update(['document' => $document]);
            return;
        }

        if (!config('fee_collection.pdf.enabled', false)) {
            return;
        }

        $path = $this->statementPdfService->store($statement->fresh(['upcomingPayments', 'accountable']));
        $statement->update(['document' => $path]);
    }
}
