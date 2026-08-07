<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Single engine behind every scanner page (P4).
 *
 * The v1 scanner family had 14 pages and 15 action handlers. This service
 * replaces all of them with two verbs — lookup() and save() — whose behavior
 * is fully driven by the program configuration in config/scanner.php. Every
 * v1 quirk (duplicate rules, insert/update templates, audit events, remarks
 * divergence, payout attendance, name-matching semantics) is preserved; no
 * schema change was made.
 */
class ScanService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * @return array{success: bool, message?: string, data?: array, alreadySaved?: bool, existing?: object|null}
     */
    public function lookup(string $key, string $scanned, string $action = 'lookup'): array
    {
        $config = $this->config($key);
        $scanned = trim($scanned);

        if ($config['mode'] === 'seat_attendance') {
            return $this->lookupSeatAttendance($config, $scanned, $action);
        }

        return $this->lookupByStrategy($config, $scanned);
    }

    /**
     * @return array{success: bool, message?: string, data?: array, alreadySaved?: bool, existing?: object|null}
     */
    public function save(string $key, array $input, User $actor): array
    {
        return match ($this->config($key)['mode'] ?? null) {
            'scholarship_transaction' => $this->saveScholarshipTransaction($key, $input, $actor),
            'date_guarded_transaction' => $this->saveDateGuardedTransaction($key, $input, $actor),
            'update_in_place' => $this->saveUpdateInPlace($key, $input, $actor),
            'exam_derived' => $this->saveExamDerived($key, $input, $actor),
            'validate_existing' => $this->saveValidateExisting($key, $input, $actor),
            'seat_attendance', 'unpaid_attendance' => $this->saveAttendance($key, $input, $actor),
            'generic_form' => $this->saveGenericForm($key, $input),
            default => ['success' => false, 'message' => 'Unknown scanner.'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function config(string $key): array
    {
        $entry = config('scanner.scanners.'.$key);

        return is_array($entry) ? $entry : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Lookups
    |--------------------------------------------------------------------------
    */

    private function lookupByStrategy(array $config, string $scanned): array
    {
        return match ($config['lookup'] ?? null) {
            'client' => $this->lookupClient($config, $scanned),
            'client_geo' => $this->lookupClientGeo($config, $scanned),
            'transaction' => $this->lookupTransaction($config, $scanned),
            'transaction_partial' => $this->lookupTransactionPartial($config, $scanned),
            'exam_derived' => $this->lookupExamDerived($scanned),
            'existing_program' => $this->lookupExistingProgram($config, $scanned),
            default => ['success' => false, 'message' => 'Unknown lookup strategy.'],
        };
    }

    private function lookupClient(array $config, string $scanned): array
    {
        $client = $this->findClientByName($scanned);

        if ($client === null) {
            return ['success' => false, 'message' => $this->missMessage($config, $scanned)];
        }

        return ['success' => true, 'data' => [
            'id' => $client->id,
            'full_name' => $client->full_name,
        ]];
    }

    private function lookupClientGeo(array $config, string $scanned): array
    {
        $client = $this->findClientByName($scanned);

        if ($client === null) {
            return ['success' => false, 'message' => $this->missMessage($config, $scanned)];
        }

        $data = [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'city_municipality' => $client->city_municipality,
            'municipality' => $this->municipalityName((int) $client->city_municipality),
        ];

        if ($config['include_barangay'] ?? false) {
            $data['barangay'] = $this->barangayName((int) $client->barangay);
        }

        return ['success' => true, 'data' => $data];
    }

    private function lookupTransaction(array $config, string $scanned): array
    {
        $row = DB::table('tbl_transactions as t')
            ->whereRaw('TRIM(t.patient_name) = ?', [$scanned])
            ->whereIn('t.program', $config['programs'])
            ->where('t.remarks', 'LIKE', '%2ND SEM%')
            ->where('t.status', 'PENDING PAYOUT')
            ->orderByDesc('t.id')
            ->first(['t.id as transaction_id', 't.patient_name', 't.program', 't.remarks', 't.status']);

        if ($row === null) {
            return ['success' => false, 'message' => 'No pending CEDSSG 2ND SEM payout found.'];
        }

        return ['success' => true, 'data' => [
            'transaction_id' => $row->transaction_id,
            'patient_name' => $row->patient_name,
            'program' => $row->program,
            'remarks' => $row->remarks,
            'status' => $row->status,
        ]];
    }

    private function lookupTransactionPartial(array $config, string $scanned): array
    {
        $scanned = preg_replace('/\s+/', ' ', $scanned) ?? $scanned;

        $tx = DB::table('tbl_transactions')
            ->whereRaw('LOWER(patient_name) LIKE LOWER(CONCAT("%", ?, "%"))', [$scanned])
            ->whereIn('program', $config['programs'])
            ->orderByDesc('id')
            ->first(['id as transaction_id', 'patient_name', 'program', 'status', 'comments']);

        if ($tx === null) {
            return ['success' => false, 'message' => "No matching transaction found for [$scanned]."];
        }

        if ($this->alreadyScanned($config, $tx->transaction_id)) {
            return ['success' => false, 'message' => 'This QR code has already been scanned.'];
        }

        return ['success' => true, 'data' => [
            'id' => $tx->transaction_id,
            'patient_name' => $tx->patient_name,
            'program' => $tx->program,
            'status' => $tx->status,
            'comments' => $tx->comments ?? '',
        ]];
    }

    private function lookupExamDerived(string $scanned): array
    {
        $client = $this->findClientByName($scanned);

        if ($client === null) {
            return ['success' => false, 'message' => "Client not found for: $scanned"];
        }

        $exam = DB::table('tbl_exam')
            ->whereRaw('TRIM(fullname) COLLATE utf8mb4_general_ci = ?', [$scanned])
            ->first(['exam_no']);

        if ($exam === null) {
            return ['success' => false, 'message' => "No exam record found for this client: $scanned"];
        }

        $result = DB::table('tbl_results')->where('exam_no', $exam->exam_no)->first(['approved']);

        if ($result === null || empty($result->approved)) {
            return ['success' => false, 'message' => "No approved scholarship found for this client: $scanned"];
        }

        return ['success' => true, 'data' => [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'program' => strtoupper(trim((string) $result->approved)),
        ]];
    }

    private function lookupExistingProgram(array $config, string $scanned): array
    {
        $client = $this->findClientByName($scanned);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $txn = DB::table('tbl_transactions')
            ->where('client_id', $client->id)
            ->whereIn('program', array_keys($config['programs']))
            ->orderByDesc('id')
            ->first(['program']);

        if ($txn === null) {
            return ['success' => false, 'message' => 'No ongoing scholarship program found'];
        }

        return ['success' => true, 'data' => [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'program' => $txn->program,
        ]];
    }

    private function lookupSeatAttendance(array $config, string $scanned, string $action): array
    {
        $scanned = preg_replace('/\s+/', ' ', $scanned) ?? $scanned;

        if ($action === 'lookup_ignore_scan') {
            return $this->lookupSeatIgnoreScan($scanned);
        }

        $programs = array_values($config['programs']);
        $placeholders = implode(',', array_fill(0, count($programs), '?'));

        $row = DB::selectOne("
            SELECT
                t.id AS transaction_id, t.client_id, c.full_name AS client_name,
                t.program, t.amount_paid, t.payout_date, t.date_paid, t.status, t.comments,
                s.town, s.section, s.box, s.row, s.seat
            FROM tbl_seats2 s
            INNER JOIN tbl_clients c ON LOWER(TRIM(s.name)) = LOWER(TRIM(c.full_name))
            INNER JOIN tbl_transactions t ON t.client_id = c.id
            WHERE LOWER(TRIM(s.name)) = LOWER(?) AND t.program IN ($placeholders)
            ORDER BY t.id DESC
            LIMIT 1
        ", array_merge([$scanned], $programs));

        if ($row === null) {
            $row = DB::selectOne("
                SELECT
                    t.id AS transaction_id, t.client_id, c.full_name AS client_name,
                    t.program, t.amount_paid, t.payout_date, t.date_paid, t.status, t.comments,
                    s.town, s.section, s.box, s.row, s.seat
                FROM tbl_seats2 s
                INNER JOIN tbl_clients c ON LOWER(TRIM(s.name)) LIKE LOWER(CONCAT('%', TRIM(c.full_name), '%'))
                INNER JOIN tbl_transactions t ON t.client_id = c.id
                WHERE LOWER(s.name) LIKE LOWER(CONCAT('%', ?, '%')) AND t.program IN ($placeholders)
                ORDER BY t.id DESC
                LIMIT 1
            ", array_merge([$scanned], $programs));
        }

        if ($row === null) {
            return ['success' => false, 'message' => "No matching transaction found for [$scanned]."];
        }

        if ($this->alreadyScanned($config, $row->transaction_id)) {
            return ['success' => false, 'message' => 'This QR code has already been scanned.'];
        }

        return ['success' => true, 'data' => [
            'id' => $row->transaction_id,
            'patient_name' => $row->client_name,
            'program' => $row->program,
            'amount_paid' => $row->amount_paid,
            'payout_date' => $row->payout_date,
            'date_paid' => $row->date_paid,
            'status' => $row->status,
            'town' => $row->town,
            'section' => $row->section,
            'box' => $row->box,
            'row' => $row->row,
            'seat' => $row->seat,
            'comments' => $row->comments ?? '',
        ]];
    }

    private function lookupSeatIgnoreScan(string $scanned): array
    {
        $row = DB::selectOne('
            SELECT
                t.id AS transaction_id, t.patient_name, t.program, t.amount_paid,
                t.payout_date, t.date_paid, t.status, t.comments,
                s.town, s.section, s.box, s.row, s.seat
            FROM tbl_transactions t
            LEFT JOIN tbl_seats2 s
                ON LOWER(TRIM(s.name)) = LOWER(TRIM(t.patient_name))
               AND LOWER(TRIM(s.program)) = LOWER(TRIM(t.program))
            WHERE LOWER(TRIM(t.patient_name)) = LOWER(?)
            ORDER BY t.id DESC
            LIMIT 1
        ', [$scanned]);

        if ($row === null) {
            return ['success' => false, 'message' => 'No details found.'];
        }

        return ['success' => true, 'data' => [
            'id' => $row->transaction_id,
            'patient_name' => $row->patient_name,
            'program' => $row->program,
            'town' => $row->town,
            'section' => $row->section,
            'box' => $row->box,
            'row' => $row->row,
            'seat' => $row->seat,
            'comments' => $row->comments ?? '',
        ]];
    }

    /*
    |--------------------------------------------------------------------------
    | Saves
    |--------------------------------------------------------------------------
    */

    private function saveScholarshipTransaction(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $clientId = (int) ($input['id'] ?? 0);

        if ($clientId <= 0) {
            return ['success' => false, 'message' => 'Invalid client ID'];
        }

        $client = Client::query()->find($clientId);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $program = $config['programs'][0];
        $insert = $config['insert'];

        if ($this->remarkKeyDuplicateExists($clientId, $program, $insert['remarks'])) {
            return ['success' => false, 'message' => 'Transaction already recorded for this client.'];
        }

        $transaction = Transaction::query()->create([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => $client->full_name,
            'date_applied' => date('Y-m-d'),
            'type' => $insert['type'],
            'remarks' => $insert['remarks'],
            'suggested_amount' => $insert['suggested_amount'],
            'status' => $insert['status'],
            'payout_date' => $insert['payout_date'],
        ]);

        $this->writeAudit($config, $actor, $transaction->id, [
            'program' => $program,
            'client_id' => $clientId,
            'patient_name' => $client->full_name,
            'remarks' => $insert['remarks'],
            'status' => $insert['status'],
        ]);

        return ['success' => true];
    }

    private function saveDateGuardedTransaction(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $clientId = (int) ($input['id'] ?? 0);
        $dateApplied = ! empty($input['date_applied']) ? $input['date_applied'] : date('Y-m-d');
        $datePaid = ! empty($input['date_paid']) ? $input['date_paid'] : null;
        $program = $config['programs'][0];

        if ($clientId <= 0) {
            return ['success' => false, 'message' => $program === 'TODA' ? 'Invalid ID' : 'Invalid client ID'];
        }

        $alreadySaved = DB::table('tbl_transactions')
            ->where('client_id', $clientId)
            ->where('program', $program)
            ->where('date_applied', $dateApplied)
            ->exists();

        if ($alreadySaved) {
            $response = [
                'success' => false,
                'alreadySaved' => true,
                'message' => $this->duplicateMessage($config, $dateApplied),
            ];

            if ($config['duplicate']['show_existing'] ?? false) {
                $response['existing'] = DB::table('tbl_transactions')
                    ->where('client_id', $clientId)
                    ->where('program', $program)
                    ->where('date_applied', $dateApplied)
                    ->orderBy('id')
                    ->first(['id', 'date_applied', 'date_paid', 'status', 'remarks', 'suggested_amount']);
            }

            return $response;
        }

        $client = Client::query()->find($clientId);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $insert = $config['insert'];
        $amountPaid = $insert['amount_paid'] === 'input' ? ($input['amount_paid'] ?? 0) : $insert['amount_paid'];

        $transaction = Transaction::query()->create([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => $client->full_name,
            'date_applied' => $dateApplied,
            'date_paid' => $datePaid,
            'type' => $insert['type'],
            'remarks' => $insert['remarks'],
            'suggested_amount' => $insert['suggested_amount'],
            'status' => $insert['status'],
            'amount_paid' => $amountPaid,
        ]);

        $this->writeAudit($config, $actor, $transaction->id, [
            'program' => $program,
            'client_id' => $clientId,
            'patient_name' => $client->full_name,
            'amount_paid' => $amountPaid,
            'remarks' => $insert['remarks'],
            'status' => $insert['status'],
        ]);

        return ['success' => true];
    }

    private function saveUpdateInPlace(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $transactionId = (int) ($input['transaction_id'] ?? 0);
        $datePaid = $input['date_paid'] ?? null;

        $update = $config['update'];

        $values = [
            'status' => $update['status'],
            'date_paid' => $datePaid,
            'amount_paid' => $update['amount_paid'],
        ];

        DB::table('tbl_transactions')->where('id', $transactionId)->update($values);

        $this->writeAudit($config, $actor, $transactionId, $values);

        return ['success' => true];
    }

    private function saveExamDerived(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $clientId = (int) ($input['id'] ?? 0);

        if ($clientId <= 0) {
            return ['success' => false, 'message' => 'Invalid client ID'];
        }

        $client = Client::query()->find($clientId);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $exam = DB::table('tbl_exam')
            ->whereRaw('TRIM(fullname) COLLATE utf8mb4_general_ci = ?', [$client->full_name])
            ->first(['exam_no']);

        if ($exam === null) {
            return ['success' => false, 'message' => 'No exam record found'];
        }

        $result = DB::table('tbl_results')->where('exam_no', $exam->exam_no)->first(['approved']);

        if ($result === null || empty($result->approved)) {
            return ['success' => false, 'message' => 'No approved scholarship'];
        }

        $program = strtoupper(trim((string) $result->approved));
        $programConfig = $config['programs'][$program] ?? null;

        if ($programConfig === null) {
            return ['success' => false, 'message' => "Unknown program: $program"];
        }

        if ($this->remarkKeyDuplicateExists($clientId, $program, $programConfig['remarks'])) {
            return ['success' => false, 'message' => 'Transaction already recorded for this client.'];
        }

        $transaction = Transaction::query()->create([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => $client->full_name,
            'date_applied' => date('Y-m-d'),
            'type' => $config['insert']['type'],
            'remarks' => $programConfig['remarks'],
            'suggested_amount' => $programConfig['suggested_amount'],
            'status' => $config['insert']['status'],
            'payout_date' => $programConfig['payout_date'],
        ]);

        $this->writeAudit($config, $actor, $transaction->id, [
            'program' => $program,
            'client_id' => $clientId,
            'patient_name' => $client->full_name,
            'remarks' => $programConfig['remarks'],
            'status' => $config['insert']['status'],
        ]);

        return ['success' => true];
    }

    private function saveValidateExisting(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $clientId = (int) ($input['id'] ?? 0);

        if ($clientId <= 0) {
            return ['success' => false, 'message' => 'Invalid client'];
        }

        $client = Client::query()->find($clientId);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $patientName = $client->full_name;

        $txn = DB::table('tbl_transactions')
            ->where('client_id', $clientId)
            ->whereIn('program', array_keys($config['programs']))
            ->orderByDesc('id')
            ->first(['program']);

        if ($txn === null) {
            return ['success' => false, 'message' => 'Program not found'];
        }

        $program = $txn->program;
        $programConfig = $config['programs'][$program];

        if ($this->remarkKeyDuplicateExists($clientId, $program, $programConfig['remarks'], $patientName)) {
            return ['success' => false, 'message' => 'Transaction already recorded'];
        }

        Transaction::query()->create([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => $patientName,
            'date_applied' => date('Y-m-d'),
            'type' => $config['insert']['type'],
            'remarks' => $programConfig['remarks'],
            'suggested_amount' => $programConfig['suggested_amount'],
            'status' => $config['insert']['status'],
        ]);

        return ['success' => true];
    }

    private function saveAttendance(string $key, array $input, User $actor): array
    {
        $config = $this->config($key);
        $table = $config['attendance'] ?? null;

        if ($table === null) {
            return ['success' => false, 'message' => 'Invalid request.'];
        }

        $transactionId = (int) ($input['id'] ?? 0);
        $scanned = trim((string) ($input['scanned'] ?? ''));
        $userId = $actor->id;

        if ($transactionId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => $config['mode'] === 'unpaid_attendance' ? 'Invalid transaction.' : 'Invalid transaction or client.'];
        }

        try {
            DB::table($table)->insert([
                'transaction_id' => $transactionId,
                'scanned_text' => $scanned,
                'scanned_by' => $userId,
            ]);

            $response = ['success' => true];

            if (! empty($config['ui']['success_message'])) {
                $response['message'] = $config['ui']['success_message'];
            }

            return $response;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return ['success' => false, 'message' => 'This QR code has already been scanned.'];
            }

            return ['success' => false, 'message' => 'Database error: '.$e->getMessage()];
        }
    }

    private function saveGenericForm(string $key, array $input): array
    {
        $config = $this->config($key);
        $clientId = (int) ($input['client_id'] ?? 0);

        if ($clientId <= 0) {
            return ['success' => false, 'message' => 'Invalid client ID'];
        }

        $patientName = $this->resolveGenericPatient($input);

        if ($patientName === null || $patientName === '') {
            return ['success' => false, 'message' => 'Beneficiary is required'];
        }

        $transaction = Transaction::query()->create([
            'client_id' => $clientId,
            'patient_name' => $patientName,
            'program' => $input['program'] ?? null,
            'date_applied' => ! empty($input['date_applied']) ? $input['date_applied'] : null,
            'type' => $input['type'] ?? null,
            'status' => $input['status'] ?? null,
            'remarks' => ! empty($input['remarks']) ? $input['remarks'] : null,
            'comments' => ! empty($input['comments']) ? $input['comments'] : null,
            'suggested_amount' => ($input['suggested_amount'] ?? '') !== '' ? $input['suggested_amount'] : null,
            'amount_paid' => ($input['amount_paid'] ?? '') !== '' ? $input['amount_paid'] : null,
            'payout_date' => ! empty($input['payout_date']) ? $input['payout_date'] : null,
            'date_paid' => ! empty($input['date_paid']) ? $input['date_paid'] : null,
            'gwa' => ($input['gwa'] ?? '') !== '' ? $input['gwa'] : null,
            'units' => ($input['units'] ?? '') !== '' ? $input['units'] : null,
        ]);

        return ['success' => true, 'message' => 'Transaction saved successfully'];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function findClientByName(string $name): ?Client
    {
        return Client::query()
            ->whereRaw('TRIM(full_name) COLLATE utf8mb4_general_ci = ?', [$name])
            ->first();
    }

    private function municipalityName(int $id): string
    {
        $name = DB::table('tbl_municipalities')->where('id', $id)->value('name');

        return $name !== null ? (string) $name : '';
    }

    private function barangayName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $name = DB::table('tbl_barangays')->where('id', $id)->value('name');

        return $name !== null ? (string) $name : '';
    }

    private function remarkKeyDuplicateExists(int $clientId, string $program, string $remarks, ?string $patientName = null): bool
    {
        $query = DB::table('tbl_transactions')
            ->where('client_id', $clientId)
            ->where('program', $program)
            ->where('remarks', $remarks);

        if ($patientName !== null) {
            $query->where('patient_name', $patientName);
        }

        return $query->exists();
    }

    private function alreadyScanned(array $config, int $transactionId): bool
    {
        $table = $config['attendance'] ?? null;

        if ($table === null) {
            return false;
        }

        return DB::table($table)->where('transaction_id', $transactionId)->exists();
    }

    private function missMessage(array $config, string $scanned): string
    {
        $message = $config['lookup_miss_message'] ?? "Client not found for scanned code: '{scanned}'";

        return str_replace('{scanned}', $scanned, $message);
    }

    private function duplicateMessage(array $config, ?string $dateApplied = null): string
    {
        $message = $config['duplicate']['message'] ?? 'Transaction already recorded.';

        if ($dateApplied !== null && str_contains($message, '{date}')) {
            $message = str_replace('{date}', $dateApplied, $message);
        }

        return $message;
    }

    private function resolveGenericPatient(array $input): ?string
    {
        $option = $input['patient_option'] ?? 'self';

        if ($option === 'custom') {
            return trim((string) ($input['patient_name_custom'] ?? '')) ?: null;
        }

        $clientId = (int) ($option === 'existing' ? ($input['existing_client_id'] ?? 0) : ($input['client_id'] ?? 0));

        if ($clientId <= 0) {
            return null;
        }

        $client = Client::query()->find($clientId);

        return $client?->full_name;
    }

    private function writeAudit(array $config, User $actor, int $targetId, array $values): void
    {
        $audit = $config['audit'] ?? null;

        if ($audit === null || empty($audit['action'])) {
            return;
        }

        $action = $audit['action'];

        if (str_contains($action, '{program}')) {
            $action = str_replace('{program}', (string) ($values['program'] ?? ''), $action);
        }

        $payload = [];

        foreach ($audit['fields'] ?? [] as $field) {
            $payload[$field] = $audit['values'][$field] ?? $values[$field] ?? null;
        }

        $this->auditService->log(
            $actor->id,
            $action,
            'tbl_transactions',
            $targetId,
            null,
            json_encode($payload)
        );
    }
}
