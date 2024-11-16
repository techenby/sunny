<?php

test('home', function () {
    $this->get('/')
        ->assertOk();
});
