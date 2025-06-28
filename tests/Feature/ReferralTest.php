<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Referrer;
use App\Gateways\ClinicPatientGateway;
use Mockery;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_referral_can_be_successfully_added()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // This is correct. We replace the real gateway with a mock one.
        $mock = Mockery::mock(ClinicPatientGateway::class);
        $this->instance(ClinicPatientGateway::class, $mock);
        
        // Define what the mock should do when called by ReferralService
        // Call #1: `validateReferral` looks for the new patient by mobile.
        $mock->shouldReceive('findPatientByMobile')
             ->once() // Expect it to be called exactly once
             ->with('5553334444') // With this specific mobile number
             ->andReturn((object)[
                 'id' => 102, 'fname_a' => 'Referred', 'lname_a' => 'Patient', 'mobile' => '5553334444'
             ]);

        // Call #2: `createReferral` checks the new patient's spending.
        $mock->shouldReceive('getTotalPaidForPatient')
             ->once() // Expect it to be called once
             ->with(102) // With the ID of the patient we "found" above
             ->andReturn(0); // Return 0 since they are a new patient

        // Create the existing referrer in our test database
        Referrer::create([
            'referrer_patient_id' => 101,
            'referral_code' => 'REF101',
            'referrer_phone' => '5551112222'
        ]);

        // 2. Act
        $response = $this->post(route('referral.eligibility.check'), [
            'referral_code' => 'REF101',
            'mobile' => '5553334444',
            'action' => 'add'
        ]);

        // 3. Assert
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', '✅ Referral successfully added!');
        $this->assertDatabaseHas('referrals', [
            'referrer_patient_id' => 101,
            'referred_patient_id' => 102,
            'status' => 'pending' // Let's be more specific
        ]);
    }
}