<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['username' => 'rootnav']);
        $this->logInAs($user, ['*']);

        return $user;
    }

    private function pageUser(array $pages): User
    {
        $user = User::factory()->create(['username' => 'navclerk'.mt_rand(100000, 999999)]);
        $this->logInAs($user, $pages);

        return $user;
    }

    private function logInAs(User $user, array $pages): void
    {
        foreach ($pages as $page) {
            Permission::query()->create([
                'user_id' => $user->id,
                'page_name' => $page,
                'can_access' => true,
            ]);
        }

        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    public function test_super_admin_sidebar_groups_access_control(): void
    {
        $this->superAdmin();

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Access Control')
            ->assertSee('Create User')
            ->assertSee('User Management')
            ->assertSee('Manage Permissions')
            ->assertSee('Action Permissions')
            ->assertSee('Municipality Scope')
            ->assertSee('Manage Program Permissions')
            ->assertSee('Multi-Device Exemptions')
            ->assertSee('Currently Logged Users')
            ->assertSee('Audit Logs');

        $content = $response->getContent();

        // Scholarly/assistance hubs render once as single links, not per-key.
        $this->assertSame(1, substr_count($content, 'href="'.route('scanners.index').'"'));
        $this->assertSame(1, substr_count($content, 'href="'.route('payouts.index').'"'));
    }

    public function test_access_control_group_hidden_without_admin_permissions(): void
    {
        $this->pageUser(['clients.php']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Access Control')
            ->assertDontSee('Create User')
            ->assertDontSee('User Management')
            ->assertDontSee('Manage Permissions')
            ->assertDontSee('Multi-Device Exemptions')
            ->assertDontSee('Currently Logged Users')
            ->assertDontSee('Audit Logs')
            ->assertDontSee('Scanner Engine')
            ->assertDontSee('href="'.route('payouts.index').'"');
    }

    public function test_access_control_group_shows_only_children_whose_pages_user_can_open(): void
    {
        $this->pageUser(['manage_permissions.php']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Access Control')
            ->assertSee('Manage Permissions')
            ->assertSee('Action Permissions')
            ->assertSee('Municipality Scope')
            ->assertDontSee('Create User')
            ->assertDontSee('User Management')
            ->assertDontSee('Manage Program Permissions')
            ->assertDontSee('Multi-Device Exemptions')
            ->assertDontSee('Currently Logged Users')
            // Orphan section headers stay hidden (no visible link in Users).
            ->assertDontSee('>Users<');
    }

    public function test_sidebar_shows_single_payouts_hub_instead_of_flat_items(): void
    {
        $this->pageUser(['scanned_payouts2.php']);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('Payout Attendance')
            ->assertDontSee('Payout Attendance 2')
            ->assertDontSee('Payout Attendance Unpaid')
            ->assertDontSee('Unpaid Grantees');

        // Exactly one hub link takes the place of the three flat screens.
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('payouts.index').'"'));
    }

    public function test_sidebar_shows_single_scanner_engine_hub_instead_of_flat_items(): void
    {
        $this->pageUser(['scanner_ceap.php']);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('CEDSSG Scholarship')
            ->assertDontSee('TUPAD Cash for Work');

        // The permitted scanner keeps its quick-action chip on the dashboard,
        // but the sidebar holds exactly one Scanner Engine page link.
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('scanners.index').'"'));
    }

    public function test_reports_only_user_gets_top_level_fallback_link(): void
    {
        $this->pageUser(['scholarship_reports.php']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Scholarship Reports');
    }

    public function test_logs_only_user_gets_top_level_fallback_link(): void
    {
        $this->pageUser(['update_logs.php']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Update Logs');
    }

    public function test_scholars_only_user_gets_no_top_level_reports_or_logs_links(): void
    {
        $this->pageUser(['scholars.php']);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('Scholarship Reports')
            ->assertDontSee('Update Logs');

        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('scholars.index').'"'));
    }

    public function test_aics_dead_link_is_removed_from_chrome(): void
    {
        $this->superAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="#"')
            ->assertDontSee('AICS');
    }

    public function test_hub_pages_require_login(): void
    {
        $this->get(route('scanners.index'))->assertRedirect(route('login'));
        $this->get(route('payouts.index'))->assertRedirect(route('login'));
    }

    public function test_hub_pages_are_reachable_without_a_specific_page_key(): void
    {
        // The hubs render for any authenticated user (their content is gated
        // per-destination); they must never 403 for a non-admin.
        $this->pageUser(['clients.php']);

        $this->get(route('payouts.index'))->assertOk()->assertSee('No payout screens');
    }

    public function test_navbar_breadcrumb_resolves_hub_and_payout_screens(): void
    {
        $this->superAdmin();

        $this->get(route('scanners.index'))->assertOk()->assertSee('Scanner Engine');
        $this->get(route('payouts.index'))->assertOk()->assertSee('Payouts');
        $this->get(route('payout-attendance.scanned_payouts.index'))->assertOk()->assertSee('Scanned Payouts');
        $this->get(route('payout-attendance.scanned_payouts_unpaid.index'))->assertOk()->assertSee('Scanned Unpaid Payouts');
    }
}
