<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            [
                'name' => 'Filologiya hám tillerdi oqıtıw (ózbek tili)',
            ],
            [
                'name' => 'Filologiya hám tillerdi oqıtıw (qazaq tili)',
            ],
        ];

        foreach ($specializations as $specialization) {
            Specialization::create($specialization);
        }
    }
}
