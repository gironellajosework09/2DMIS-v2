<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\PhotoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of the v1 student self-service flow (student_update_photo.php →
 * student_verify.php → student_photo_upload.php). Public routes: a student
 * searches their name, proves identity with birthdate + mobile number, then
 * updates their own profile photo. Identity is held in the
 * 'verified_student' session key exactly like v1.
 */
class StudentController extends Controller
{
    private const SCHOLAR_PROGRAMS = ['CEAP', 'CEAP_NEW', 'CEDSSG', 'CEDSSG_NEW', 'OTEA', 'OTCES'];

    public function __construct(private readonly PhotoService $photos) {}

    public function updatePhoto(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $results = [];

        if ($search !== '') {
            $like = '%'.$search.'%';

            $results = DB::table('tbl_transactions as t')
                ->join('tbl_clients as c', 't.client_id', '=', 'c.id')
                ->select('c.id', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname')
                ->distinct()
                ->whereIn('t.program', self::SCHOLAR_PROGRAMS)
                ->where(function ($q) use ($like) {
                    $q->where('c.lastname', 'like', $like)
                        ->orWhere('c.firstname', 'like', $like)
                        ->orWhere(DB::raw("CONCAT(c.lastname, ', ', c.firstname)"), 'like', $like);
                })
                ->orderBy('c.lastname')
                ->orderBy('c.firstname')
                ->get();
        }

        return view('students.update-photo', [
            'search' => $search,
            'results' => $results,
        ]);
    }

    public function verify(Request $request, Client $client): View|RedirectResponse
    {
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'birthdate' => ['required', 'date'],
                'mobile' => ['required', 'string', 'max:50'],
            ]);

            $inputDate = Carbon::parse($validated['birthdate'])->format('Y-m-d');
            $clientDate = Carbon::parse((string) $client->birthdate)->format('Y-m-d');
            $clientMobile = trim((string) $client->mobile_no);

            if ($inputDate === $clientDate && trim($validated['mobile']) === $clientMobile) {
                session(['verified_student' => $client->id]);

                return redirect()->route('student.photo-upload');
            }

            return back()->withErrors(['verification' => 'Verification failed. Please check your details.']);
        }

        return view('students.verify', ['client' => $client]);
    }

    public function photoUpload(): View|RedirectResponse
    {
        $clientId = session('verified_student');

        if (! $clientId) {
            return redirect()->route('student.update-photo');
        }

        $client = Client::query()->with('photos')->find($clientId);

        if (! $client) {
            return redirect()->route('student.update-photo');
        }

        return view('students.photo-upload', ['client' => $client]);
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $clientId = session('verified_student');

        if (! $clientId) {
            return redirect()->route('student.update-photo');
        }

        $validated = $request->validate([
            'camera_image' => ['required', 'string'],
        ]);

        try {
            $this->photos->store((int) $clientId, null, $validated['camera_image']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['camera_image' => $e->getMessage()]);
        }

        $request->session()->forget('verified_student');

        return redirect()
            ->route('student.photo-upload')
            ->with('success', 'Profile photo updated successfully!');
    }
}
