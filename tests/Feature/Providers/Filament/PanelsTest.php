<?php

it('renders the login page', function (string $panel) {
    $this->get("/{$panel}/login")->assertOk();
})->with(['admin', 'app']);

it('redirects guests to the panel login page', function (string $panel) {
    $this->get("/{$panel}")->assertRedirect("/{$panel}/login");
})->with(['admin', 'app']);
