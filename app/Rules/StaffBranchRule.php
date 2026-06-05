<?php

namespace App\Rules;

use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StaffBranchRule implements ValidationRule
{
    public function __construct(
        protected int $staffId,
        protected int $branchId,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $staff = User::find($this->staffId);

        if (! $staff) {
            $fail('The selected staff member does not exist.');
            return;
        }

        if ((int) $staff->branch_id !== $this->branchId) {
            $branch = Branch::find($this->branchId);
            $branchName = $branch?->name ?? "ID {$this->branchId}";
            $fail("Staff member {$staff->name} is not assigned to branch {$branchName}.");
        }
    }
}
