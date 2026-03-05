<?php

namespace Database\Seeders;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'role' => RoleEnum::SUPER_ADMIN->value,
                'first_name' => 'Timur',
                'last_name' => 'Baltabekov',
                'phone' => '998999999999',
                'password' => '123aral123',
                'gender' => GenderEnum::MALE->value,
                'age' => 26,
                'specialization_id' => null,
                'course' => null,
                'is_verified' => true,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
