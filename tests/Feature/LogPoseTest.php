<?php

test('page is not visible without token', function () {
    $this->get('/log-pose')
        ->assertStatus(404);
});

test('page is not visible without correct token', function () {
    $this->get('/log-pose?token=asdf')
        ->assertStatus(404);
});

test('page is visible with correct token', function () {
    config(['dashboard.token' => 'cube']);

    $this->withoutExceptionHandling()->get('/log-pose?token=cube')
        ->assertOk();
});
