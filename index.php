<?php

declare(strict_types=1);

/*
 * Front controller: every request that is not a static file comes here.
 */

require __DIR__ . '/app/bootstrap.php';

Angelma\App::handle(Angelma\Request::fromGlobals())->send();
