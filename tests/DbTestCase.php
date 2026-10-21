<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Db;
use PHPUnit\Framework\TestCase;

/**
 * Base for integration tests: a fresh in-memory database with all migrations applied.
 */
abstract class DbTestCase extends TestCase
{
    protected Db $db;

    protected function setUp(): void
    {
        $this->db = Db::sqlite(':memory:');
        $this->db->migrate();
        App::setDb($this->db);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        App::setDb(null);
        $_SESSION = [];
    }

    protected function makeUser(string $username = 'admin', string $role = 'admin'): int
    {
        return (new \Angelma\Repos\Users($this->db))->create([
            'username' => $username,
            'display_name' => ucfirst($username),
            'password_hash' => password_hash('correct horse', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => $role,
        ]);
    }
}
