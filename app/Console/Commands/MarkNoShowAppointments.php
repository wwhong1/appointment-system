<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class MarkNoShowAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:mark-no-show';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark confirmed appointments as no-show when 15 minutes have elapsed after start time';

    /**
     * Execute the console command.
     */
    public function handle(AppointmentService $appointmentService): int
    {
        $cutoff = Carbon::now()->subMinutes(15);

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('start_datetime', '>', $cutoff)
            ->get();

        $marked = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($appointments as $appointment) {
            // Re-check status in case it changed since the query
            $freshAppointment = $appointment->fresh();

            if ($freshAppointment->status !== AppointmentStatus::Confirmed) {
                $skipped++;
                Log::info("MarkNoShow: Skipped appointment #{$freshAppointment->id} - status changed to {$freshAppointment->status->value}");
                continue;
            }

            try {
                $appointmentService->transitionStatus($freshAppointment, AppointmentStatus::NoShow);
                $marked++;
                Log::info("MarkNoShow: Marked appointment #{$freshAppointment->id} as no_show");
            } catch (\Throwable $e) {
                $failed++;
                Log::error("MarkNoShow: Failed to mark appointment #{$freshAppointment->id} as no_show - {$e->getMessage()}");
            }
        }

        $this->info("No-show processing complete: {$marked} marked, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->everyFiveMinutes();
    }
}
