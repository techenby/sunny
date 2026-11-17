<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCalendarFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CalendarFeed::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https'],
            'color' => ['required', Rule::enum(CalendarColor::class)],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
