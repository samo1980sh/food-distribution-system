<?php

namespace App\Http\Requests\Api\V1\Operational;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldTodayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasRole(User::ROLE_SALES_REPRESENTATIVE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => [
                'sometimes',
                'nullable',
                'required_with:route_id',
                Rule::in([User::ROLE_SALES_REPRESENTATIVE]),
            ],
            'route_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('distribution_routes', 'id')->where('status', 'active'),
            ],
        ];
    }
}
