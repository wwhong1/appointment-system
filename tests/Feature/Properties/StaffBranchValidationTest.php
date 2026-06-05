<?php

namespace Tests\Feature\Properties;

use App\Models\Branch;
use App\Models\User;
use App\Rules\StaffBranchRule;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 10: Staff-branch assignment validation
 *
 * For any appointment associating a staff member with a branch, the system SHALL accept
 * the appointment if and only if the staff member's branch_id equals the selected branch's id.
 *
 * Validates: Requirements 7.1, 7.2
 */
class StaffBranchValidationTest extends TestCase
{
    use RefreshDatabase;

    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
    }

    private function createBranch(array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'name' => $this->faker->unique()->company(),
            'address' => $this->faker->address(),
            'phone' => '+' . $this->faker->numerify('############'),
            'timezone' => $this->faker->randomElement([
                'Asia/Kuala_Lumpur', 'America/New_York', 'Europe/London',
                'Asia/Tokyo', 'Australia/Sydney', 'Pacific/Auckland',
            ]),
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ], $attributes));
    }

    private function createStaff(Branch $branch, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $branch->id,
        ], $attributes));
    }

    private function runRule(StaffBranchRule $rule): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('staff_id', null, $fail);

        return $errors;
    }

    /**
     * Property 10: Staff-branch assignment validation
     * Validates: Requirements 7.1, 7.2
     *
     * For any staff member assigned to a branch, the StaffBranchRule SHALL accept
     * when the staff member's branch_id equals the selected branch's id.
     */
    public function test_property_staff_branch_rule_accepts_when_branch_matches(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Create a random branch
            $branch = $this->createBranch();

            // Create a staff member assigned to that branch
            $staff = $this->createStaff($branch);

            // The rule should accept when staff's branch_id matches the selected branch
            $rule = new StaffBranchRule($staff->id, $branch->id);
            $errors = $this->runRule($rule);

            $this->assertEmpty(
                $errors,
                "Iteration {$i}: StaffBranchRule should accept when staff (ID: {$staff->id}, branch_id: {$staff->branch_id}) "
                . "is validated against their own branch (ID: {$branch->id}). Errors: " . implode(', ', $errors)
            );
        }
    }

    /**
     * Property 10: Staff-branch assignment validation
     * Validates: Requirements 7.1, 7.2
     *
     * For any staff member assigned to branch A, the StaffBranchRule SHALL reject
     * when the selected branch is branch B (where B != A).
     */
    public function test_property_staff_branch_rule_rejects_when_branch_does_not_match(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Create two different branches
            $assignedBranch = $this->createBranch();
            $selectedBranch = $this->createBranch();

            // Create a staff member assigned to the first branch
            $staff = $this->createStaff($assignedBranch);

            // The rule should reject when staff's branch_id does NOT match the selected branch
            $rule = new StaffBranchRule($staff->id, $selectedBranch->id);
            $errors = $this->runRule($rule);

            $this->assertNotEmpty(
                $errors,
                "Iteration {$i}: StaffBranchRule should reject when staff (ID: {$staff->id}, branch_id: {$staff->branch_id}) "
                . "is validated against a different branch (ID: {$selectedBranch->id})."
            );

            // Verify the error message contains the staff name and branch name
            $this->assertStringContainsString(
                $staff->name,
                $errors[0],
                "Iteration {$i}: Error message should contain the staff member's name."
            );
            $this->assertStringContainsString(
                $selectedBranch->name,
                $errors[0],
                "Iteration {$i}: Error message should contain the selected branch name."
            );
        }
    }

    /**
     * Property 10: Staff-branch assignment validation
     * Validates: Requirements 7.1, 7.2
     *
     * For any random combination of staff and branches, the StaffBranchRule SHALL accept
     * if and only if the staff member's branch_id equals the selected branch's id.
     * This tests the biconditional ("if and only if") nature of the property.
     */
    public function test_property_staff_branch_rule_accepts_iff_branch_matches(): void
    {
        $iterations = 100;

        // Create a pool of branches
        $branches = [];
        $numBranches = $this->faker->numberBetween(3, 6);
        for ($b = 0; $b < $numBranches; $b++) {
            $branches[] = $this->createBranch();
        }

        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random branch to assign the staff to
            $assignedBranch = $this->faker->randomElement($branches);

            // Create a staff member assigned to that branch
            $staff = $this->createStaff($assignedBranch);

            // Pick a random branch to validate against (may or may not match)
            $selectedBranch = $this->faker->randomElement($branches);

            $rule = new StaffBranchRule($staff->id, $selectedBranch->id);
            $errors = $this->runRule($rule);

            $shouldPass = ($staff->branch_id === $selectedBranch->id);

            if ($shouldPass) {
                $this->assertEmpty(
                    $errors,
                    "Iteration {$i}: StaffBranchRule should accept when staff's branch_id ({$staff->branch_id}) "
                    . "equals selected branch id ({$selectedBranch->id}). Errors: " . implode(', ', $errors)
                );
            } else {
                $this->assertNotEmpty(
                    $errors,
                    "Iteration {$i}: StaffBranchRule should reject when staff's branch_id ({$staff->branch_id}) "
                    . "does not equal selected branch id ({$selectedBranch->id})."
                );
            }
        }
    }
}
