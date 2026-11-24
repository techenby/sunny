<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\CalendarColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCalendarFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('calendarFeed'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'url:http,https', 'max:2048'],
            'color' => ['sometimes', 'required', Rule::enum(CalendarColor::class)],
        ];
    }
}
