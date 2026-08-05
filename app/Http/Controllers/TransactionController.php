<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Municipality;
use App\Models\Transaction;
use App\Services\AccessControlService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly AccessControlService $acl,
    ) {}

    public function index(): View
    {
        return view('transactions.index', [
            'municipalities' => Municipality::query()->orderBy('name')->get(),
            'programs' => $this->programsForUser(auth()->user()),
        ]);
    }

    public function create(Client $client): View
    {
        return view('transactions.create', [
            'client' => $client,
            'programs' => $this->programsForUser(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:tbl_clients,id'],
            'program' => ['required', 'string'],
            'patient_option' => ['required', 'in:self,custom,existing'],
            'date_applied' => ['required', 'date'],
            'type' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'comments' => ['nullable', 'string'],
            'suggested_amount' => ['nullable', 'numeric'],
            'status' => ['required', 'in:'.implode(',', TransactionService::STATUSES)],
            'amount_paid' => ['nullable', 'numeric'],
            'payout_date' => ['nullable', 'date'],
            'date_paid' => ['nullable', 'date'],
            'gwa' => ['nullable', 'numeric'],
            'units' => ['nullable', 'numeric'],
        ]);

        $client = Client::query()->findOrFail($request->integer('client_id'));

        $this->authorizeProgram($request->user(), $validated['program']);

        $patientName = $this->transactions->resolvePatientName(
            $validated['patient_option'],
            $client,
            $request->all(),
        );

        $data = [
            'client_id' => $client->id,
            'program' => $validated['program'],
            'patient_name' => $patientName,
            'date_applied' => $validated['date_applied'],
            'type' => strtoupper($validated['type']),
            'remarks' => strtoupper(trim((string) ($validated['remarks'] ?? ''))) ?: null,
            'comments' => strtoupper(trim((string) ($validated['comments'] ?? ''))) ?: null,
            'suggested_amount' => $request->filled('suggested_amount') ? (float) $validated['suggested_amount'] : null,
            'status' => $validated['status'],
            'amount_paid' => $request->filled('amount_paid') ? (float) $validated['amount_paid'] : null,
            'payout_date' => $request->filled('payout_date') ? $validated['payout_date'] : null,
            'date_paid' => $request->filled('date_paid') ? $validated['date_paid'] : null,
            'gwa' => $request->filled('gwa') ? (float) $validated['gwa'] : null,
            'units' => $request->filled('units') ? (float) $validated['units'] : null,
        ];

        if ($data['program'] === 'TUPAD') {
            $data['comments'] = null;
            $data['payout_date'] = null;
            $data['gwa'] = null;
            $data['units'] = null;
        }

        $transaction = $this->transactions->create($data, $request->user());

        return redirect()
            ->route('transactions.show', $transaction->id)
            ->with('success', 'Transaction added successfully!');
    }

    public function show(int $id): View
    {
        $transaction = Transaction::query()
            ->with('client')
            ->findOrFail($id);

        return view('transactions.show', compact('transaction'));
    }

    public function edit(int $id): View
    {
        $transaction = Transaction::query()->findOrFail($id);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'programs' => $this->programsForUser(auth()->user()),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'program' => ['required', 'string'],
            'patient_option' => ['required', 'in:self,custom,existing'],
            'date_applied' => ['required', 'date'],
            'type' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'suggested_amount' => ['nullable', 'numeric'],
            'status' => ['required', 'in:'.implode(',', TransactionService::STATUSES)],
            'amount_paid' => ['nullable', 'numeric'],
            'payout_date' => ['nullable', 'date'],
            'date_paid' => ['nullable', 'date'],
        ]);

        $transaction = Transaction::query()->findOrFail($id);

        $this->authorizeProgram($request->user(), $validated['program']);

        $client = $transaction->client;
        $patientName = $client
            ? $this->transactions->resolvePatientName($validated['patient_option'], $client, $request->all())
            : ($request->filled('patient_name_custom') ? strtoupper(trim($request->string('patient_name_custom'))) : null);

        $data = [
            'program' => $validated['program'],
            'patient_name' => $patientName,
            'date_applied' => $validated['date_applied'],
            'type' => strtoupper($validated['type']),
            'remarks' => strtoupper(trim((string) ($validated['remarks'] ?? ''))) ?: null,
            'suggested_amount' => $request->filled('suggested_amount') ? (float) $validated['suggested_amount'] : null,
            'status' => $validated['status'],
            'amount_paid' => $request->filled('amount_paid') ? (float) $validated['amount_paid'] : null,
            'payout_date' => $request->filled('payout_date') ? $validated['payout_date'] : null,
            'date_paid' => $request->filled('date_paid') ? $validated['date_paid'] : null,
        ];

        $this->transactions->update($id, $data, $request->user());

        return redirect()
            ->route('transactions.show', $id)
            ->with('success', 'Transaction updated successfully!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->transactions->destroy($id, $request->user());

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Port of v1 update_transaction.php — inline edit of a DataTables row.
     * Returns a plain text token so the jQuery port can branch on it.
     */
    public function inlineUpdate(Request $request): JsonResponse
    {
        $id = $request->integer('id');
        $normalizeAmount = function ($v) {
            if ($v === null || $v === '') {
                return null;
            }
            $v = str_replace(',', '', (string) $v);

            return is_numeric($v) ? (float) $v : null;
        };

        $datePaid = null;
        if ($request->filled('date_paid')) {
            $datePaid = date('Y-m-d', strtotime($request->string('date_paid')));
        }

        $data = [
            'remarks' => $request->input('remarks'),
            'comments' => $request->input('comments'),
            'suggested_amount' => $normalizeAmount($request->input('suggested_amount')),
            'status' => $request->input('status'),
            'amount_paid' => $normalizeAmount($request->input('amount_paid')),
            'date_paid' => $datePaid,
            'gwa' => $normalizeAmount($request->input('gwa')),
            'units' => $normalizeAmount($request->input('units')),
        ];

        try {
            $this->transactions->update($id, $data, $request->user());

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Live search for the beneficiary picker (v1 search_clients.php). Scans
     * all clients, gated by the transactions page rather than clients.php.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || strlen($q) < 2) {
            return response()->json([]);
        }

        $words = preg_split('/\s+/', $q) ?: [];

        $query = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->select(['c.id', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname', 'm.name as municipality_name', 'b.name as barangay_name']);

        foreach ($words as $word) {
            $like = "%{$word}%";
            $query->where(function ($q) use ($like) {
                $q->where('c.firstname', 'like', $like)
                    ->orWhere('c.lastname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('c.extensionname', 'like', $like)
                    ->orWhere('c.full_name', 'like', $like);
            });
        }

        $rows = $query
            ->orderByRaw('CASE WHEN c.firstname LIKE ? THEN 0 WHEN c.lastname LIKE ? THEN 1 WHEN c.full_name LIKE ? THEN 2 ELSE 3 END, c.lastname, c.firstname', ["{$q}%", "{$q}%", "{$q}%"])
            ->limit(15)
            ->get();

        return response()->json($rows);
    }

    /**
     * v1 all_transactions.php feed. Program access is enforced the same way:
     * restricted users only see (and can only filter to) their programs.
     */
    public function data(Request $request): JsonResponse
    {
        $draw = $request->integer('draw', 1);
        $start = $request->integer('start', 0);
        $length = $request->integer('length', 25);
        $searchValue = trim((string) $request->input('search.value', ''));

        $programFilter = trim((string) $request->input('program', ''));
        $statusFilter = trim((string) $request->input('status', ''));
        $municipalityFilter = $request->integer('municipality', 0);
        $barangayFilter = $request->integer('barangay', 0);
        $dateAppliedStart = trim((string) $request->input('date_applied_start', ''));
        $dateAppliedEnd = trim((string) $request->input('date_applied_end', ''));
        $datePaidStart = trim((string) $request->input('date_paid_start', ''));
        $datePaidEnd = trim((string) $request->input('date_paid_end', ''));

        $allowedPrograms = $this->acl->permittedPrograms($request->user());

        $query = DB::table('tbl_transactions as t')
            ->leftJoin('tbl_clients as c', 't.client_id', '=', 'c.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id');

        $whereForbidden = false;

        if (! empty($allowedPrograms)) {
            if ($programFilter !== '') {
                if (! in_array($programFilter, $allowedPrograms, true)) {
                    $whereForbidden = true;
                } else {
                    $query->where('t.program', $programFilter);
                }
            } else {
                $query->whereIn('t.program', $allowedPrograms);
            }
        } elseif ($programFilter !== '') {
            $query->where('t.program', $programFilter);
        }

        if ($statusFilter !== '') {
            $query->where('t.status', $statusFilter);
        }
        if ($municipalityFilter > 0) {
            $query->where('c.city_municipality', $municipalityFilter);
        }
        if ($barangayFilter > 0) {
            $query->where('c.barangay', $barangayFilter);
        }
        if ($dateAppliedStart !== '' && $dateAppliedEnd !== '') {
            $query->whereBetween('t.date_applied', [$dateAppliedStart, $dateAppliedEnd]);
        }
        if ($datePaidStart !== '' && $datePaidEnd !== '') {
            $query->whereBetween('t.date_paid', [$datePaidStart, $datePaidEnd]);
        }

        if ($searchValue !== '') {
            $like = '%'.$searchValue.'%';
            $query->where(function ($q) use ($like) {
                $q->where('c.lastname', 'like', $like)
                    ->orWhere('c.firstname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('c.extensionname', 'like', $like)
                    ->orWhere('t.program', 'like', $like)
                    ->orWhere('t.patient_name', 'like', $like)
                    ->orWhere('t.remarks', 'like', $like)
                    ->orWhere('t.status', 'like', $like)
                    ->orWhere('t.type', 'like', $like)
                    ->orWhere('t.suggested_amount', 'like', $like)
                    ->orWhere('t.payout_date', 'like', $like);
            });
        }

        $totalCount = $whereForbidden ? 0 : DB::table('tbl_transactions')->count();
        $filteredCount = $whereForbidden ? 0 : (clone $query)->count();

        $columnsMap = [
            0 => 't.id',
            1 => 't.id',
            2 => 't.client_id',
            3 => 't.date_applied',
            4 => 't.program',
            5 => 'c.lastname',
            6 => 't.patient_name',
            7 => 'c.mobile_no',
            8 => 'b.name',
            9 => 'm.name',
            10 => 't.type',
            11 => 't.remarks',
            12 => 't.comments',
            13 => 't.suggested_amount',
            14 => 't.status',
            15 => 't.amount_paid',
            16 => 't.payout_date',
            17 => 't.date_paid',
            18 => 't.gwa',
            19 => 't.units',
            20 => 't.created_at',
        ];

        $orderColumnIndex = (int) $request->input('order.0.column', 5);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = $columnsMap[$orderColumnIndex] ?? 't.created_at';

        $rows = $whereForbidden ? collect() : (clone $query)
            ->select([
                't.*',
                'c.lastname',
                'c.firstname',
                'c.middlename',
                'c.extensionname',
                'c.birthdate',
                'c.sex',
                'c.province',
                'c.mobile_no',
                'b.name as barangay_name',
                'm.name as municipality_name',
            ])
            ->orderBy($orderColumn, $orderDir)
            ->limit($length)
            ->offset($start)
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row->id,
                'client_id' => $row->client_id,
                'date_applied' => $row->date_applied ? date('m/d/Y', strtotime($row->date_applied)) : '',
                'program' => e((string) $row->program),
                'client_name' => e(trim($row->lastname.', '.$row->firstname.' '.$row->middlename.' '.$row->extensionname)),
                'patient_name' => e((string) $row->patient_name),
                'mobile_no' => e((string) $row->mobile_no),
                'barangay' => e((string) $row->barangay_name),
                'city_municipality' => e((string) $row->municipality_name),
                'type' => e((string) $row->type),
                'remarks' => e((string) $row->remarks),
                'comments' => e((string) $row->comments),
                'suggested_amount' => $row->suggested_amount !== null ? number_format((float) $row->suggested_amount, 2) : '',
                'status' => e((string) $row->status),
                'amount_paid' => $row->amount_paid !== null ? number_format((float) $row->amount_paid, 2) : '',
                'payout_date' => $row->payout_date ? date('m/d/Y', strtotime($row->payout_date)) : '',
                'date_paid' => $row->date_paid ? date('m/d/Y', strtotime($row->date_paid)) : '',
                'gwa' => e((string) $row->gwa),
                'units' => e((string) $row->units),
                'created_at' => Carbon::parse($row->created_at)->setTimezone('Asia/Manila')->format('m/d/Y - h:i A'),
                'actions' => '<button type="button" class="btn btn-sm btn-warning edit-btn">Edit</button> '
                    .'<button type="button" class="btn btn-sm btn-success save-btn d-none">Save</button> '
                    .'<button type="button" class="btn btn-sm btn-secondary cancel-btn d-none">Cancel</button> '
                    .'<button type="button" class="btn btn-sm btn-danger delete-transaction" data-id="'.$row->id.'">Delete</button>',
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $mode = (string) $request->query('export_mode', 'csv');

        $query = DB::table('tbl_transactions as t')
            ->leftJoin('tbl_clients as c', 't.client_id', '=', 'c.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id');

        $this->applyExportFilters($query, $request, $request->user());

        $fileName = match ($mode) {
            'custom' => 'transactions_custom_'.date('Ymd'),
            'custom2' => 'transactions_custom2_'.date('Ymd'),
            'gip' => 'gip_report_'.date('Ymd'),
            default => 'transactions_'.date('Ymd'),
        };

        return response()->streamDownload(function () use ($mode, $query) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            match ($mode) {
                'custom' => $this->writeCustomCsv($out, $query),
                'custom2' => $this->writeCustom2Csv($out, $query),
                'gip' => $this->writeGipCsv($out, $query),
                default => $this->writeStandardCsv($out, $query),
            };

            fclose($out);
        }, $fileName.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function applyExportFilters($query, Request $request, $user): void
    {
        $programFilter = trim((string) $request->query('program', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $municipalityFilter = $request->integer('municipality', 0);
        $barangayFilter = $request->integer('barangay', 0);
        $dateAppliedStart = trim((string) $request->query('date_applied_start', ''));
        $dateAppliedEnd = trim((string) $request->query('date_applied_end', ''));
        $datePaidStart = trim((string) $request->query('date_paid_start', ''));
        $datePaidEnd = trim((string) $request->query('date_paid_end', ''));

        $allowedPrograms = $this->acl->permittedPrograms($user);

        if (! empty($allowedPrograms)) {
            if ($programFilter !== '' && in_array($programFilter, $allowedPrograms, true)) {
                $query->where('t.program', $programFilter);
            } elseif ($programFilter === '') {
                $query->whereIn('t.program', $allowedPrograms);
            }
        } elseif ($programFilter !== '') {
            $query->where('t.program', $programFilter);
        }

        if ($statusFilter !== '') {
            $query->where('t.status', $statusFilter);
        }
        if ($municipalityFilter > 0) {
            $query->where('c.city_municipality', $municipalityFilter);
        }
        if ($barangayFilter > 0) {
            $query->where('c.barangay', $barangayFilter);
        }
        if ($dateAppliedStart !== '' && $dateAppliedEnd !== '') {
            $query->whereBetween('t.date_applied', [$dateAppliedStart, $dateAppliedEnd]);
        }
        if ($datePaidStart !== '' && $datePaidEnd !== '') {
            $query->whereBetween('t.date_paid', [$datePaidStart, $datePaidEnd]);
        }
    }

    private function writeStandardCsv($out, $query): void
    {
        fputcsv($out, [
            'Date Applied', 'Program', 'Client Name', 'Patient Name', 'Mobile No',
            'Barangay', 'Municipality', 'Type', 'Remarks', 'Comments',
            'Suggested Amount', 'Status', 'Amount Paid', 'Pay Out Date', 'Date Paid',
            'GWA', 'Units', 'Created At',
        ]);

        foreach ($query->get() as $row) {
            fputcsv($out, [
                $row->date_applied, $row->program,
                trim($row->lastname.', '.$row->firstname.' '.$row->middlename.' '.$row->extensionname),
                $row->patient_name, $row->mobile_no, $row->barangay_name, $row->municipality_name,
                $row->type, $row->remarks, $row->comments, $row->suggested_amount, $row->status,
                $row->amount_paid, $row->payout_date, $row->date_paid, $row->gwa, $row->units,
                $row->created_at,
            ]);
        }
    }

    private function writeCustomCsv($out, $query): void
    {
        fputcsv($out, [
            'Date Applied', 'Program', 'Lastname', 'Firstname', 'Middlename', 'Extensionname',
            'Birthdate', 'Sex', 'Civil Status', 'Barangay', 'Municipality', 'Province',
            'Suggested Amount', 'Contact Number', 'Status', 'Remarks', 'Comments', 'Full Name',
        ]);

        foreach ($query->get() as $row) {
            fputcsv($out, [
                $row->date_applied, $row->program, $row->lastname, $row->firstname,
                $row->middlename, $row->extensionname, $row->birthdate ?? '', $row->sex ?? '',
                $row->civil_status ?? '', $row->barangay_name ?? '', $row->municipality_name ?? '',
                $row->province ?? '', $row->suggested_amount, $row->mobile_no, $row->status,
                $row->remarks ?? '', $row->comments ?? '',
                trim($row->lastname.', '.$row->firstname.' '.$row->middlename.' '.($row->extensionname ?? '')),
            ]);
        }
    }

    private function writeCustom2Csv($out, $query): void
    {
        $query->leftJoin('tbl_scholar_info as s', 's.client_id', '=', 'c.id');

        fputcsv($out, [
            'Date Applied', 'Program', 'Lastname', 'Firstname', 'Middlename', 'Extensionname',
            'Birthdate', 'Sex', 'Civil Status', 'Barangay', 'Municipality', 'Province',
            'IP', 'IP Group', 'Email', 'Contact Number', 'Suggested Amount', 'Status',
            'Remarks', 'Comments', 'Full Name', 'School', 'Course', 'Year Level',
        ]);

        foreach ($query->get(['t.date_applied', 't.program', 't.remarks', 't.comments', 't.suggested_amount', 't.status', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname', 'c.birthdate', 'c.sex', 'c.civil_status', 'c.province', 'c.ip', 'c.ip_group', 'c.email', 'c.mobile_no', 'b.name as barangay_name', 'm.name as municipality_name', 's.school', 's.course', 's.year_level']) as $row) {
            fputcsv($out, [
                $row->date_applied, $row->program, $row->lastname, $row->firstname,
                $row->middlename, $row->extensionname, $row->birthdate ?? '', $row->sex ?? '',
                $row->civil_status ?? '', $row->barangay_name ?? '', $row->municipality_name ?? '',
                $row->province ?? '', $row->ip ?? '', $row->ip_group ?? '', $row->email ?? '',
                $row->mobile_no, $row->suggested_amount, $row->status, $row->remarks ?? '',
                $row->comments ?? '',
                trim($row->lastname.', '.$row->firstname.' '.$row->middlename.' '.($row->extensionname ?? '')),
                $row->school ?? '', $row->course ?? '', $row->year_level ?? '',
            ]);
        }
    }

    private function writeGipCsv($out, $query): void
    {
        $query->leftJoin('tbl_scholar_info as s', 's.client_id', '=', 'c.id');
        $query->leftJoin('tbl_gip_info as g', 'g.client_id', '=', 'c.id');

        fputcsv($out, [
            'Date Applied', 'Program', 'Lastname', 'Firstname', 'Middlename', 'Extensionname',
            'Birthdate', 'Sex', 'Civil Status', 'Barangay', 'Municipality', 'Province',
            'Email', 'Contact Number', 'Suggested Amount', 'Status', 'Remarks', 'Comments', 'Full Name',
            'School', 'Scholar Course', 'Year Level',
            'Valid Government ID', 'ID Number', 'Insurance Beneficiary', 'Emergency Contact',
            'Emergency Contact Number', 'Emergency Contact Address', 'College', 'GIP Course',
            'Year Graduated', 'High School', 'Elementary School', 'Latest Work Experience',
            'Position', 'Period of Engagement', 'Special Skills', 'Achievements',
        ]);

        foreach ($query->get(['t.date_applied', 't.program', 't.remarks', 't.comments', 't.suggested_amount', 't.status', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname', 'c.birthdate', 'c.sex', 'c.civil_status', 'c.province', 'c.email', 'c.mobile_no', 'b.name as barangay_name', 'm.name as municipality_name', 's.school', 's.course as scholar_course', 's.year_level', 'g.valid_govt_id', 'g.id_number', 'g.insurance_beneficiary', 'g.emergency_contact', 'g.ecp_contact_number', 'g.ecp_address', 'g.college', 'g.course as gip_course', 'g.year_graduated', 'g.high_school', 'g.elementary_school', 'g.latest_work_experience', 'g.position', 'g.period_of_engagement', 'g.special_skills', 'g.achievements']) as $row) {
            fputcsv($out, [
                $row->date_applied, $row->program, $row->lastname, $row->firstname,
                $row->middlename, $row->extensionname, $row->birthdate ?? '', $row->sex ?? '',
                $row->civil_status ?? '', $row->barangay_name ?? '', $row->municipality_name ?? '',
                $row->province ?? '', $row->email ?? '', $row->mobile_no, $row->suggested_amount,
                $row->status, $row->remarks ?? '', $row->comments ?? '',
                trim($row->lastname.', '.$row->firstname.' '.$row->middlename.' '.($row->extensionname ?? '')),
                $row->school ?? '', $row->scholar_course ?? '', $row->year_level ?? '',
                $row->valid_govt_id ?? '', $row->id_number ?? '', $row->insurance_beneficiary ?? '',
                $row->emergency_contact ?? '', $row->ecp_contact_number ?? '', $row->ecp_address ?? '',
                $row->college ?? '', $row->gip_course ?? '', $row->year_graduated ?? '',
                $row->high_school ?? '', $row->elementary_school ?? '', $row->latest_work_experience ?? '',
                $row->position ?? '', $row->period_of_engagement ?? '', $row->special_skills ?? '',
                $row->achievements ?? '',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function programsForUser($user): array
    {
        $allowed = $this->acl->permittedPrograms($user);

        if (! empty($allowed)) {
            return array_values(array_intersect(TransactionService::PROGRAMS, $allowed));
        }

        return TransactionService::PROGRAMS;
    }

    private function authorizeProgram($user, string $program): void
    {
        $allowed = $this->acl->permittedPrograms($user);

        if (! empty($allowed) && ! in_array($program, $allowed, true)) {
            abort(403, 'Unauthorized program selection.');
        }
    }
}
