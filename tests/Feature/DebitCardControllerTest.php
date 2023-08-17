<?php

namespace Tests\Feature;

use App\Models\DebitCardTransaction;
use App\Models\User;
use App\Models\DebitCard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        Passport::actingAs($this->user);

        // Creating Debit Cards
        $debitCards = DebitCard::factory()
            ->count(8)
            ->active() // Apply the "active" state to set is_active to true
            ->create(['user_id' => $this->user->id]);

        // API Call
        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200)
            // Making sure that number of Debit Cards in the list is equal to number of Debit Cards we created.
            ->assertJsonCount(8)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ]
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // Creating 2 different users.
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $debitCardsUser1 = DebitCard::factory()
            ->count(8)
            ->active()
            ->create(['user_id' =>$user1->id]);

        $debitCardsUser2 = DebitCard::factory()
            ->count(5)
            ->active()
            ->create(['user_id' => $user2->id]);

        // Authenticating as User1
        Passport::actingAs($user1);

        // Attempt to retrieve the list of debit cards for user2
        $response = $this->getJson("/api/debit-cards?user_id=$user2->id");

        $response->assertStatus(200);

        // Assert that the response contains only the debit cards of user1
        $response->assertStatus(200)
            // Making sure that number of Debit Cards in the list is equal to number of Debit Cards
            // we created for user1 and not user2
            ->assertJsonCount(8)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ]
            ]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        Passport::actingAs($this->user);
        $debitCardData = [
            'type' => 'EmployeeDebitCard', // Replace with appropriate data
        ];

        $response = $this->postJson('/api/debit-cards', $debitCardData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]);

        $responseData = $response->json();
        $this->assertEquals('EmployeeDebitCard', $responseData['type']);
        $this->assertGreaterThanOrEqual(1000000000000000, (int) $responseData['number']);
        $this->assertLessThanOrEqual(9999999999999999, (int) $responseData['number']);
        $this->assertTrue(Carbon::now()->lessThan(Carbon::parse($responseData['expiration_date'])));
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // Create a debit card for the user
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        Passport::actingAs($this->user);

        // API Call to retrieve the details of the created debit card
        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]);
    }


    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // Creating a different user.
        $anotherUser = User::factory()->create();

        // Creating Debit Card for original user.
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $anotherUser->id]);

        // Authenticate as the original user
        Passport::actingAs($this->user);

        // API Call to attempt to fetch the details of the debit card of the original user.
        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }


    public function testCustomerCanActivateADebitCard()
    {
        // Create an inactive debit card for the user
        $debitCard = DebitCard::factory()->expired()->create(['user_id' => $this->user->id]);

        Passport::actingAs($this->user);

        // API Call to activate the debit card
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['is_active']);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // Create an active debit card for the user
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        Passport::actingAs($this->user);

        // API Call to deactivate the debit card
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => false]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['is_active']);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // Create an active debit card for the user with specific type "Visa"
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
            'type' => 'Visa',
        ]);

        Passport::actingAs($this->user);

        // API Call to update the debit card type, instead of activating or deactivating the debit card.
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['type' => 'Rupay']);

        // response is rightfully unprocessable entity.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        Passport::actingAs($this->user);

        // API Call to delete the debit card
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);

        // Verify that the debit card was deleted from the database
        $this->assertSoftDeleted('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        // Create a transaction associated with the debit card
        $transaction = DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        Passport::actingAs($this->user);

        // API Call to delete the debit card
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);

        // Verify that the debit card still exists in the database
        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id]);

        // Verify that the transaction still exists in the database
        $this->assertDatabaseHas('debit_card_transactions', ['id' => $transaction->id]);
    }

    // EXTRA TEST CASES

    public function testCustomerCannotUpdateADebitCardOfOtherCustomers()
    {
        // Create a different user and their debit card
        $anotherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $anotherUser->id]);

        Passport::actingAs($this->user);

        // Attempt to update the other user's debit card
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }

    public function testCustomerCannotDeleteDebitCardOfOtherCustomer()
    {
        // Create a different user and their debit card
        $anotherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $anotherUser->id]);

        // Authenticate as the current user
        Passport::actingAs($this->user);

        // Attempt to delete the other user's debit card
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

}
