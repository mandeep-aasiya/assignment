<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */

    public function definition(): array
    {
        return [
            'amount' => $this->faker->numberBetween(0, 2147483647),
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_VND, Loan::CURRENCY_SGD]),
            'due_date' => $this->faker->date(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (ScheduledRepayment $scheduledRepayment) {
            $amount = $scheduledRepayment->amount;
            $status = $scheduledRepayment->status;

            switch ($status) {
                case ScheduledRepayment::STATUS_REPAID:
                    $outstanding_amount = 0;
                    break;
                case ScheduledRepayment::STATUS_PARTIAL:
                    $outstanding_amount = $amount;
                    break;
                case ScheduledRepayment::STATUS_DUE:
                default:
                    $outstanding_amount = $amount;
                    break;
            }

            $scheduledRepayment->outstanding_amount = $outstanding_amount;

            $loan = $scheduledRepayment->loan;
            if ($loan && $status == ScheduledRepayment::STATUS_REPAID) {
                $loan->outstanding_amount = (int)$loan->outstanding_amount - $amount;
                $loan->save();
            }
        });
    }
}
