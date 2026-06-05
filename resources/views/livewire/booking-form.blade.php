<div>
    <h1 class="mb-6 text-2xl font-semibold text-gray-900">Book an Appointment</h1>

    @if ($bookingSuccess)
        <div class="rounded-md bg-green-50 p-4 mb-6" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $successMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{-- Branch Selection --}}
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700">Branch</label>
            <select
                wire:model.live="branch_id"
                id="branch_id"
                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
            >
                <option value="">Select a branch</option>
                @foreach ($this->branchOptions as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Staff Selection (filtered by branch) --}}
        <div>
            <label for="staff_id" class="block text-sm font-medium text-gray-700">Staff Member</label>
            <select
                wire:model="staff_id"
                id="staff_id"
                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                @if (! $branch_id) disabled @endif
            >
                <option value="">{{ $branch_id ? 'Select a staff member' : 'Select a branch first' }}</option>
                @foreach ($this->staffOptions as $staff)
                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                @endforeach
            </select>
            @error('staff_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Service Selection --}}
        <div>
            <label for="service_id" class="block text-sm font-medium text-gray-700">Service</label>
            <select
                wire:model="service_id"
                id="service_id"
                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
            >
                <option value="">Select a service</option>
                @foreach ($this->serviceOptions as $service)
                    <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->duration_minutes }} min)</option>
                @endforeach
            </select>
            @error('service_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Start Datetime --}}
        <div>
            <label for="start_datetime" class="block text-sm font-medium text-gray-700">
                Date & Time
                @if ($branch_id)
                    <span class="text-gray-400">({{ \App\Models\Branch::find($branch_id)?->timezone ?? '' }})</span>
                @endif
            </label>
            <input
                wire:model="start_datetime"
                type="datetime-local"
                id="start_datetime"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
            >
            @error('start_datetime')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('end_datetime')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Customer Name --}}
        <div>
            <label for="customer_name" class="block text-sm font-medium text-gray-700">Your Name</label>
            <input
                wire:model="customer_name"
                type="text"
                id="customer_name"
                maxlength="255"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                placeholder="Enter your full name"
            >
            @error('customer_name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Customer Email --}}
        <div>
            <label for="customer_email" class="block text-sm font-medium text-gray-700">
                Email
                <span class="text-gray-400">(optional)</span>
            </label>
            <input
                wire:model="customer_email"
                type="email"
                id="customer_email"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                placeholder="you@example.com"
            >
            @error('customer_email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Customer Phone --}}
        <div>
            <label for="customer_phone" class="block text-sm font-medium text-gray-700">
                Phone
                <span class="text-gray-400">(optional)</span>
            </label>
            <input
                wire:model="customer_phone"
                type="tel"
                id="customer_phone"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                placeholder="+60123456789"
            >
            @error('customer_phone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-sm text-gray-500">At least one contact method (email or phone) is required.</p>

        {{-- Submit Button --}}
        <div>
            <button
                type="submit"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
            >
                Book Appointment
            </button>
        </div>
    </form>
</div>
