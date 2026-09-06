<?php

namespace Tests\Feature;

use App\Services\LoginOtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The two-factor step on login.
 *
 * The password half of the flow is not exercised here because it goes through
 * the live Cloudflare CAPTCHA call; these start from the pending-login state
 * that a verified password produces and cover everything after it.
 */
class LoginOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Minimal stand-ins for the two account tables. The project's real
        // migrations do not run on sqlite (an early data migration inserts into
        // a table it does not create), so the shape needed here is built by hand.
        Schema::create('centralized_admin_user', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('full_name')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('role')->nullable();
            $table->string('department')->nullable();
            $table->timestamps();
        });

        Schema::create('crime_department_staff', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('full_name')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('ip_address')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });

        Mail::fake();
    }

    /** The session state the password step hands to the OTP step */
    private function pending(array $overrides = []): array
    {
        return array_merge([
            'type'           => 'admin',
            'id'             => 1,
            'email'          => 'admin@example.com',
            'name'           => 'Admin User',
            'ip'             => '127.0.0.1',
            'started_at'     => now()->timestamp,
            'otp_expires_at' => now()->addMinutes(5)->timestamp,
        ], $overrides);
    }

    private function makeAdmin(): int
    {
        return DB::table('centralized_admin_user')->insertGetId([
            'email'         => 'admin@example.com',
            'full_name'     => 'Admin User',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);
    }

    private function makeStaff(bool $active = true): int
    {
        return DB::table('crime_department_staff')->insertGetId([
            'email'         => 'staff@example.com',
            'full_name'     => 'Staff User',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'is_active'     => $active,
        ]);
    }

    /**
     * Run the verify step against a session this test owns.
     *
     * The HTTP test client mints a new session per request, so a code bound to
     * one session id could never be presented back through it. Driving the
     * controller directly keeps that binding intact while still exercising the
     * real verification and sign-in path.
     */
    private function submitCode(array $pending, string $code, ?string $sessionId = null)
    {
        $session = $this->app['session']->driver();
        $session->setId($sessionId ?? $session->getId());
        $session->start();
        $session->put('pending_login', $pending);

        $request = Request::create('/verify-otp', 'POST', ['otp_code' => $code]);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $this->app->make(AuthController::class)->verifyOtp($request);
    }

    /** A session id plus a live code bound to it */
    private function issueCodeFor(string $accountKey): array
    {
        $session = $this->app['session']->driver();
        $session->start();
        $sessionId = $session->getId();

        return [$sessionId, LoginOtpService::generate($sessionId, $accountKey)];
    }

    public function test_otp_page_redirects_to_login_without_a_pending_login()
    {
        $this->get('/verify-otp')->assertRedirect(route('login'));
    }

    public function test_otp_page_renders_for_a_pending_login()
    {
        $response = $this->withSession(['pending_login' => $this->pending()])
            ->get('/verify-otp');

        $response->assertStatus(200);
        // The mailbox is hinted, never spelled out in full.
        $response->assertSee('a***n@example.com');
        $response->assertDontSee('admin@example.com');
    }

    public function test_a_stale_pending_login_is_rejected()
    {
        $response = $this->withSession([
            'pending_login' => $this->pending(['started_at' => now()->subMinutes(30)->timestamp]),
        ])->get('/verify-otp');

        $response->assertRedirect(route('login'));
    }

    public function test_correct_code_signs_the_admin_in()
    {
        $id = $this->makeAdmin();
        [$sessionId, $code] = $this->issueCodeFor('admin:' . $id);

        $response = $this->submitCode($this->pending(['id' => $id]), $code, $sessionId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('dashboard', $response->getTargetUrl());
        $this->assertTrue(auth()->check());
        $this->assertSame('admin@example.com', session('auth_user.email'));
        // The half-finished login is cleared once it completes.
        $this->assertNull(session('pending_login'));
    }

    public function test_wrong_code_does_not_sign_anyone_in()
    {
        $id = $this->makeAdmin();
        [$sessionId] = $this->issueCodeFor('admin:' . $id);

        $response = $this->submitCode($this->pending(['id' => $id]), '000000', $sessionId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse(auth()->check());
        // Still pending: the user gets to try again rather than starting over.
        $this->assertTrue(LoginOtpService::isActive($sessionId));
    }

    public function test_a_code_cannot_be_reused()
    {
        $id = $this->makeAdmin();

        $this->startSession();
        $sessionId = session()->getId();
        $code = LoginOtpService::generate($sessionId, 'admin:' . $id);

        [$first] = LoginOtpService::verify($sessionId, 'admin:' . $id, $code);
        [$second] = LoginOtpService::verify($sessionId, 'admin:' . $id, $code);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_a_code_issued_for_one_account_cannot_be_spent_on_another()
    {
        $this->startSession();
        $sessionId = session()->getId();
        $code = LoginOtpService::generate($sessionId, 'admin:1');

        [$ok] = LoginOtpService::verify($sessionId, 'staff:1', $code);

        $this->assertFalse($ok);
    }

    public function test_code_dies_after_the_attempt_limit()
    {
        $this->startSession();
        $sessionId = session()->getId();
        $code = LoginOtpService::generate($sessionId, 'admin:1');

        for ($i = 0; $i < LoginOtpService::MAX_ATTEMPTS; $i++) {
            LoginOtpService::verify($sessionId, 'admin:1', '000000');
        }

        // Even the genuine code is worthless once the budget is spent.
        [$ok] = LoginOtpService::verify($sessionId, 'admin:1', $code);
        $this->assertFalse($ok);
        $this->assertFalse(LoginOtpService::isActive($sessionId));
    }

    public function test_active_staff_signs_in_with_a_correct_code()
    {
        $id = $this->makeStaff();
        [$sessionId, $code] = $this->issueCodeFor('staff:' . $id);

        $response = $this->submitCode($this->pending([
            'type'  => 'staff',
            'id'    => $id,
            'email' => 'staff@example.com',
        ]), $code, $sessionId);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('dashboard', $response->getTargetUrl());
        $this->assertTrue(auth()->guard('staff')->check());
        $this->assertSame('staff@example.com', session('auth_user.email'));
    }

    public function test_deactivated_staff_cannot_finish_a_login_in_flight()
    {
        $id = $this->makeStaff(active: false);
        [$sessionId, $code] = $this->issueCodeFor('staff:' . $id);

        $response = $this->submitCode($this->pending([
            'type'  => 'staff',
            'id'    => $id,
            'email' => 'staff@example.com',
        ]), $code, $sessionId);

        $this->assertSame(route('login'), $response->getTargetUrl());
        $this->assertFalse(auth()->guard('staff')->check());
    }

    public function test_resend_sends_a_fresh_code_for_a_pending_login()
    {
        $id = $this->makeAdmin();

        $response = $this->withSession(['pending_login' => $this->pending(['id' => $id])])
            ->postJson('/verify-otp/resend');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_resend_is_refused_while_the_cooldown_is_running()
    {
        $session = $this->app['session']->driver();
        $session->start();

        LoginOtpService::generate($session->getId(), 'admin:1');

        $this->assertGreaterThan(0, LoginOtpService::resendCooldownRemaining($session->getId()));
        $this->assertSame(0, LoginOtpService::resendCooldownRemaining('some-other-session'));
    }

    public function test_resend_without_a_pending_login_is_refused()
    {
        $this->postJson('/verify-otp/resend')->assertStatus(419);
    }
}
