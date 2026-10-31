<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use NormalizesPhoneNumber;

    public function rules(): array
    {
        return [
            'role' => [Rule::in($this->grantableRoleValues())],
            'first_name' => ['string', 'max:255'],
            'last_name' => ['string', 'max:255'],
            'phone' => [new UzPhoneRule, Rule::unique('users', 'phone')->ignore($this->user->id)],
            'password' => ['string', 'min:8', 'max:64'],
            'gender' => [Rule::in([GenderEnum::MALE->value, GenderEnum::FEMALE->value])],
            'age' => ['integer', 'min:1', 'max:100'],
            'specialization_id' => [Rule::exists('specializations', 'id')],
            'course' => ['nullable', 'integer'],
            'is_verified' => ['boolean'],
            'admin_id' => $this->adminIdRules(),
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * The roles the signed-in user may give: an admin never grants the admin
     * role, nobody grants the super admin.
     *
     * @return array<int, int>
     */
    private function grantableRoleValues(): array
    {
        return array_map(
            fn (RoleEnum $role): int => $role->value,
            $this->user()?->role?->grantableRoles() ?? [],
        );
    }

    /**
     * An admin keeps their users to themselves; only the super admin moves
     * users between admins.
     *
     * @return array<int, mixed>
     */
    private function adminIdRules(): array
    {
        if ($this->user()?->role === RoleEnum::ADMIN) {
            return ['sometimes', Rule::in([$this->user()->id])];
        }

        return ['nullable', Rule::exists('users', 'id')->where('role', RoleEnum::ADMIN->value)];
    }
}
