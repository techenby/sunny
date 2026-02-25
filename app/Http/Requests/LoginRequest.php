<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\InvitationEmailMatch;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            Fortify::username() => ['required', 'string', new InvitationEmailMatch],
            'password' => ['required', 'string'],
        ];
    }
}
