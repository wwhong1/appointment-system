<?php

namespace Tests\Unit\Rules;

use App\Models\Branch;
use App\Models\User;
use App\Rules\StaffBranchRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBranchRuleTest extends TestCase
{
    use RefreshDatabase;

    private function runRule(StaffBranchRule $rule): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('staff_id', null, $fail);

        return $errors;
    }

    private function createBranch(array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'name' => 'Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ], $attributes));
    }

    private function createStaff(Branch $branch, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $branch->id,
        ], $attributes));
    }

    public function test_passes_when_staff_belongs_to_selected_branch(): void
    {
        $branch = $this->createBranch();
        $staff = $this->createStaff($branch);

        $rule = new StaffBranchRule($staff->id, $branch->id);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_fails_when_staff_does_not_belong_to_selected_branch(): void
    {
        $branchA = $this->createBranch(['name' => 'Branch A']);
        $branchB = $this->createBranch(['name' => 'Branch B']);
        $staff = $this->createStaff($branchA, ['name' => 'John Doe']);

        $rule = new StaffBranchRule($staff->id, $branchB->id);
        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('John Doe', $errors[0]);
        $this->assertStringContainsString('Branch B', $errors[0]);
    }

    public function test_fails_when_staff_does_not_exist(): void
    {
        $rule = new StaffBranchRule(9999, 1);
        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function test_error_message_includes_staff_name_and_branch_name(): void
    {
        $branchA = $this->createBranch(['name' => 'Downtown Office']);
        $branchB = $this->createBranch(['name' => 'Uptown Office']);
        $staff = $this->createStaff($branchA, ['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $rule = new StaffBranchRule($staff->id, $branchB->id);
        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertEquals(
            'Staff member Jane Smith is not assigned to branch Uptown Office.',
            $errors[0]
        );
    }
}
