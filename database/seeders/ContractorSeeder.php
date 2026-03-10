<?php

namespace Database\Seeders;

use App\Models\Contractor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContractorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Contractor::create([
            'name' => 'Mr. Contractor',
            'email' => 'contractor@gmail.com',
            'phone' => '7733844020',
            'whatsapp_phone' => '7733844020',
            'password' => 'contractor@123',
        ]);
    }
}
