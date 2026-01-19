<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * รันทั้งหมดด้วยคำสั่ง: php artisan db:seed --force
     */
    public function run(): void
    {
        // สร้าง Test User
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // 🔥 Import Seeders ตามลำดับ Dependencies
        $this->call([
            // 1. ข้อมูลพื้นฐาน (ไม่มี dependencies)
            ImportToolTypesSeeder::class,
            ImportMastersSeeder::class,

            // 2. Instruments (ต้องการ tool_types)
            ImportInstrumentsSeeder::class,

            // 3. ความสัมพันธ์ (ต้องการ tool_types, masters, instruments)
            ImportStandardUsagesSeeder::class,
            ImportStatusHistorySeeder::class,

            // 4. Calibration Logs (ต้องการ instruments, tool_types)
            ImportCALKNewSeeder::class,
            ImportCalPlugSeeder::class,
            ImportCALPressureSeeder::class,
            ImportCalSerPlSeeder::class,
            ImportCALSNAPSeeder::class,
            ImportCalThreadPlugGaugeFitWearSeeder::class,
            ImportCalThreadPlugSeeder::class,
            ImportCalThreadRingSeeder::class,
            ImportCalVernierCaliperDigitalSeeder::class,
            ImportCalVernierOtherSeeder::class,
        ]);

        // 🔥 Clear caches และ compile views หลัง seed เสร็จ
        $this->command->info('');
        $this->command->info('🧹 กำลัง Clear Caches และ Compile Views...');
        
        Artisan::call('config:clear');
        $this->command->info('✅ Config cleared');
        
        Artisan::call('cache:clear');
        $this->command->info('✅ Cache cleared');
        
        Artisan::call('view:cache');
        $this->command->info('✅ Views compiled');
        
        $this->command->info('');
        $this->command->info('🎉 Seeding เสร็จสมบูรณ์!');
    }
}
