<?php

namespace Tests\Feature\Properties;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 15: Terminal status immutability
 *
 * Validates: Requirements 11.4
 *
 * For any appointment with status in {completed, cancelled, no_show},
 * the system SHALL reject any update to the appointment's fields
 * (start datetime, service, staff, customer, or status).
 */
class TerminalStatusImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private User $alternateStaff;
    private Customer $customer;
    private Customer $alternateCustomer;
    private Service $service;
    private Service $alternateService;
    private AppointmentService $appointmentService;
    private \Faker\Generator $faker;

    /**
     * Terminal statuses that should reject all modifications.
     */
    private const TERMINAL_STATUSES = [
        'completed',
        'cancelled',
        'no_show',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        $this->branch = Branch::create([
            'name' => 'Terminal Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'Terminal Test Staff',
            'email' => 'terminal-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->alternateStaff = User::create([
            'name' => 'Alternate Staff',
            'email' => 'alternate-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Terminal Test Customer',
            'email' => 'terminal-customer@example.com',
        ]);

        $this->alternateCustomer = Customer::create([
            'name' => 'Alternate Customer',
            'email' => 'alternate-customer@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'Terminal Test Service',
            'duration_minutes' => 60,
        ]);

        $this->alternateService = Service::create([
            'name' => 'Alternate Service',
            'duration_minutes' => 30,
        ]);

        $this->appointmentService = new AppointmentService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper: Create an appointment with a specific terminal status.
     */
    private function createAppointmentWithTerminalStatus(string $statusValue): Appointment
    {
        $now = Carbon::create(2025, 1, 1, 10, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $startDatetime = $now->copy()->addDays(1)->setHour(10)->setMinute(0)->setSecond(0);
        $endDatetime = $startDatetime->copy()->addMinutes($this->service->duration_minutes);

        // Create appointment directly in the database with terminal status
        return Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'status' => $statusValue,
        ]);
    }

    /**
     * Property 15: Terminal status appointments reject start_datetime updates.
     *
     * For any appointment with a terminal status (completed, cancelled, no_show)
     * and any random new start_datetime, the AppointmentService::update() SHALL
     * throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_start_datetime_update(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            // Generate a random new start datetime
            $newStart = Carbon::create(2025, 1, 1, 10, 0, 0, 'UTC')
                ->addDays($this->faker->numberBetween(2, 30))
                ->setHour($this->faker->numberBetween(0, 22))
                ->setMinute($this->faker->numberBetween(0, 59))
                ->setSecond(0);

            $exceptionThrown = false;
            try {
                $this->appointmentService->update($appointment, [
                    'start_datetime' => $newStart,
                ]);
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key for terminal status '{$terminalStatus}'"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Updating start_datetime on appointment with terminal status '{$terminalStatus}' "
                . "should throw ValidationException but did not"
            );
        }
    }

    /**
     * Property 15: Terminal status appointments reject service_id updates.
     *
     * For any appointment with a terminal status and any different service,
     * the AppointmentService::update() SHALL throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_service_update(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            $exceptionThrown = false;
            try {
                $this->appointmentService->update($appointment, [
                    'service_id' => $this->alternateService->id,
                ]);
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key for terminal status '{$terminalStatus}'"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Updating service_id on appointment with terminal status '{$terminalStatus}' "
                . "should throw ValidationException but did not"
            );
        }
    }

    /**
     * Property 15: Terminal status appointments reject staff_id updates.
     *
     * For any appointment with a terminal status and any different staff member,
     * the AppointmentService::update() SHALL throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_staff_update(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            $exceptionThrown = false;
            try {
                $this->appointmentService->update($appointment, [
                    'staff_id' => $this->alternateStaff->id,
                ]);
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key for terminal status '{$terminalStatus}'"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Updating staff_id on appointment with terminal status '{$terminalStatus}' "
                . "should throw ValidationException but did not"
            );
        }
    }

    /**
     * Property 15: Terminal status appointments reject customer_id updates.
     *
     * For any appointment with a terminal status and any different customer,
     * the AppointmentService::update() SHALL throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_customer_update(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            $exceptionThrown = false;
            try {
                $this->appointmentService->update($appointment, [
                    'customer_id' => $this->alternateCustomer->id,
                ]);
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key for terminal status '{$terminalStatus}'"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Updating customer_id on appointment with terminal status '{$terminalStatus}' "
                . "should throw ValidationException but did not"
            );
        }
    }

    /**
     * Property 15: Terminal status appointments reject status transitions via transitionStatus().
     *
     * For any appointment with a terminal status and any target status,
     * the AppointmentService::transitionStatus() SHALL throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_status_transitions(): void
    {
        $allStatuses = AppointmentStatus::cases();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            // Pick a random target status (including self-transitions)
            $targetStatus = $this->faker->randomElement($allStatuses);

            $exceptionThrown = false;
            try {
                $this->appointmentService->transitionStatus(
                    $appointment,
                    $targetStatus,
                    $targetStatus === AppointmentStatus::Cancelled ? 'Test cancellation reason' : null
                );
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key when transitioning "
                    . "from terminal status '{$terminalStatus}' to '{$targetStatus->value}'"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Transitioning from terminal status '{$terminalStatus}' to '{$targetStatus->value}' "
                . "should throw ValidationException but did not"
            );
        }
    }

    /**
     * Property 15: Terminal status appointments reject combined field updates.
     *
     * For any appointment with a terminal status and any random combination of
     * field updates, the AppointmentService::update() SHALL throw a ValidationException.
     *
     * **Validates: Requirements 11.4**
     */
    public function test_property_terminal_status_rejects_combined_field_updates(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            $terminalStatus = $this->faker->randomElement(self::TERMINAL_STATUSES);
            $appointment = $this->createAppointmentWithTerminalStatus($terminalStatus);

            // Build a random combination of field updates
            $updateData = [];
            $possibleFields = ['start_datetime', 'service_id', 'staff_id', 'customer_id'];

            // Pick at least one field, up to all fields
            $fieldsToUpdate = $this->faker->randomElements(
                $possibleFields,
                $this->faker->numberBetween(1, count($possibleFields))
            );

            foreach ($fieldsToUpdate as $field) {
                switch ($field) {
                    case 'start_datetime':
                        $updateData['start_datetime'] = Carbon::create(2025, 1, 1, 10, 0, 0, 'UTC')
                            ->addDays($this->faker->numberBetween(2, 30))
                            ->setHour($this->faker->numberBetween(0, 22))
                            ->setMinute($this->faker->numberBetween(0, 59))
                            ->setSecond(0);
                        break;
                    case 'service_id':
                        $updateData['service_id'] = $this->alternateService->id;
                        break;
                    case 'staff_id':
                        $updateData['staff_id'] = $this->alternateStaff->id;
                        break;
                    case 'customer_id':
                        $updateData['customer_id'] = $this->alternateCustomer->id;
                        break;
                }
            }

            $exceptionThrown = false;
            try {
                $this->appointmentService->update($appointment, $updateData);
            } catch (ValidationException $e) {
                $exceptionThrown = true;
                $this->assertArrayHasKey('status', $e->errors(),
                    "Iteration {$i}: ValidationException should have 'status' key for terminal status '{$terminalStatus}' "
                    . "when updating fields: " . implode(', ', $fieldsToUpdate)
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "Iteration {$i}: Updating fields [" . implode(', ', $fieldsToUpdate) . "] "
                . "on appointment with terminal status '{$terminalStatus}' "
                . "should throw ValidationException but did not"
            );
        }
    }
}
