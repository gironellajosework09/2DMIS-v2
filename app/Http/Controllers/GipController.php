<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AccessControlService;
use App\Services\GipService;
use App\Support\RecordMunicipality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * P6 GIP details — v1 save_gip.php port.
 *
 * The GIP form in v1 is embedded in view_client.php#collapseGIP (only shown
 * for clients that have a GIP transaction) and POSTs to save_gip.php. The v2
 * equivalent is a modal on the client profile page posting to this store(),
 * gated by the same clients.php page key that guards the profile itself.
 */
class GipController extends Controller
{
    public function __construct(
        private readonly GipService $gipService,
        private readonly AccessControlService $acl,
    ) {}

    public function store(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'client_id' => ['required', 'integer', 'exists:tbl_clients,id'],
        ]);

        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        $this->gipService->save($request->input(), $request->user()->id);

        return redirect()
            ->to(route('clients.show', $client).'#collapseGIP')
            ->with('success', 'GIP details saved.');
    }
}
