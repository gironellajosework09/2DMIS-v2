<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\FamilyMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FamilyMemberController extends Controller
{
    public function __construct(private readonly FamilyMemberService $family) {}

    public function create(Client $client): View
    {
        return view('family_members.create', ['parent' => $client]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'existing_client_id' => ['required', 'integer', 'exists:tbl_clients,id'],
            'relationship' => ['required', 'string', 'max:50'],
        ], [
            'existing_client_id.required' => 'Please search for and select the family member.',
        ]);

        if ((int) $validated['existing_client_id'] === $client->id) {
            return redirect()->back()->withErrors(['existing_client_id' => 'A client cannot be their own family member.']);
        }

        $this->family->link($client, (int) $validated['existing_client_id'], $validated['relationship'], $request->user());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Family member added successfully!');
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || strlen($q) < 2) {
            return response()->json([]);
        }

        $words = preg_split('/\s+/', $q) ?: [];

        $query = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->select(['c.id', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname', 'c.birthdate', 'c.age', 'c.sex', 'c.civil_status', 'c.occupation', 'c.mobile_no', 'c.house_no', 'c.city_municipality', 'c.barangay', 'c.monthly_income', 'c.precinct_no', 'c.voter_id', 'm.name as municipality_name', 'b.name as barangay_name']);

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
}
