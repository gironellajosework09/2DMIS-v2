<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeographyController extends Controller
{
    /**
     * Port of v1 get_barangays.php — fills the municipality → barangay cascade.
     */
    public function barangays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'municipality_id' => ['required', 'integer', 'exists:tbl_municipalities,id'],
        ]);

        $barangays = Barangay::query()
            ->where('municipality_id', (int) $validated['municipality_id'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($barangays);
    }
}
