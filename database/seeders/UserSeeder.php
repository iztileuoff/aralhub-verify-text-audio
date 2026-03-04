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
                'name' => 'Timur',
                'phone' => '998999999999',
                'password' => '123aral123',
                'gender' => GenderEnum::MALE->value,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
