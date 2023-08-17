<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        $transactions = DebitCardTransaction::factory()->count(5)->create(['debit_card_id' => $this->debitCard->id]);
        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$this->debitCard->id}");
        $response->assertStatus(200)->assertJsonCount(5);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $user2 = User::factory()->create();
        // Creating debit card for user2.
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $user2->id]);

        // transactions for the user2's debit card.
        $transactions = DebitCardTransaction::factory()->count(5)->create(['debit_card_id' => $otherDebitCard->id]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$otherDebitCard->id}");
        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 2000,
            'currency_code' => DebitCardTransaction::CURRENCY_VND,
        ];
        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => $data['amount'],
            'currency_code' => DebitCardTransaction::CURRENCY_VND,
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // Create another user and their debit card
        $user2 = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $user2->id]);

        // Data for the transaction on the user2's debit card
        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 2000,
            'currency_code' => DebitCardTransaction::CURRENCY_VND,
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => $data['amount'],
            'currency_code' => DebitCardTransaction::CURRENCY_VND,
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->create(['debit_card_id' => $this->debitCard->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'currency_code' => $transaction->currency_code,
            'amount' => $transaction->amount,
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $user2 = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $user2->id]);

        $transaction = DebitCardTransaction::factory()->create(['debit_card_id' => $otherDebitCard->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }
}
