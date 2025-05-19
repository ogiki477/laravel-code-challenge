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
    protected User $otherUser;
    protected DebitCard $debitCard;
    protected DebitCard $otherUserDebitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        $this->otherUserDebitCard = DebitCard::factory()->create([
            'user_id' => $this->otherUser->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // Create transactions for the user's debit card
        $transactions = DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonStructure([
            '*' => ['id', 'amount', 'currency', 'debit_card_id', 'created_at']
        ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // Create transactions for another user's debit card
        DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->otherUserDebitCard->id
        ]);

        $response = $this->getJson('/api/debit-card-transactions');

        $response->assertStatus(200);
        $response->assertJsonCount(0); // Should see none as they belong to other user
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $data = [
            'amount' => 100,
            'currency' => 'USD',
            'debit_card_id' => $this->debitCard->id
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id', 'amount', 'currency', 'debit_card_id', 'created_at'
        ]);
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency' => 'USD'
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $data = [
            'amount' => 100,
            'currency' => 'USD',
            'debit_card_id' => $this->otherUserDebitCard->id
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $this->otherUserDebitCard->id,
            'amount' => 100
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $transaction->id,
            'debit_card_id' => $this->debitCard->id
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->otherUserDebitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCannotCreateTransactionWithInvalidData()
    {
        $data = [
            'amount' => 'not-a-number',
            'currency' => 'USDD', // invalid currency code
            'debit_card_id' => $this->debitCard->id
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount', 'currency']);
    }

    public function testCustomerCannotCreateTransactionForNonexistentDebitCard()
    {
        $nonExistentId = 9999;
        $data = [
            'amount' => 100,
            'currency' => 'USD',
            'debit_card_id' => $nonExistentId
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['debit_card_id']);
    }
}