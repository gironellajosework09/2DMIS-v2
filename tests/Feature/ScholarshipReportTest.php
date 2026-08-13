<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ScholarInfo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipReportTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function reportUser(): User
    {
        $user = User::factory()->create(['username' => 'report-clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'scholarship_reports.php',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => ''],
            'municipality' => '',
            'barangay' => '',
            'program' => '',
            'submitted' => '',
            'date_from' => '',
            'date_to' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeScholar(Client $client, string $program, array $attributes = []): ScholarInfo
    {
        return ScholarInfo::create(array_merge([
            'client_id' => $client->id,
            'full_name' => $client->lastname.', '.$client->firstname,
            'program' => $program,
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => 'BSIT',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '12345',
        ], $attributes));
    }

    private function makeTransaction(Client $client, string $program, array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'client_id' => $client->id,
            'program' => $program,
            'date_applied' => '2026-01-15',
            'type' => 'SCHOLARSHIP',
            'gwa' => '1.5',
            'units' => '18',
            'remarks' => 'SCHOLAR',
            'status' => 'PENDING PAYOUT',
        ], $attributes));
    }

    private function client(array $attributes = []): Client
    {
        return Client::factory()->create(array_merge([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'SANTOS',
            'mobile_no' => '09171234567',
            'city_municipality' => 1,
            'barangay' => 1,
        ], $attributes));
    }

    public function test_report_screen_renders_with_municipalities_and_programs()
    {
        $this->reportUser();
        $municipality = Municipality::create(['name' => 'VIGAN']);

        $response = $this->get(route('scholarship-reports.index'));

        $response->assertOk();
        $response->assertSee('Scholarship Reports');
        $response->assertSee($municipality->name);
        $response->assertSee('CEDSSG');
        $response->assertSee('OTCES');
    }

    public function test_report_feed_joins_scholar_info_and_clients()
    {
        $this->reportUser();
        $client = $this->client();
        $scholar = $this->makeScholar($client, 'CEDSSG', ['is_regular' => 1]);
        $this->makeTransaction($client, 'CEDSSG', ['gwa' => '1.75', 'units' => '21', 'remarks' => 'REGULAR']);

        $response = $this->post(route('scholarship-reports.data'), $this->reportPayload());

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('CEDSSG', $payload['data'][0]['program']);
        $this->assertSame('DELA CRUZ, JUAN SANTOS', $payload['data'][0]['full_name']);
        $this->assertSame('09171234567', $payload['data'][0]['mobile_no']);
        $this->assertSame('ISPSC-MAIN', $payload['data'][0]['school']);
        $this->assertSame('BSIT', $payload['data'][0]['course']);
        $this->assertSame('1ST YEAR', $payload['data'][0]['year_level']);
        $this->assertSame('1.75', $payload['data'][0]['gwa']);
        $this->assertSame('21', $payload['data'][0]['units']);
        $this->assertSame('12345', $payload['data'][0]['landbank_no']);
        $this->assertSame('REGULAR', $payload['data'][0]['remarks']);
        $this->assertSame('2026-01-15', $payload['data'][0]['date_applied']);
        $this->assertSame('Yes', $payload['data'][0]['regular']);
        $this->assertSame('Yes', $payload['data'][0]['submitted']);
    }

    public function test_report_feed_filters_by_program_and_uses_max_scholar_row()
    {
        $this->reportUser();
        $client = $this->client(['lastname' => 'REYES', 'firstname' => 'MARIA', 'middlename' => null]);
        $this->makeScholar($client, 'CEDSSG');
        $this->makeScholar($client, 'CEDSSG', ['course' => 'BSCS', 'is_regular' => 0]);
        $this->makeTransaction($client, 'CEAP');
        $this->makeTransaction($client, 'CEDSSG');

        $response = $this->post(route('scholarship-reports.data'), $this->reportPayload([
            'program' => 'CEDSSG',
        ]));

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('BSCS', $payload['data'][0]['course']);
        $this->assertSame('No', $payload['data'][0]['regular']);
    }

    public function test_report_feed_filters_by_date_range_and_search()
    {
        $this->reportUser();
        $client = $this->client();
        $this->makeScholar($client, 'CEDSSG');
        $this->makeTransaction($client, 'CEDSSG', ['date_applied' => '2026-02-01', 'gwa' => '1.25']);

        $response = $this->post(route('scholarship-reports.data'), $this->reportPayload([
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(0, $payload['recordsFiltered']);

        $response = $this->post(route('scholarship-reports.data'), $this->reportPayload([
            'search' => ['value' => 'DELA'],
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json()['recordsFiltered']);

        $response = $this->post(route('scholarship-reports.data'), $this->reportPayload([
            'search' => ['value' => 'NOMATCH'],
        ]));

        $response->assertOk();
        $this->assertSame(0, $response->json()['recordsFiltered']);
    }

    public function test_report_export_streams_bom_csv_with_v1_columns()
    {
        $this->reportUser();
        $client = $this->client(['lastname' => 'DELA CRUZ', 'firstname' => 'JUAN', 'middlename' => 'SANTOS', 'extensionname' => 'JR']);
        $this->makeScholar($client, 'CEDSSG');
        $this->makeTransaction($client, 'CEDSSG', ['gwa' => '1.5', 'units' => '18', 'remarks' => 'OK', 'status' => 'PENDING PAYOUT']);

        $response = $this->get(route('scholarship-reports.export'));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('scholarship_reports'.date('Ymd').'.csv', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($content, 3))));
        $this->assertSame('program', $rows[0][0]);
        $this->assertSame('lastname', $rows[0][1]);
        $this->assertSame('extensionname', $rows[0][4]);
        $this->assertSame('full_name', $rows[0][5]);
        $this->assertSame('submitted', $rows[0][22]);
        $this->assertSame('CEDSSG', $rows[1][0]);
        $this->assertSame('DELA CRUZ', $rows[1][1]);
        $this->assertSame('DELA CRUZ, JUAN SANTOS JR', $rows[1][5]);
        $this->assertSame('1.5', $rows[1][15]);
        $this->assertSame('OK', $rows[1][18]);
        $this->assertSame('PENDING PAYOUT', $rows[1][19]);
        $this->assertSame('2026-01-15', $rows[1][20]);
        $this->assertSame('Yes', $rows[1][22]);
    }

    public function test_report_export_applies_program_and_submitted_filters()
    {
        $this->reportUser();
        $submittedClient = $this->client(['lastname' => 'REYES', 'firstname' => 'MARIA']);
        $this->makeScholar($submittedClient, 'CEDSSG');
        $this->makeTransaction($submittedClient, 'CEDSSG');

        $unsubmittedClient = $this->client(['lastname' => 'GARCIA', 'firstname' => 'ANA']);
        $this->makeScholar($unsubmittedClient, 'CEAP');

        $response = $this->get(route('scholarship-reports.export', ['submitted' => 'No']));
        $content = $response->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($content, 3))));
        $this->assertCount(2, $rows);
        $this->assertSame('GARCIA, ANA SANTOS', $rows[1][5]);

        $response = $this->get(route('scholarship-reports.export', ['program' => 'CEDSSG']));
        $content = $response->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($content, 3))));
        $this->assertCount(2, $rows);
        $this->assertSame('REYES, MARIA SANTOS', $rows[1][5]);
    }

    public function test_report_screen_requires_permission()
    {
        $user = User::factory()->create(['username' => 'no-access']);
        $this->logInAs($user);

        $this->get(route('scholarship-reports.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
        $this->get(route('scholarship-reports.export'))
            ->assertRedirect(route('dashboard'));
        $this->post(route('scholarship-reports.data'), $this->reportPayload(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
