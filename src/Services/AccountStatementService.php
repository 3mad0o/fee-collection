<?php

namespace Emad\FeeCollection\Services;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Contracts\WalletServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
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

    /**
     * Creates an invoice statement and links it to the upcoming payment.
     */
    public function createInvoice(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null
    ): AccountStatement
    {
        $date ??= Carbon::now();
        if ($upcomingPayment->invoice()->exists()) {
            throw new FeeCollectionException('Invoice already created');
        }

        /** @var UseFeeable $payable */
        $payable = $upcomingPayment->payable;
        $amount = (float) $upcomingPayment->amount;

        return DB::transaction(function () use ($upcomingPayment, $description, $date, $document, $payable, $amount) {
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
                'number' => $payable->lastInvoiceNumber() + 1,
                'document' => $document,
            ]);

            $accountStatement->upcomingPayments()->syncWithoutDetaching([$upcomingPayment->id]);
            $this->walletService->record($payable, AccountStatementType::INVOICE, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            return $accountStatement->refresh();
        });
    }

    /**
     * Creates a receipt against one upcoming payment and closes it.
     */
    public function createUpcomingPaymentReceipt(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null
    ): AccountStatement
    {
        $date ??= Carbon::now();
        if ($upcomingPayment->receipt()->exists()) {
            throw new FeeCollectionException('Receipt already created');
        }

        /** @var UseFeeable $payable */
        $payable = $upcomingPayment->payable;
        $amount = (float) $upcomingPayment->remaining_amount;
        if ($amount <= 0) {
            throw new FeeCollectionException('Upcoming payment already closed');
        }

        return DB::transaction(function () use ($upcomingPayment, $description, $date, $document, $payable, $amount) {
            $accountStatement = AccountStatement::create([
                'accountable_id' => $upcomingPayment->payable_id,
                'accountable_type' => $upcomingPayment->payable_type,
                'amount' => $amount,
                'description' => $description,
                'date' => $date,
                'debit' => 0,
                'credit' => $amount,
                'balance' => 0,
                'type' => AccountStatementType::RECEIPT,
                'number' => $payable->lastReceiptNumber() + 1,
                'document' => $document,
            ]);

            $upcomingPayment->update([
                'remaining_amount' => 0,
            ]);

            if (!$upcomingPayment->invoice()->exists()) {
                $this->createInvoice($upcomingPayment, 'Auto-generated invoice during receipt settlement', $date, $document);
            }

            $accountStatement->upcomingPayments()->syncWithoutDetaching([$upcomingPayment->id]);
            $this->walletService->record($payable, AccountStatementType::RECEIPT, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            return $accountStatement->refresh();
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
        ?string $document = null
    ): AccountStatement {
        $date ??= Carbon::now();
        if ($amount <= 0) {
            throw new FeeCollectionException('Amount must be greater than zero');
        }

        return DB::transaction(function () use ($payable, $amount, $description, $date, $document) {
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

                if (!$upcomingPayment->invoice()->exists()) {
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

            $accountStatement = AccountStatement::create([
                'accountable_id' => $payable->getKey(),
                'accountable_type' => $payable::class,
                'amount' => $amount,
                'description' => $description,
                'date' => $date,
                'debit' => 0,
                'credit' => $amount,
                'balance' => 0,
                'type' => AccountStatementType::RECEIPT,
                'number' => $payable->lastReceiptNumber() + 1,
                'document' => $document,
            ]);

            $accountStatement->upcomingPayments()->syncWithoutDetaching(array_unique($upcomingPaymentIds));
            $this->walletService->record($payable, AccountStatementType::RECEIPT, $amount);
            $this->recalculateBalances($payable);
            $this->assignDocument($accountStatement, $document);

            return $accountStatement->refresh();
        });
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
            $runningBalance += (float) ($statement->debit ?? 0);
            $runningBalance -= (float) ($statement->credit ?? 0);
            $statement->update(['balance' => $runningBalance]);
        }
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
