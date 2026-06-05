<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- Branches ---
        $branches = [
            Branch::create([
                'name' => 'Downtown KL',
                'address' => '123 Jalan Bukit Bintang, Kuala Lumpur 55100',
                'phone' => '+60321234567',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ]),
            Branch::create([
                'name' => 'Petaling Jaya',
                'address' => '45 Jalan SS2/55, Petaling Jaya 47300',
                'phone' => '+60379876543',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '10:00',
                'closing_time' => '20:00',
            ]),
            Branch::create([
                'name' => 'Penang Georgetown',
                'address' => '78 Lebuh Chulia, Georgetown 10200',
                'phone' => '+60342223344',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '08:30',
                'closing_time' => '17:30',
            ]),
        ];

        // --- Services ---
        $services = [
            Service::create([
                'name' => 'Haircut',
                'duration_minutes' => 30,
                'price' => 35.00,
                'description' => 'Standard haircut with wash and style.',
            ]),
            Service::create([
                'name' => 'Hair Coloring',
                'duration_minutes' => 90,
                'price' => 120.00,
                'description' => 'Full hair coloring with premium products.',
            ]),
            Service::create([
                'name' => 'Facial Treatment',
                'duration_minutes' => 60,
                'price' => 80.00,
                'description' => 'Deep cleansing facial with moisturizing mask.',
            ]),
            Service::create([
                'name' => 'Manicure & Pedicure',
                'duration_minutes' => 45,
                'price' => 55.00,
                'description' => 'Nail care with polish and hand massage.',
            ]),
            Service::create([
                'name' => 'Full Body Massage',
                'duration_minutes' => 120,
                'price' => 150.00,
                'description' => 'Relaxing full body massage with essential oils.',
            ]),
            Service::create([
                'name' => 'Quick Trim',
                'duration_minutes' => 15,
                'price' => 15.00,
                'description' => 'Quick trim for minor adjustments.',
            ]),
        ];

        // --- Admin User ---
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'branch_id' => null,
        ]);

        // --- Staff Users ---
        $staff = [
            User::create([
                'name' => 'Alice Tan',
                'email' => 'alice@example.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => $branches[0]->id, // Downtown KL
            ]),
            User::create([
                'name' => 'Bob Lee',
                'email' => 'bob@example.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => $branches[0]->id, // Downtown KL
            ]),
            User::create([
                'name' => 'Carol Wong',
                'email' => 'carol@example.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => $branches[1]->id, // Petaling Jaya
            ]),
            User::create([
                'name' => 'David Lim',
                'email' => 'david@example.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => $branches[1]->id, // Petaling Jaya
            ]),
            User::create([
                'name' => 'Emily Ng',
                'email' => 'emily@example.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => $branches[2]->id, // Penang
            ]),
        ];

        // --- Customers ---
        $customers = [
            Customer::create([
                'name' => 'Ahmad bin Ibrahim',
                'email' => 'ahmad@gmail.com',
                'phone' => '+60121234567',
            ]),
            Customer::create([
                'name' => 'Siti Nurhaliza',
                'email' => 'siti@gmail.com',
                'phone' => '+60139876543',
            ]),
            Customer::create([
                'name' => 'Raj Kumar',
                'email' => null,
                'phone' => '+60145551234',
            ]),
            Customer::create([
                'name' => 'Mei Ling',
                'email' => 'meiling@yahoo.com',
                'phone' => null,
            ]),
            Customer::create([
                'name' => 'John Smith',
                'email' => 'john.smith@outlook.com',
                'phone' => '+60167778899',
            ]),
            Customer::create([
                'name' => 'Fatimah Zahra',
                'email' => 'fatimah@gmail.com',
                'phone' => '+60183334455',
            ]),
        ];

        // --- Appointments ---
        $now = Carbon::now();

        // Future appointments (pending/confirmed)
        Appointment::create([
            'branch_id' => $branches[0]->id,
            'staff_id' => $staff[0]->id, // Alice
            'customer_id' => $customers[0]->id,
            'service_id' => $services[0]->id, // Haircut 30min
            'start_datetime' => $now->copy()->addDays(1)->setTime(1, 0, 0), // 09:00 local
            'end_datetime' => $now->copy()->addDays(1)->setTime(1, 30, 0),  // 09:30 local
            'status' => AppointmentStatus::Pending->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[0]->id,
            'staff_id' => $staff[0]->id, // Alice
            'customer_id' => $customers[1]->id,
            'service_id' => $services[2]->id, // Facial 60min
            'start_datetime' => $now->copy()->addDays(1)->setTime(2, 0, 0), // 10:00 local
            'end_datetime' => $now->copy()->addDays(1)->setTime(3, 0, 0),   // 11:00 local
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[0]->id,
            'staff_id' => $staff[1]->id, // Bob
            'customer_id' => $customers[2]->id,
            'service_id' => $services[1]->id, // Hair Coloring 90min
            'start_datetime' => $now->copy()->addDays(1)->setTime(3, 0, 0), // 11:00 local
            'end_datetime' => $now->copy()->addDays(1)->setTime(4, 30, 0),  // 12:30 local
            'status' => AppointmentStatus::Pending->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[1]->id,
            'staff_id' => $staff[2]->id, // Carol
            'customer_id' => $customers[3]->id,
            'service_id' => $services[3]->id, // Manicure 45min
            'start_datetime' => $now->copy()->addDays(2)->setTime(4, 0, 0), // 12:00 local
            'end_datetime' => $now->copy()->addDays(2)->setTime(4, 45, 0),  // 12:45 local
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[1]->id,
            'staff_id' => $staff[3]->id, // David
            'customer_id' => $customers[4]->id,
            'service_id' => $services[4]->id, // Massage 120min
            'start_datetime' => $now->copy()->addDays(2)->setTime(6, 0, 0), // 14:00 local
            'end_datetime' => $now->copy()->addDays(2)->setTime(8, 0, 0),   // 16:00 local
            'status' => AppointmentStatus::Pending->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[2]->id,
            'staff_id' => $staff[4]->id, // Emily
            'customer_id' => $customers[5]->id,
            'service_id' => $services[0]->id, // Haircut 30min
            'start_datetime' => $now->copy()->addDays(3)->setTime(1, 0, 0), // 09:00 local
            'end_datetime' => $now->copy()->addDays(3)->setTime(1, 30, 0),  // 09:30 local
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Past appointments (completed/cancelled/no-show)
        Appointment::create([
            'branch_id' => $branches[0]->id,
            'staff_id' => $staff[0]->id, // Alice
            'customer_id' => $customers[4]->id,
            'service_id' => $services[0]->id, // Haircut
            'start_datetime' => $now->copy()->subDays(2)->setTime(1, 0, 0),
            'end_datetime' => $now->copy()->subDays(2)->setTime(1, 30, 0),
            'status' => AppointmentStatus::Completed->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[0]->id,
            'staff_id' => $staff[1]->id, // Bob
            'customer_id' => $customers[0]->id,
            'service_id' => $services[2]->id, // Facial
            'start_datetime' => $now->copy()->subDays(1)->setTime(2, 0, 0),
            'end_datetime' => $now->copy()->subDays(1)->setTime(3, 0, 0),
            'status' => AppointmentStatus::Cancelled->value,
            'cancellation_reason' => 'Customer requested reschedule due to personal emergency.',
        ]);

        Appointment::create([
            'branch_id' => $branches[1]->id,
            'staff_id' => $staff[2]->id, // Carol
            'customer_id' => $customers[1]->id,
            'service_id' => $services[3]->id, // Manicure
            'start_datetime' => $now->copy()->subDays(1)->setTime(4, 0, 0),
            'end_datetime' => $now->copy()->subDays(1)->setTime(4, 45, 0),
            'status' => AppointmentStatus::NoShow->value,
        ]);

        Appointment::create([
            'branch_id' => $branches[2]->id,
            'staff_id' => $staff[4]->id, // Emily
            'customer_id' => $customers[3]->id,
            'service_id' => $services[5]->id, // Quick Trim
            'start_datetime' => $now->copy()->subDays(3)->setTime(0, 30, 0),
            'end_datetime' => $now->copy()->subDays(3)->setTime(0, 45, 0),
            'status' => AppointmentStatus::Completed->value,
        ]);

        $this->command->info('Seeded: 3 branches, 6 services, 1 admin, 5 staff, 6 customers, 10 appointments');
    }
}
