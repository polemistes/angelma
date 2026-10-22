<?php

declare(strict_types=1);

namespace Angelma\Tests;

use Angelma\App;
use Angelma\Auth;
use Angelma\HttpException;
use Angelma\Request;

final class AuthTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Auth::reset();
        App::$request = null;
    }

    protected function tearDown(): void
    {
        Auth::reset();
        App::$request = null;
        parent::tearDown();
    }

    private function request(string $ip = '10.0.0.1'): Request
    {
        return new Request('POST', '/login', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    public function testCorrectLoginSetsSession(): void
    {
        $id = $this->makeUser('anna', 'editor');
        $result = Auth::attempt($this->request(), 'Anna', 'correct horse');
        $this->assertIsArray($result);
        $this->assertSame($id, $_SESSION['user_id']);
        $this->assertSame($id, Auth::id());
    }

    public function testWrongPasswordAndUnknownUser(): void
    {
        $this->makeUser('anna', 'editor');
        $this->assertSame('invalid', Auth::attempt($this->request(), 'anna', 'wrong'));
        $this->assertSame('invalid', Auth::attempt($this->request(), 'nobody', 'x'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testInactiveUserCannotLogIn(): void
    {
        $id = $this->makeUser('anna', 'editor');
        (new \Angelma\Repos\Users($this->db))->update($id, ['is_active' => 0]);
        $this->assertSame('invalid', Auth::attempt($this->request(), 'anna', 'correct horse'));
    }

    public function testFiveFailuresForAUsernameBlockTheSixthAttempt(): void
    {
        $this->makeUser('anna', 'editor');
        $t = 1_800_000_000;
        for ($i = 0; $i < 5; $i++) {
            // Different IPs, so only the username key counts.
            $this->assertSame('invalid', Auth::attempt($this->request('10.0.0.' . $i), 'anna', 'wrong', $t + $i));
        }
        $this->assertSame('blocked', Auth::attempt($this->request('10.0.0.9'), 'anna', 'wrong', $t + 5));
        // Even the right password is refused while blocked.
        $this->assertSame('blocked', Auth::attempt($this->request('10.0.1.1'), 'anna', 'correct horse', $t + 10));
        // 15 minutes after the fifth failure the block is lifted.
        $this->assertIsArray(Auth::attempt($this->request('10.0.1.1'), 'anna', 'correct horse', $t + 4 + 901));
    }

    public function testFiveFailuresFromAnIpBlockOtherUsernames(): void
    {
        $this->makeUser('anna', 'editor');
        $t = 1_800_000_000;
        foreach (['a1', 'a2', 'a3', 'a4', 'a5'] as $i => $name) {
            Auth::attempt($this->request(), $name, 'x', $t + $i);
        }
        $this->assertSame('blocked', Auth::attempt($this->request(), 'anna', 'correct horse', $t + 6));
        $this->assertIsArray(Auth::attempt($this->request('10.9.9.9'), 'anna', 'correct horse', $t + 7));
    }

    public function testOldAttemptsAreDeleted(): void
    {
        $t = 1_800_000_000;
        Auth::attempt($this->request(), 'x', 'y', $t);
        Auth::attempt($this->request(), 'x', 'y', $t + 3601);
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM login_attempts'));
    }

    public function testRoleLevels(): void
    {
        $this->assertTrue(Auth::satisfies('admin', 'editor'));
        $this->assertTrue(Auth::satisfies('admin', 'user'));
        $this->assertTrue(Auth::satisfies('editor', 'editor'));
        $this->assertFalse(Auth::satisfies('editor', 'admin'));
        $this->assertFalse(Auth::satisfies('user', 'editor'));
        $this->assertFalse(Auth::satisfies('', 'user'));
        $this->assertFalse(Auth::satisfies('admin', 'nonsense'));
    }

    public function testRequireRoleRedirectsAnonymousToLogin(): void
    {
        App::$request = new Request('GET', '/admin/pages', ['x' => '1']);
        try {
            Auth::requireRole('editor');
            $this->fail('Expected redirect');
        } catch (HttpException $e) {
            $this->assertSame('/login?return=%2Fadmin%2Fpages%3Fx%3D1', $e->response?->header('Location'));
        }
    }

    public function testRequireRole(): void
    {
        $editor = $this->makeUser('ed', 'editor');
        $admin = $this->makeUser('boss', 'admin');
        Auth::setUser((new \Angelma\Repos\Users($this->db))->find($editor));

        Auth::requireRole('user');
        Auth::requireRole('editor');
        $this->assertTrue(Auth::can('editor'));
        $this->assertFalse(Auth::isAdmin());
        $this->assertStatus(403, fn () => Auth::requireRole('admin'));

        Auth::setUser((new \Angelma\Repos\Users($this->db))->find($admin));
        Auth::requireRole('editor');
        Auth::requireRole('admin');
        $this->assertTrue(Auth::isAdmin());
    }

    private function assertStatus(int $status, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected HTTP ' . $status);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->status);
        }
    }
}
