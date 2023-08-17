<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        $loan = new Loan([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        $loan->save();

        // Create scheduled repayments
        $repaymentAmount = floor($amount / $terms);
        $dueDate = Carbon::parse($processedAt)->addMonths(1)->format('Y-m-d');

        for ($i = 0; $i < $terms; $i++) {
            $currentAmount = $repaymentAmount;
            if($i === $terms-1){
                $currentAmount = $amount - ($terms - 1)*$repaymentAmount;
                $currentAmount = $currentAmount-1;
            }
            $loan->scheduledRepayments()->create([
                'amount' => $currentAmount,
                'outstanding_amount' => $currentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate,
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);
            $dueDate = Carbon::parse($dueDate)->addMonths(1)->format('Y-m-d');
        }

        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        // Refreshing Loan for data consistency.
        $loan->refresh();
        $outstandingAmount = $loan->outstanding_amount - $amount;

        // Update the loan's outstanding amount and status
        $loan->update([
            'outstanding_amount' => $outstandingAmount,
            'status' => ($outstandingAmount === 0) ? Loan::STATUS_REPAID : Loan::STATUS_DUE,
        ]);

        // Create a new ReceivedRepayment record
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt,
        ]);

        // Update the status of related scheduled repayments
        $scheduledRepayments = $loan->scheduledRepayments()
            ->where('status', ScheduledRepayment::STATUS_DUE)
            ->orderBy('due_date')
            ->get();

        foreach ($scheduledRepayments as $scheduledRepayment) {
            if ($amount >= $scheduledRepayment->outstanding_amount) {
                $amount -= $scheduledRepayment->outstanding_amount;
                $scheduledRepayment->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID,
                ]);
            } else {
                if($amount != 0) {
                    $scheduledRepayment->update([
                        'outstanding_amount' => $scheduledRepayment->outstanding_amount - $amount,
                        'status' => ScheduledRepayment::STATUS_PARTIAL,
                    ]);
                    break;
                }
            }
        }
        return $receivedRepayment;
    }
}
