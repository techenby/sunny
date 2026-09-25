<?php

use App\Enums\TokenLifetime;
use App\Models\User;

test('never has no expiration', function () {
    expect(TokenLifetime::Never->expiresAt())->toBeNull();
});

test('finite lifetimes expire after their number of days', function (TokenLifetime $lifetime, int $days) {
    $this->freezeSecond();

    expect($lifetime->expiresAt()->equalTo(now()->addDays($days)))->toBeTrue();
})->with([
    [TokenLifetime::ThirtyDays, 30],
    [TokenLifetime::NinetyDays, 90],
    [TokenLifetime::OneYear, 365],
]);

test('the lifetime of a token is read from its timestamps', function (?int $days, TokenLifetime $expected) {
    $this->freezeSecond();
    $token = User::factory()->create()->createToken('Test', ['*'], $days === null ? null : now()->addDays($days))->accessToken;

    expect(TokenLifetime::fromToken($token->fresh()))->toBe($expected);
})->with([
    'never' => [null, TokenLifetime::Never],
    '30 days' => [30, TokenLifetime::ThirtyDays],
    '90 days' => [90, TokenLifetime::NinetyDays],
    '1 year' => [365, TokenLifetime::OneYear],
    'unknown lifetime' => [130, TokenLifetime::ThirtyDays],
]);
