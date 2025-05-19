<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // Create some debit cards for the user
        $debitCards = DebitCard::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonStructure([
            '*' => ['id', 'number', 'type', 'expiration_date', 'is_active']
        ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // Create debit cards for another user
        DebitCard::factory()->count(3)->create([
            'user_id' => $this->otherUser->id
        ]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(0); // Should see none as they belong to other user
    }

    public function testCustomerCanCreateADebitCard()
    {
        $data = [
            'type' => 'visa',
        ];

        $response = $this->postJson('/api/debit-cards', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id', 'number', 'type', 'expiration_date', 'is_active'
        ]);
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
            'is_active' => false
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $debitCard->id,
            'user_id' => $this->user->id
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->otherUser->id
        ]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false
        ]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => true
        ]);

        $response->assertStatus(200);
        $response->assertJson(['is_active' => true]);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'is_active' => true
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true
        ]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => false
        ]);

        $response->assertStatus(200);
        $response->assertJson(['is_active' => false]);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'is_active' => false
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        // Test invalid is_active (not boolean)
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => 'not-a-boolean'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('debit_cards', [
            'id' => $debitCard->id
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id
        ]);
    }

    public function testCustomerCannotUpdateOtherCustomersDebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->otherUser->id
        ]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", [
            'is_active' => true
        ]);

        $response->assertStatus(403);
    }

    public function testCustomerCannotDeleteOtherCustomersDebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->otherUser->id
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }
}