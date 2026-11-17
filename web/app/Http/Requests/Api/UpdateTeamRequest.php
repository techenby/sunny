<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', new TeamName],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'week_start' => ['sometimes', 'required', 'integer', 'between:0,6'],
            'address' => ['sometimes', 'required', 'array:address,city,state,zip,lat,long'],
            'address.address' => ['required_with:address', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:255'],
            'address.state' => ['required_with:address', 'string', 'max:255'],
            'address.zip' => ['required_with:address', 'string', 'max:255'],
            'address.lat' => ['required_with:address', 'numeric', 'between:-90,90'],
            'address.long' => ['required_with:address', 'numeric', 'between:-180,180'],
        ];
    }
}
