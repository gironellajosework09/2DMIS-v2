<?php

namespace App\Http\Controllers;

use App\Services\AccessControlService;
use App\Services\PhotoService;
use App\Support\RecordMunicipality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(
        private readonly PhotoService $photos,
        private readonly AccessControlService $acl,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:tbl_clients,id'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'camera_image' => ['nullable', 'string'],
        ]);

        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient((int) $validated['client_id']), 'clients.php')
            || abort(403, 'Access denied.');

        try {
            $this->photos->store(
                (int) $validated['client_id'],
                $request->file('photo'),
                $request->input('camera_image'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('clients.show', (int) $validated['client_id'])
                ->withErrors(['photo' => $e->getMessage()]);
        }

        return redirect()
            ->route('clients.show', (int) $validated['client_id'])
            ->with('success', 'Client photo updated successfully.');
    }
}
