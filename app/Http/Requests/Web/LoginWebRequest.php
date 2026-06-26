<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use App\Rules\UzPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginWebRequest extends FormRequest
{
    use NormalizesPhoneNumber;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new UzPhoneRule],
            'password' => ['required', 'string', 'max:254'],
        ];
    }
}
