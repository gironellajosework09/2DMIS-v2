<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SampleDataSeeder extends Seeder
{
    private function getCategoryForAge(int $age): string
    {
        if ($age < 18) {
            return 'MINOR (0-17)';
        }
        if ($age <= 29) {
            return 'YOUTH (18-29)';
        }
        if ($age <= 59) {
            return 'ADULT (30-59)';
        }

        return 'SENIOR CITIZEN (60 AND ABOVE)';
    }

    public function run(): void
    {
        $this->command->info('Seeding sample data into main_system...');

        // Sample Filipino Names
        $firstNamesMale = ['Juan', 'Pedro', 'Jose', 'Mark', 'Christian', 'Angelo', 'Gabriel', 'Michael', 'Joshua', 'Jerome'];
        $firstNamesFemale = ['Maria', 'Ana', 'Grace', 'Christine', 'Angelica', 'Joy', 'Mary', 'Bea', 'Rhea', 'Camille'];
        $lastNames = ['Del Rosario', 'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Castillo'];

        // Exact enum values from tbl_transactions.program
        $programs = ['AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP', 'OTCES', 'OTEA', 'GIP', 'TODA'];

        $batchTag = rand(100, 999);

        // Create 5 Sample Households
        for ($h = 1; $h <= 5; $h++) {
            $householdCode = 'HH-2026-'.$batchTag.'-'.str_pad((string) $h, 2, '0', STR_PAD_LEFT);
            $surname = $lastNames[$h - 1];
            $headIsMale = ($h % 2 !== 0);

            // 1. Create Head of Household Client first
            $headFirstName = $headIsMale ? $firstNamesMale[$h - 1] : $firstNamesFemale[$h - 1];
            $headBirthdate = Carbon::now()->subYears(45 + ($h * 3))->format('Y-m-d');
            $headAge = 45 + ($h * 3);

            $headClient = Client::query()->create([
                'family_id' => $h + ($batchTag * 10),
                'household_id' => null,
                'lastname' => $surname,
                'firstname' => $headFirstName,
                'middlename' => 'Santos',
                'extensionname' => '',
                'region' => 'Region I',
                'province' => 'Ilocos Sur',
                'city_municipality' => 1, // CANDON CITY
                'barangay' => ($h % 5) + 1,
                'house_no' => (10 + $h).' Main St',
                'mobile_no' => '0917'.str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => strtolower($headFirstName.'.'.str_replace(' ', '', $surname).$batchTag).'@example.com',
                'birthdate' => $headBirthdate,
                'age' => $headAge,
                'sex' => $headIsMale ? 'Male' : 'Female',
                'civil_status' => 'Married',
                'pwd' => 'No',
                'ip' => 'No',
                'ip_group' => '',
                'occupation' => 'Farmer / Self-employed',
                'monthly_income' => 8500.00,
                'category' => $this->getCategoryForAge($headAge),
                'aff_org' => 'N/A',
                'precinct_no' => '001A',
                'voter_id' => 'VOT-'.rand(10000, 99999),
                'full_name' => strtoupper("{$surname}, {$headFirstName} SANTOS"),
                'match_name' => strtolower("{$surname}_{$headFirstName}_santos"),
            ]);

            // 2. Create Household with head_household set
            $household = Household::query()->create([
                'household_id' => $householdCode,
                'head_household' => $headClient->id,
            ]);

            // 3. Link household_id to head client
            $headClient->update(['household_id' => $household->id]);

            // Create Spouse
            $spouseIsMale = ! $headIsMale;
            $spouseFirstName = $spouseIsMale ? $firstNamesMale[($h + 2) % count($firstNamesMale)] : $firstNamesFemale[($h + 2) % count($firstNamesFemale)];
            $spouseAge = 42 + ($h * 2);
            $spouseBirthdate = Carbon::now()->subYears($spouseAge)->format('Y-m-d');

            $spouseClient = Client::query()->create([
                'family_id' => $h + ($batchTag * 10),
                'household_id' => $household->id,
                'lastname' => $surname,
                'firstname' => $spouseFirstName,
                'middlename' => 'Mendoza',
                'extensionname' => '',
                'region' => 'Region I',
                'province' => 'Ilocos Sur',
                'city_municipality' => 1,
                'barangay' => ($h % 5) + 1,
                'house_no' => (10 + $h).' Main St',
                'mobile_no' => '0918'.str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => strtolower($spouseFirstName.'.'.str_replace(' ', '', $surname).$batchTag).'@example.com',
                'birthdate' => $spouseBirthdate,
                'age' => $spouseAge,
                'sex' => $spouseIsMale ? 'Male' : 'Female',
                'civil_status' => 'Married',
                'pwd' => 'No',
                'ip' => 'No',
                'ip_group' => '',
                'occupation' => 'Homemaker / Vendor',
                'monthly_income' => 4500.00,
                'category' => $this->getCategoryForAge($spouseAge),
                'aff_org' => 'N/A',
                'precinct_no' => '001A',
                'voter_id' => 'VOT-'.rand(10000, 99999),
                'full_name' => strtoupper("{$surname}, {$spouseFirstName} MENDOZA"),
                'match_name' => strtolower("{$surname}_{$spouseFirstName}_mendoza"),
            ]);

            // Map Spouse relationship
            FamilyMember::query()->create(['client_id' => $headClient->id, 'relative_id' => $spouseClient->id, 'relationship' => 'SPOUSE']);
            FamilyMember::query()->create(['client_id' => $spouseClient->id, 'relative_id' => $headClient->id, 'relationship' => 'SPOUSE']);

            // Create 2 Children
            for ($c = 1; $c <= 2; $c++) {
                $childIsMale = ($c % 2 === 0);
                $childFirstName = $childIsMale ? $firstNamesMale[($h + $c + 4) % count($firstNamesMale)] : $firstNamesFemale[($h + $c + 4) % count($firstNamesFemale)];
                $childAge = 18 + ($c * 3);
                $childBirthdate = Carbon::now()->subYears($childAge)->format('Y-m-d');

                $childClient = Client::query()->create([
                    'family_id' => $h + ($batchTag * 10),
                    'household_id' => $household->id,
                    'lastname' => $surname,
                    'firstname' => $childFirstName,
                    'middlename' => $headFirstName,
                    'extensionname' => '',
                    'region' => 'Region I',
                    'province' => 'Ilocos Sur',
                    'city_municipality' => 1,
                    'barangay' => ($h % 5) + 1,
                    'house_no' => (10 + $h).' Main St',
                    'mobile_no' => '0919'.str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'email' => strtolower($childFirstName.'.'.str_replace(' ', '', $surname).$batchTag).'@example.com',
                    'birthdate' => $childBirthdate,
                    'age' => $childAge,
                    'sex' => $childIsMale ? 'Male' : 'Female',
                    'civil_status' => 'Single',
                    'pwd' => ($c === 2) ? 'Yes' : 'No',
                    'ip' => 'No',
                    'ip_group' => '',
                    'occupation' => 'Student / Jobseeker',
                    'monthly_income' => 0.00,
                    'category' => $this->getCategoryForAge($childAge),
                    'aff_org' => 'N/A',
                    'precinct_no' => '001A',
                    'voter_id' => 'VOT-'.rand(10000, 99999),
                    'full_name' => strtoupper("{$surname}, {$childFirstName} {$headFirstName}"),
                    'match_name' => strtolower("{$surname}_{$childFirstName}_{$headFirstName}"),
                ]);

                // Map Child relationships
                FamilyMember::query()->create(['client_id' => $headClient->id, 'relative_id' => $childClient->id, 'relationship' => 'CHILD']);
                FamilyMember::query()->create(['client_id' => $childClient->id, 'relative_id' => $headClient->id, 'relationship' => 'PARENT']);
                FamilyMember::query()->create(['client_id' => $spouseClient->id, 'relative_id' => $childClient->id, 'relationship' => 'CHILD']);
                FamilyMember::query()->create(['client_id' => $childClient->id, 'relative_id' => $spouseClient->id, 'relationship' => 'PARENT']);

                // Create 1 Transaction per child
                Transaction::query()->create([
                    'client_id' => $childClient->id,
                    'program' => $programs[array_rand($programs)],
                    'patient_name' => '',
                    'date_applied' => Carbon::now()->subDays(rand(5, 60))->format('Y-m-d'),
                    'type' => 'Cash Assistance',
                    'remarks' => 'Assisted via local municipal office.',
                    'comments' => 'Verified requirements attached.',
                    'suggested_amount' => 3000.00,
                    'status' => 'PAID',
                    'amount_paid' => 3000.00,
                    'payout_date' => Carbon::now()->subDays(rand(1, 40))->format('Y-m-d'),
                    'date_paid' => Carbon::now()->subDays(rand(1, 40))->format('Y-m-d'),
                    'gwa' => '',
                    'units' => '',
                ]);
            }

            // Create Head Transactions (e.g. Medical, TUPAD, Financial)
            Transaction::query()->create([
                'client_id' => $headClient->id,
                'program' => 'AICS',
                'patient_name' => "{$headClient->firstname} {$headClient->lastname}",
                'date_applied' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'type' => 'Hospitalization Assistance',
                'remarks' => 'Candon City General Hospital billing support',
                'comments' => 'Medical certificate & clinical abstract validated.',
                'suggested_amount' => 5000.00,
                'status' => 'PAID',
                'amount_paid' => 5000.00,
                'payout_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
                'date_paid' => Carbon::now()->subDays(2)->format('Y-m-d'),
                'gwa' => '',
                'units' => '',
            ]);

            Transaction::query()->create([
                'client_id' => $headClient->id,
                'program' => 'TUPAD',
                'patient_name' => '',
                'date_applied' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'type' => 'Emergency Employment',
                'remarks' => '10-day community work project',
                'comments' => 'DOLE TUPAD batch 2026-Q1',
                'suggested_amount' => 4350.00,
                'status' => 'APPROVED',
                'amount_paid' => 0.00,
                'payout_date' => null,
                'date_paid' => null,
                'gwa' => '',
                'units' => '',
            ]);
        }

        // Add call to AccessControlSeeder to ensure super-admin grants exist
        $this->call([
            AccessControlSeeder::class,
        ]);

        $this->command->info('Sample data seeding complete!');
    }
}
