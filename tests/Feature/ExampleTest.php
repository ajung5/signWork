<?php

test('guest is redirected to login from dashboard', function () {
    $response = $this->get(
        route('dashboard')
    );

    $response->assertRedirect(
        route('login')
    );
});
