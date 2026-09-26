<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Toast feedback audit — Actions that land on the FULL-PAGE client profile
 * (clients.show) used to be silent: Add Family Member, GIP save, and Change
 * Photo all flash `session('success')` on redirect, but the show page dropped
 * the flash (no consumer), and the full-page Editor discarded its JSON message
 * across `window.location.reload()`.
 *
 * The fix reuses the ONE shared notification stack (window.notify /
 * partials.unified-notify): clients.show renders a small consumer that (a)
 * flushes the server `session('success')` flash and (b) flushes a
 * sessionStorage stash written by the full-page editor before its reload.
 * Panel mode (?panel=1) renders clients._details directly and never includes
 * the consumer script — so no duplicate toasts are possible.
 *
 * These tests prove the server-flash → consumer wiring end-to-end at the
 * render level (deterministic; the same mechanism is browser-proven via a
 * sessionStorage flush probe that writes nothing to main_system).
 */
class ClientProfileFlashToastTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        return $user;
    }

    /**
     * @return array{0: Municipality, 1: Barangay}
     */
    private function place(): array
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return [$municipality, $barangay];
    }

    private function makeClient(array $overrides = []): Client
    {
        [$municipality, $barangay] = $this->place();

        return Client::query()->create(array_merge([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'extensionname' => null,
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'mobile_no' => '09171234567',
            'full_name' => 'DELA CRUZ, JUAN R',
            'match_name' => 'DELACRUZJUANR',
        ], $overrides));
    }

    /**
     *  Full profile page renders the consumer script that pushes the server
     *  flash into window.notify.
     */
    public function test_gip_save_flash_is_consumed_on_the_full_page_profile(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $post = $this->post(route('gip.store', $client), [
            'client_id' => $client->id,
            'valid_govt_id' => 'UMID',
            'id_number' => '8811-0000-0000',
            'year_graduated' => 2000,
        ]);

        $post->assertRedirect(route('clients.show', $client).'#collapseGIP');
        $post->assertSessionHas('success', 'GIP details saved.');

        $html = $this->get(route('clients.show', $client))->assertOk()->getContent();

        $this->assertStringContainsString("pending.push({ type: 'success', title: 'Success', message: 'GIP details saved.' });", $html);
    }

    /**
     *  Regression probe (documenting a PRE-EXISTING v2 bug, out of scope for
     *  the toast audit): v1 never audited family-member linking, but v2's
     *  FamilyMemberService::audit() writes an ADD_FAMILY_MEMBER row with
     *  target_id = null into tbl_audit_logs.target_id, which is NOT NULL in
     *  the baseline schema — the store() request 500s and the whole link
     *  transaction is rolled back. Result: no family-member rows persist and
     *  the success flash never exists, so NO post-action feedback is possible
     *  until this backend constraint is resolved (needs a separate decision:
     *  audit the tbl_family_members id, or drop the audit like v1 did).
     */
    public function test_add_family_member_flow_is_500_target_id_null_regression(): void
    {
        $this->logInAs($this->clientUser());
        $parent = $this->makeClient();
        $relative = $this->makeClient(['lastname' => 'SANTOS', 'firstname' => 'MARIA', 'full_name' => 'SANTOS, MARIA', 'match_name' => 'SANTOSMARIA']);

        $post = $this->post(route('family-members.store', $parent), [
            'existing_client_id' => $relative->id,
            'relationship' => 'SON',
        ]);

        $post->assertStatus(500);
        $post->assertSessionMissing('success');

        $this->assertDatabaseMissing('tbl_family_members', [
            'client_id' => $parent->id,
            'relative_id' => $relative->id,
        ]);
    }

    public function test_change_photo_flash_is_consumed_on_the_full_page_profile(): void
    {
        Storage::fake('public');

        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $post = $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
            'photo' => UploadedFile::fake()->image('photo.jpg', 100, 100),
        ]);

        $post->assertRedirect(route('clients.show', $client));
        $post->assertSessionHas('success', 'Client photo updated successfully.');

        $html = $this->get(route('clients.show', $client))->assertOk()->getContent();

        $this->assertStringContainsString("message: 'Client photo updated successfully.'", $html);
    }

    /**
     *  The profile always ships both the flash consumer and the full-page
     *  Editor's stash writer; panel mode must NOT include the consumer.
     */
    public function test_full_page_profile_ships_consumer_and_stash_writer_but_panel_does_not(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $show = $this->get(route('clients.show', $client))->assertOk()->getContent();
        $panel = $this->get(route('clients.show', $client).'?panel=1')->assertOk()->getContent();

        $this->assertStringContainsString('2dmis_client_flash', $show);
        $this->assertStringContainsString('sessionStorage.getItem(\'2dmis_client_flash\')', $show);
        $this->assertStringContainsString('sessionStorage.setItem(\'2dmis_client_flash\', JSON.stringify({', $show);
        $this->assertStringContainsString('window.location.reload();', $show);

        $this->assertStringNotContainsString('sessionStorage.getItem(\'2dmis_client_flash\')', $panel);
        $this->assertStringNotContainsString('pushOnReady', $panel);
    }

    /**
     *  Error feedback for a rejected upload is visible on the profile
     *  (layout `errors` alert) — the flash-less failure path.
     */
    public function test_invalid_photo_type_surfaces_the_error_on_the_profile(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $post = $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
        ]);

        $post->assertRedirect(route('clients.show', $client));
        $post->assertSessionHasErrors(['photo' => 'No image provided.']);

        $html = $this->get(route('clients.show', $client))->assertOk()->getContent();

        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('No image provided.', $html);
    }
}