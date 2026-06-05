<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Rules\E164PhoneRule;
use App\Services\AppointmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class BookingForm extends Component
{
    public ?int $branch_id = null;

    public ?int $staff_id = null;

    public ?int $service_id = null;

    public string $start_datetime = '';

    public string $customer_name = '';

    public string $customer_email = '';

    public string $customer_phone = '';

    public bool $bookingSuccess = false;

    public string $successMessage = '';

    /**
     * Reset staff selection when branch changes.
     */
    public function updatedBranchId(): void
    {
        $this->staff_id = null;
    }

    /**
     * Get the staff members filtered by the selected branch.
     */
    public function getStaffOptionsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->branch_id) {
            return collect();
        }

        return User::where('role', 'staff')
            ->where('branch_id', $this->branch_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get all available branches.
     */
    public function getBranchOptionsProperty(): \Illuminate\Support\Collection
    {
        return Branch::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get all available services.
     */
    public function getServiceOptionsProperty(): \Illuminate\Support\Collection
    {
        return Service::orderBy('name')->get(['id', 'name', 'duration_minutes']);
    }

    /**
     * Validation rules for the booking form.
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'staff_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:services,id'],
            'start_datetime' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email'],
            'customer_phone' => ['nullable', new E164PhoneRule],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Please select a branch.',
            'staff_id.required' => 'Please select a staff member.',
            'service_id.required' => 'Please select a service.',
            'start_datetime.required' => 'Please select a date and time.',
            'start_datetime.after' => 'Appointment start time must be in the future.',
            'customer_name.required' => 'Please enter your name.',
            'customer_name.max' => 'Name must not exceed 255 characters.',
            'customer_email.email' => 'Please enter a valid email address.',
        ];
    }

    /**
     * Handle form submission.
     *
     * Validates input, looks up or creates a Customer, then calls
     * AppointmentService::create() with full validation. Displays
     * success message or validation errors.
     */
    public function submit(): void
    {
        $this->validate();

        // Validate at least one contact method is provided
        if (empty($this->customer_email) && empty($this->customer_phone)) {
            $this->addError('customer_email', 'At least one contact method (email or phone) is required.');
            $this->addError('customer_phone', 'At least one contact method (email or phone) is required.');

            return;
        }

        // Look up existing Customer by email or phone match, or create a new one
        $customer = $this->lookupOrCreateCustomer();

        // Convert the user's input from branch-local timezone to UTC
        // The datetime-local input gives us a value the user intends as branch-local time
        $branch = Branch::find($this->branch_id);
        $startDatetimeUtc = \Carbon\Carbon::parse($this->start_datetime, $branch->timezone)->utc();

        // Call AppointmentService::create() with all validation
        try {
            $appointmentService = app(AppointmentService::class);

            $appointmentService->create([
                'branch_id' => $this->branch_id,
                'staff_id' => $this->staff_id,
                'customer_id' => $customer->id,
                'service_id' => $this->service_id,
                'start_datetime' => $startDatetimeUtc,
            ]);

            // Success: show confirmation and reset form
            $this->bookingSuccess = true;
            $this->successMessage = 'Your appointment has been booked successfully!';
            $this->resetForm();
        } catch (ValidationException $e) {
            // Display the specific validation errors from AppointmentService
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Look up an existing Customer by email or phone match,
     * or create a new Customer record.
     */
    protected function lookupOrCreateCustomer(): Customer
    {
        $customer = null;

        // Try to find by email first
        if (! empty($this->customer_email)) {
            $customer = Customer::where('email', $this->customer_email)->first();
        }

        // If not found by email, try by phone
        if ($customer === null && ! empty($this->customer_phone)) {
            $customer = Customer::where('phone', $this->customer_phone)->first();
        }

        // If no existing customer found, create a new one
        if ($customer === null) {
            $customer = Customer::create([
                'name' => $this->customer_name,
                'email' => $this->customer_email ?: null,
                'phone' => $this->customer_phone ?: null,
            ]);
        }

        return $customer;
    }

    /**
     * Reset the form fields after successful submission.
     */
    protected function resetForm(): void
    {
        $this->branch_id = null;
        $this->staff_id = null;
        $this->service_id = null;
        $this->start_datetime = '';
        $this->customer_name = '';
        $this->customer_email = '';
        $this->customer_phone = '';
    }

    public function render(): View
    {
        return view('livewire.booking-form');
    }
}
