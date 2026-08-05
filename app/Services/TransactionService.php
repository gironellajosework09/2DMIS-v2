<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(private readonly AuditService $auditService) {}

    public const PROGRAMS = [
        'AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP', 'CEAP_NEW', 'CEDSSG_NEW',
        'OTEA', 'OTCES', 'COFFEE GROWERS', 'PUSO TI KABABAIHAN', 'PUSO TI AGTUTUBO',
        'PUSO TI MANNALON', 'TESDA', 'GIP', 'TODA',
    ];

    public const TYPES = [
        'CRA', 'OCA', 'CASH FOR WORK', 'MEDICAL', 'BURIAL', 'FOOD SUBSIDY',
        'SCHOLARSHIP', 'MEMBERSHIP', 'SKILLS TRAINING', 'INTERNSHIP PROGRAM',
        'CASH RELIEF ASSISTANCE', 'ATTENDANCE',
    ];

    public const STATUSES = ['PENDING PAYOUT', 'PAID'];

    public function create(array $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($data, $actor) {
            $transaction = Transaction::query()->create($data);

            $this->auditService->log(
                $actor->id,
                'ADD_TRANSACTION',
                'tbl_transactions',
                $transaction->id,
                null,
                json_encode([
                    'client_id' => $data['client_id'],
                    'program' => $data['program'],
                    'patient_name' => $data['patient_name'],
                    'date_applied' => $data['date_applied'],
                    'type' => $data['type'],
                    'remarks' => $data['remarks'],
                    'comments' => $data['comments'],
                    'suggested_amount' => $data['suggested_amount'],
                    'status' => $data['status'],
                    'amount_paid' => $data['amount_paid'],
                    'payout_date' => $data['payout_date'],
                    'date_paid' => $data['date_paid'],
                    'gwa' => $data['gwa'],
                    'units' => $data['units'],
                ])
            );

            return $transaction;
        });
    }

    public function update(int $id, array $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $transaction = Transaction::query()->findOrFail($id);

            $old = json_encode($transaction->only([
                'program', 'patient_name', 'date_applied', 'type', 'remarks',
                'comments', 'suggested_amount', 'status', 'amount_paid',
                'payout_date', 'date_paid', 'gwa', 'units',
            ]));

            $transaction->fill($data)->save();

            $this->auditService->log(
                $actor->id,
                'EDIT_TRANSACTION',
                'tbl_transactions',
                $transaction->id,
                $old,
                json_encode($transaction->only([
                    'program', 'patient_name', 'date_applied', 'type', 'remarks',
                    'comments', 'suggested_amount', 'status', 'amount_paid',
                    'payout_date', 'date_paid', 'gwa', 'units',
                ]))
            );

            return $transaction;
        });
    }

    public function destroy(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $transaction = Transaction::query()->findOrFail($id);

            $old = json_encode($transaction->only([
                'client_id', 'program', 'patient_name', 'date_applied', 'type',
                'remarks', 'comments', 'suggested_amount', 'status', 'amount_paid',
                'payout_date', 'date_paid', 'gwa', 'units',
            ]));

            $transaction->delete();

            $this->auditService->log(
                $actor->id,
                'DELETE_TRANSACTION',
                'tbl_transactions',
                $id,
                $old,
                null
            );
        });
    }

    /**
     * v1 resolves the beneficiary from the patient_option radio group. The
     * "self" option stores the full client name (lastname, firstname middle).
     */
    public function resolvePatientName(string $option, Client $client, ?array $input): ?string
    {
        return match ($option) {
            'self' => trim($client->lastname.', '.$client->firstname.' '.$client->middlename),
            'custom' => strtoupper(trim((string) ($input['patient_name_custom'] ?? ''))) ?: null,
            'existing' => $this->existingClientName((int) ($input['existing_client_id'] ?? 0)),
            default => null,
        };
    }

    private function existingClientName(int $id): ?string
    {
        $client = Client::query()->find($id);

        return $client
            ? trim($client->lastname.', '.$client->firstname.' '.$client->middlename)
            : null;
    }
}
