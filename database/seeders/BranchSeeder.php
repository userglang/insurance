<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::insert([
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'branch_number' => '19',
                'branch_name' => 'OIC - BALINGASAG',
                'address' => 'Madroño Building, Barangay 4, National Highway, Balingasag, Misamis Oriental',
                'code' => 'BL',
                'is_active' => true,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'branch_number' => '20',
                'branch_name' => 'OIC - TUBIGON',
                'address' => 'G/F JRB Building, Sulpicio Falcon St., Purok 2, Potohan, Tubigon, Bohol',
                'code' => 'TUB',
                'is_active' => true,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'branch_number' => '21',
                'branch_name' => 'OIC - ILUSTRE',
                'address' => 'Ilustre, Barangay 4-A, Poblacion District, Davao City, Davao del Sur',
                'code' => 'ILU',
                'is_active' => true,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'branch_number' => '22',
                'branch_name' => 'OIC - TORIL',
                'address' => 'Mac Arthur Highway, Purok 11, Crossing Bayabas, Toril District, Davao',
                'code' => 'TOR',
                'is_active' => true,
            ],

            [
                'id' => \Illuminate\Support\Str::uuid(),
                'branch_number' => '23',
                'branch_name' => 'OIC - BAYUGAN',
                'address' => 'P-22, Narra Avenue Corner Cabonegro St., Brgy Poblacion, Bayugan City',
                'code' => 'BAY',
                'is_active' => true,
            ],
        ]);
    }
}

