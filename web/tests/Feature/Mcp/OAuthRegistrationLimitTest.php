<?php

test('one network can only register so many apps a minute', function () {
    $register = fn () => $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ]);

    foreach (range(1, 5) as $attempt) {
        $register()->assertCreated();
    }

    $register()->assertTooManyRequests();
});
