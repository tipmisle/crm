<?php

$routes = ['legal.terms', 'legal.privacy', 'legal.cookies', 'legal.dpa', 'legal.provider', 'legal.subprocessors'];

foreach ($routes as $routeName) {
    test("{$routeName} never leaks secrets", function () use ($routeName) {
        $response = $this->get(route($routeName));

        $response->assertOk();

        $body = $response->getContent();

        expect($body)->not->toContain(config('app.key'));

        if (filled(env('META_APP_SECRET'))) {
            expect($body)->not->toContain((string) env('META_APP_SECRET'));
        }

        expect($body)->not->toContain('$2y$'); // bcrypt password hash prefix
    });
}
