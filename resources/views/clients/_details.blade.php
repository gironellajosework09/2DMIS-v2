{{-- Batch E migration: client profile, rendered in TWO modes that must
     both keep working — full page (clients.show) and the index details
     panel (?panel=1 fetch + executeScripts() re-execution). The
     inline <script> blocks below MUST stay inline for that re-execution
     (and are wrapped in IIFEs so re-parsing never redeclares bindings).
     Functional Bootstrap kept: photo modal structure + d-none toggles
     (JS-coupled). Delete uses the shared uiConfirm dialog (Batch F) —
     inline when the panel is open, native full-page redirect otherwise.

     Info-hierarchy restructure: the fixed DetailsPanel shell already owns
     the identity header (data-panel-title/sub/avatar/meta → fixed header)
     and the action bar (data-panel-actions → Add Transaction / Open Full
     Page / Edit / Delete, each gated by the same ACL checks as full-page
     mode). The body
     ([data-panel-body]) therefore carries ONLY the scannable content
     sections. In full-page mode the same sections render under a heading.

     Audit data: tbl_clients has solely `created_at` (no created_by /
     updated_at / updated_by columns). Created By and Last Updated By/At
     are read (never written here) from the existing tbl_audit_logs
     contract that AuditService writes and AuditController reads —
     ADD_CLIENT for creation, the newest EDIT_CLIENT for last update.
     Values that genuinely have no source row are shown as "—". --}}
@php
    use Illuminate\Support\Facades\DB;

    $photo = $client->currentPhoto();
    $isPanel = $panel ?? false;
    $hasGipTransaction = $client->transactions->contains('program', 'GIP');
    $user = auth()->user();
    $acl = app(\App\Services\AccessControlService::class);
    $permittedActions = $acl->permittedActions($user, 'clients.php');
    $canEdit = in_array('EDIT', $permittedActions, true);
    $canDelete = in_array('DELETE', $permittedActions, true);

    $clientIdLabel = (string) ($client->household->household_id ?? $client->id);

    // PWD / IP combined, built from real values only (both are stored enums).
    $pwdIpParts = array_values(array_filter([
        trim((string) $client->pwd) === '' ? null : trim((string) $client->pwd),
        trim((string) $client->ip) === '' ? null : trim((string) $client->ip),
    ]));
    $pwdIp = implode(' / ', $pwdIpParts);

    // Avatar initials (photo-fallback), derived from real name fields only.
    $initials = strtoupper(
        mb_substr(trim((string) $client->firstname), 0, 1)
        .mb_substr(trim((string) $client->lastname), 0, 1)
    );
    if ($initials === '') {
        $initials = '?';
    }

    // --- Audit: read-only from the existing tbl_audit_logs contract -------
    $auditLogs = DB::table('tbl_audit_logs')
        ->where('target_table', 'tbl_clients')
        ->where('target_id', $client->id)
        ->orderBy('id')
        ->get();

    $createLog = $auditLogs->firstWhere('action', 'ADD_CLIENT');
    $updateLog = $auditLogs->where('action', 'EDIT_CLIENT')->sortByDesc('id')->first();

    $auditUserIds = collect([$createLog->user_id ?? null, $updateLog->user_id ?? null])
        ->filter()
        ->unique()
        ->values();
    $usernames = $auditUserIds->isNotEmpty()
        ? DB::table('tbl_users')->whereIn('id', $auditUserIds)->pluck('username', 'id')
        : collect();

    $createdBy = $createLog !== null
        ? ($usernames[$createLog->user_id] ?? '—')
        : '—';
    $createdAt = $client->created_at ? \Illuminate\Support\Carbon::parse($client->created_at)
        ->setTimezone('Asia/Manila')->format('m/d/Y - h:i A') : '—';
    $updatedBy = $updateLog !== null
        ? ($usernames[$updateLog->user_id] ?? '—')
        : '—';
    $updatedAt = $updateLog !== null
        ? \Illuminate\Support\Carbon::parse($updateLog->created_at)
            ->setTimezone('Asia/Manila')->format('m/d/Y - h:i A')
        : '—';
@endphp

{{-- Identity block fed to the fixed panel header. Kept OUTSIDE
     [data-panel-body] so it does not duplicate in the scrolling body.
     Fits the shell's 64px rounded avatar (photo or initials placeholder). --}}
<div data-panel-avatar style="display:none;">
    @if ($photo)
        <img class="details-avatar-photo"
            src="{{ asset('storage/uploads/client_photos/'.$photo->photo_path) }}"
            alt="{{ $client->full_name }}" width="64" height="64">
    @else
        <span class="details-avatar-initials">{{ $initials }}</span>
    @endif
</div>

<div class="data-card p-[1.25rem]" data-panel-body>
    @if (! $isPanel)
        {{-- Full-page sticky profile header: identity (photo / name / ID ·
             category) plus the page actions, pinned to the top of the
             scrolling area so profile context stays visible while reading
             the client's sections below. Wraps cleanly on narrow widths. --}}
        <div class="details-full-header">
            <div class="details-full-head">
                <div class="mb-[16px] flex items-center gap-[12px]">
                    <h1 class="m-0 text-page-title font-heading font-bold text-ink">Client Profile</h1>
                    <div class="flex flex-1 flex-wrap items-center justify-end gap-[8px]">
                        <a href="{{ route('clients.index') }}" class="btn-subtle no-underline">Back</a>
                        @if ($acl->canAccessPage($user, 'all_transactions.php'))
                            <a href="{{ route('transactions.create', $client) }}" class="btn-navy no-underline">+ Add Transaction</a>
                        @endif
                        @if ($canEdit)
                            <button type="button" class="btn-gold" data-edit-client-modal="{{ $client->id }}">Edit</button>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline mb-0"
                                data-delete-client-form data-message="Are you sure you want to delete this client? This cannot be undone.">
                                @csrf
                                <button type="submit" class="btn-red">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Identity (the panel header supplies this in panel mode, so
                     it is only rendered on the standalone page). --}}
                <div class="mb-[12px] flex items-start gap-[16px]">
                    <div class="shrink-0">
                        @if ($photo)
                            <img src="{{ asset('storage/uploads/client_photos/'.$photo->photo_path) }}" alt="Client photo"
                                class="rounded-panel ring-1 ring-line object-cover" style="width:96px;height:96px;">
                        @else
                            <div class="grid place-items-center rounded-panel ring-1 ring-line text-dense text-ink-muted"
                                style="width:96px;height:96px;">No photo</div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-dense font-semibold text-ink">{{ $client->full_name }}</div>
                        <div class="mt-[2px] text-dense text-ink-muted">ID: {{ $clientIdLabel }}</div>
                        <div class="mt-[2px]"><span class="status-badge is-neutral">{{ $client->category }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- PERSONAL INFORMATION --}}
    <div class="details-section">
        <h4 class="details-section-title">Personal Information</h4>
        <div class="details-grid">
            <div class="details-field"><label>Last Name</label><span class="value">{{ $client->lastname ?: '—' }}</span></div>
            <div class="details-field"><label>First Name</label><span class="value">{{ $client->firstname ?: '—' }}</span></div>
            <div class="details-field"><label>Middle Name</label><span class="value {{ trim((string) $client->middlename) === '' ? 'muted' : '' }}">{{ $client->middlename ?: '—' }}</span></div>
            <div class="details-field"><label>Extension Name</label><span class="value {{ trim((string) $client->extensionname) === '' ? 'muted' : '' }}">{{ $client->extensionname ?: '—' }}</span></div>
            <div class="details-field"><label>Date of Birth</label><span class="value {{ trim((string) $client->birthdate) === '' ? 'muted' : '' }}">{{ $client->birthdate ?: '—' }}</span></div>
            <div class="details-field"><label>Age</label><span class="value {{ $client->age === null ? 'muted' : '' }}">{{ $client->age ?? '—' }}</span></div>
            <div class="details-field"><label>Gender</label><span class="value {{ trim((string) $client->sex) === '' ? 'muted' : '' }}">{{ $client->sex ?: '—' }}</span></div>
            <div class="details-field"><label>Civil Status</label><span class="value {{ trim((string) $client->civil_status) === '' ? 'muted' : '' }}">{{ $client->civil_status ?: '—' }}</span></div>
        </div>
    </div>

    {{-- CONTACT INFORMATION & ADDRESS --}}
    <div class="details-section">
        <h4 class="details-section-title">Contact Information &amp; Address</h4>
        <div class="details-grid">
            <div class="details-field"><label>Mobile Number</label><span class="value {{ trim((string) $client->mobile_no) === '' ? 'muted' : '' }}">{{ $client->mobile_no ?: '—' }}</span></div>
            <div class="details-field"><label>Email</label><span class="value {{ trim((string) $client->email) === '' ? 'muted' : '' }}">{{ $client->email ?: '—' }}</span></div>
            <div class="details-field"><label>Barangay</label><span class="value {{ empty($client->barangayInfo->name) ? 'muted' : '' }}">{{ $client->barangayInfo->name ?? '—' }}</span></div>
            <div class="details-field"><label>Municipality / City</label><span class="value {{ empty($client->municipality->name) ? 'muted' : '' }}">{{ $client->municipality->name ?? '—' }}</span></div>
            <div class="details-field"><label>Province</label><span class="value {{ trim((string) $client->province) === '' ? 'muted' : '' }}">{{ $client->province ?: '—' }}</span></div>
            <div class="details-field"><label>Region</label><span class="value {{ trim((string) $client->region) === '' ? 'muted' : '' }}">{{ $client->region ?: '—' }}</span></div>
        </div>
    </div>

    {{-- ADDITIONAL INFORMATION --}}
    <div class="details-section">
        <h4 class="details-section-title">Additional Information</h4>
        <div class="details-grid">
            <div class="details-field"><label>PWD / IP</label><span class="value {{ $pwdIp === '' ? 'muted' : '' }}">{{ $pwdIp ?: '—' }}</span></div>
            <div class="details-field"><label>IP Group</label><span class="value {{ trim((string) $client->ip_group) === '' ? 'muted' : '' }}">{{ $client->ip_group ?: '—' }}</span></div>
            <div class="details-field"><label>Occupation</label><span class="value {{ trim((string) $client->occupation) === '' ? 'muted' : '' }}">{{ $client->occupation ?: '—' }}</span></div>
            <div class="details-field"><label>Monthly Income</label><span class="value {{ $client->monthly_income === null ? 'muted' : '' }}">{{ $client->monthly_income ?? '—' }}</span></div>
            <div class="details-field wide"><label>Affiliated Organization</label><span class="value {{ $client->affOrgs->isEmpty() ? 'muted' : '' }}">{{ $client->affOrgs->pluck('organization')->join(', ') ?: '—' }}</span></div>
            <div class="details-field"><label>Precinct No.</label><span class="value {{ trim((string) $client->precinct_no) === '' ? 'muted' : '' }}">{{ $client->precinct_no ?: '—' }}</span></div>
            <div class="details-field"><label>Voter's ID</label><span class="value {{ trim((string) $client->voter_id) === '' ? 'muted' : '' }}">{{ $client->voter_id ?: '—' }}</span></div>
        </div>
    </div>

    {{-- HOUSEHOLD INFORMATION --}}
    <div class="details-section">
        <h4 class="details-section-title">Household Information</h4>
        <div class="details-grid">
            <div class="details-field"><label>House No.</label><span class="value {{ trim((string) $client->house_no) === '' ? 'muted' : '' }}">{{ $client->house_no ?: '—' }}</span></div>
            <div class="details-field">
                <label>Household</label>
                @if ($client->household)
                    <a href="{{ route('households.show', $client->household) }}" class="value no-underline text-navy hover:text-navy-hover hover:underline">{{ $client->household->household_id }}</a>
                @else
                    <span class="value muted">—</span>
                @endif
            </div>
        </div>
        @if ($client->household && $client->household->headClient)
            <div class="mt-[12px] details-field">
                <label>Head of Household</label>
                <span class="value">{{ $client->household->headClient->full_name ?: '—' }}</span>
            </div>
        @endif
    </div>

    {{-- FAMILY COMPOSITION (collapsible) --}}
    <div class="details-section">
        <h4 class="details-section-title">
            <button type="button" class="details-accordion-toggle" id="famToggle"
                aria-expanded="false" aria-controls="famPanel">
                <span>Family Composition</span>
                <svg class="details-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        </h4>
        <div id="famPanel" class="details-accordion-panel" role="region" aria-labelledby="famToggle" hidden>
            @if (! $isPanel)
                <div class="mb-[12px] text-right">
                    <a href="{{ route('family-members.create', $client) }}" class="btn-subtle no-underline">+ Add Family Member</a>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full min-w-[24rem] border-collapse text-left text-dense">
                    <thead>
                        <tr class="border-b border-line">
                            <th scope="col" class="ui-micro-label py-2 pr-[16px]">Name</th>
                            <th scope="col" class="ui-micro-label py-2">Relationship</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($client->familyMembers as $member)
                            <tr class="border-b border-line-light">
                                <td class="py-2 pr-[16px]">{{ $member->relative->full_name ?? '—' }}</td>
                                <td class="py-2">{{ $member->relationship ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-[16px] text-center text-dense text-ink-muted">No family members linked.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TRANSACTIONS (collapsible) --}}
    <div class="details-section">
        <h4 class="details-section-title">
            <button type="button" class="details-accordion-toggle" id="txToggle"
                aria-expanded="false" aria-controls="txPanel">
                <span>Transactions</span>
                <svg class="details-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        </h4>
        <div id="txPanel" class="details-accordion-panel" role="region" aria-labelledby="txToggle" hidden>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] border-collapse text-left text-dense">
                    <thead>
                        <tr class="border-b border-line">
                            <th scope="col" class="ui-micro-label py-2 pr-[16px]">ID</th>
                            <th scope="col" class="ui-micro-label py-2 pr-[16px]">Program</th>
                            <th scope="col" class="ui-micro-label py-2 pr-[16px]">Date Applied</th>
                            <th scope="col" class="ui-micro-label py-2 pr-[16px]">Status</th>
                            <th scope="col" class="ui-micro-label py-2 pr-[16px] text-right">Amount</th>
                            <th scope="col" class="ui-micro-label py-2">Payout Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($client->transactions as $transaction)
                            <tr class="border-b border-line-light">
                                <td class="py-2 pr-[16px] tabular-nums">{{ $transaction->id }}</td>
                                <td class="py-2 pr-[16px]">{{ $transaction->program }}</td>
                                <td class="py-2 pr-[16px]">{{ $transaction->date_applied }}</td>
                                <td class="py-2 pr-[16px]">
                                    @php($statusClass = match (true) {
                                        $transaction->status === 'PAID' => 'is-paid',
                                        str_starts_with((string) $transaction->status, 'PENDING') => 'is-pending',
                                        default => 'is-neutral',
                                    })
                                    <span class="status-badge {{ $statusClass }}">{{ $transaction->status }}</span>
                                </td>
                                <td class="py-2 pr-[16px] text-right tabular-nums">{{ $transaction->amount_paid }}</td>
                                <td class="py-2">{{ $transaction->payout_date }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-[16px] text-center text-dense text-ink-muted">No transactions recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('clients._gip')

    {{-- AUDIT INFORMATION --}}
    <div class="details-section">
        <h4 class="details-section-title">Audit Information</h4>
        <div class="details-grid">
            <div class="details-field"><label>Created By</label><span class="value {{ $createdBy === '—' ? 'muted' : '' }}">{{ $createdBy }}</span></div>
            <div class="details-field"><label>Created At</label><span class="value">{{ $createdAt }}</span></div>
            <div class="details-field"><label>Last Updated By</label><span class="value {{ $updatedBy === '—' ? 'muted' : '' }}">{{ $updatedBy }}</span></div>
            <div class="details-field"><label>Last Updated At</label><span class="value {{ $updatedAt === '—' ? 'muted' : '' }}">{{ $updatedAt }}</span></div>
        </div>
    </div>

    {{-- Panel is now view-only — Edit opens the modal via data-panel-actions --}}
</div>

<div data-panel-title style="display:none;">{{ $client->full_name }}</div>
<div data-panel-sub style="display:none;">ID: {{ $clientIdLabel }}</div>
<div data-panel-meta style="display:none;">
    <span class="status-badge is-neutral details-category">{{ $client->category }}</span>
</div>
<div data-panel-actions style="display:none;">
    <div class="details-actions-line">
        @if ($acl->canAccessPage($user, 'all_transactions.php'))
            <a href="{{ route('transactions.create', $client) }}" class="btn btn-navy no-underline">+ Add Transaction</a>
        @endif
        <a href="{{ route('clients.show', $client) }}" class="btn btn-subtle no-underline">Open Full Page</a>
        @if ($canEdit)
            <button type="button" class="btn btn-gold" data-edit-client-modal="{{ $client->id }}">Edit</button>
        @endif
        @if ($canDelete)
            <form method="POST" action="{{ route('clients.destroy', $client) }}" data-delete-client-form
                data-message="Are you sure you want to delete this client? This cannot be undone." class="d-inline">
                @csrf
                <button type="submit" class="btn btn-red">Delete</button>
            </form>
        @endif
    </div>
</div>

<style>[x-cloak]{display:none}</style>

{{-- Tailwind + Alpine migration (Phase 10): the client photo modal
     (#photoModal) moved off Bootstrap JS. It is an upload/camera surface
     (identical to the two other modules), preserved verbatim (IDs, field
     names, endpoint, CSRF). All Bootstrap JS deps for this modal removed:
     no data-bs-toggle/data-bs-dismiss, no bootstrap.Modal, no shown.bs.modal
     / hidden.bs.modal events — replaced by the Alpine component's open()
     / close() lifecycle which runs the same DOM reset logic. --}}
<div id="photoModal"
     x-data="clientPhotoModal()"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-labelledby="photoModalTitle"
     aria-describedby="photoModalBody"
     class="pointer-events-none fixed inset-0 z-[200]">
    <div x-show="open"
         x-transition.opacity.duration.200ms
         @click="close()"
         class="pointer-events-auto absolute inset-0 bg-ink/40"
         aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
        <div x-ref="dialog"
             x-show="open"
             x-transition.opacity.duration.200ms
             @keydown.escape="close()"
             @keydown.tab.prevent.stop="handleTab($event)"
             class="pointer-events-auto flex w-full max-w-[600px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
            <form method="POST" action="{{ route('clients.photo.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="flex items-center justify-between gap-2 border-b border-line p-[1.25rem] pb-3">
                    <h5 id="photoModalTitle" class="mb-0 text-dense font-heading font-semibold text-ink">Client Profile Photo</h5>
                    <button type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-ink-muted transition duration-150 ease-standard hover:bg-surface-hover hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="close()"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div id="photoModalBody" class="text-center p-[1.25rem]">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="camera_image" id="cameraImage">
                    <video id="video" width="100%" autoplay x-show="showVideo"></video>
                    <canvas id="canvas" class="hidden"></canvas>
                    <img id="capturedPreview" x-show="showPreview" class="mb-[8px] w-full rounded-panel" alt="Captured preview">
                    <input type="file" name="photo" id="photoFile" class="form-control mb-[8px]" accept="image/*">
                    <div class="flex justify-center gap-[8px]">
                        <button type="button" class="btn-subtle" id="startCameraBtn" x-show="showStartBtn" @click="startCamera()">Use Camera</button>
                        <button type="button" class="btn-navy" id="captureBtn" x-show="showCaptureBtn" @click="capture()">Capture</button>
                        <button type="button" class="btn-subtle" id="retakeBtn" x-show="showRetakeBtn" @click="retake()">Retake</button>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-line p-[1.25rem] pt-3">
                    <button type="button" class="btn-subtle" @click="close()">Cancel</button>
                    <button class="btn-navy">Save Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        window.clientPhotoModal = function () {
            return {
                open: false,
                showVideo: false,
                showPreview: false,
                showStartBtn: true,
                showCaptureBtn: false,
                showRetakeBtn: false,
                _stream: null,
                _prevFocus: null,

                openModal: function () {
                    // shown.bs.modal equivalent: reveal the file picker and
                    // clear any stale captured preview.
                    this._prevFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    this.open = true;
                    var self = this;
                    this.$nextTick(function () {
                        self.showStartBtn = true;
                        self.showCaptureBtn = false;
                        self.showRetakeBtn = false;
                        self.showVideo = false;
                        self.showPreview = false;
                        var input = document.getElementById('photoFile');
                        if (!input) return;
                        input.hidden = false;
                        var dlg = self.$refs.dialog;
                        if (dlg) {
                            var first = dlg.querySelector('input:not([type=hidden]), button:not([aria-label="Close"])');
                            if (first) first.focus();
                        }
                    });
                },

                close: function () {
                    // hidden.bs.modal equivalent: stop the camera stream and
                    // reset the camera controls.
                    this._stopStream();
                    this.open = false;
                    this.showVideo = false;
                    this.showStartBtn = true;
                    this.showCaptureBtn = false;
                    this.showRetakeBtn = false;
                    this.showPreview = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                },

                startCamera: async function () {
                    try {
                        var s = await navigator.mediaDevices.getUserMedia({ video: true });
                        this._stream = s;
                        var video = document.getElementById('video');
                        if (video) video.srcObject = s;
                        this.showVideo = true;
                        this.showStartBtn = false;
                        this.showCaptureBtn = true;
                    } catch (err) {
                        alert('Camera access denied or not available.');
                    }
                },

                capture: function () {
                    var video = document.getElementById('video');
                    var canvas = document.getElementById('canvas');
                    var preview = document.getElementById('capturedPreview');
                    var cameraImage = document.getElementById('cameraImage');
                    if (!video || !canvas || !preview || !cameraImage) return;
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    var imageData = canvas.toDataURL('image/jpeg', 0.9);
                    cameraImage.value = imageData;
                    preview.src = imageData;
                    this.showPreview = true;
                    this.showVideo = false;
                    this.showCaptureBtn = false;
                    this.showRetakeBtn = true;
                    this._stopStream();
                },

                retake: function () {
                    this.showPreview = false;
                    var cameraImage = document.getElementById('cameraImage');
                    if (cameraImage) cameraImage.value = '';
                    this.showVideo = true;
                    this.showRetakeBtn = false;
                    this.showCaptureBtn = true;
                },

                _stopStream: function () {
                    if (this._stream) {
                        this._stream.getTracks().forEach(function (t) { t.stop(); });
                        this._stream = null;
                    }
                },

                handleTab: function (e) {
                    var dlg = this.$refs.dialog;
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };

        // Framework-agnostic imperative bridge (matching the openEditModal /
        // uiConfirm pattern) so any caller can open the photo modal without
        // touching Alpine internals.
        window.openClientPhotoModal = function () {
            var el = document.getElementById('photoModal');
            if (el && el._x_dataStack) {
                Alpine.$data(el).openModal();
            }
        };
    })();
</script>

<script>
    (function () {
        'use strict';
        // Panel is view-only. Edit opens the modal (handled by the index script
        // via data-edit-client-modal). Only the delete flow stays here.

        // Delete: confirm via the shared uiConfirm dialog. While the panel is
        // open it is sent as JSON (panel closes + index refreshes via the
        // details:deleted event); otherwise the form submits natively so the
        // redirect + flash flow runs unchanged.
        document.querySelectorAll('[data-delete-client-form]').forEach(function (delForm) {
            if (!window.uiConfirm) return;
            delForm.addEventListener('submit', function (e) {
                e.preventDefault();
                window.uiConfirm({
                    message: delForm.getAttribute('data-message') || 'Are you sure?',
                    confirmLabel: 'Delete'
                }).then(function (ok) {
                    if (!ok) return;
                    if (window.DetailsPanel && window.DetailsPanel.isOpen && window.DetailsPanel.isOpen()) {
                        fetch(delForm.getAttribute('action'), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: new FormData(delForm)
                        })
                            .then(function (r) { return r.json().catch(function () { return {}; }); })
                            .then(function (res) {
                                if (res.success) {
                                    window.DetailsPanel.close();
                                    document.dispatchEvent(new CustomEvent('details:deleted', {
                                        detail: { message: res.message || 'Client deleted successfully.' }
                                    }));
                                } else {
                                    document.dispatchEvent(new CustomEvent('details:error', {
                                        detail: { message: res.message || 'Could not delete client.' }
                                    }));
                                }
                            })
                            .catch(function () {
                                document.dispatchEvent(new CustomEvent('details:error', {
                                    detail: { message: 'Could not delete client.' }
                                }));
                            });
                        return;
                    }
                    HTMLFormElement.prototype.submit.call(delForm);
                });
            });
        });
    })();
</script>

<script>
    (function () {
        'use strict';
        // Accessible collapsibles for Family Composition and Transactions.
        // Re-executed by DetailsPanel.executeScripts() on every panel load.
        function bindAccordion(toggleId, panelId) {
            const toggle = document.getElementById(toggleId);
            const panel = document.getElementById(panelId);
            if (!toggle || !panel) return;

            function setOpen(open) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    panel.removeAttribute('hidden');
                    toggle.classList.add('is-open');
                } else {
                    panel.setAttribute('hidden', '');
                    toggle.classList.remove('is-open');
                }
            }

            toggle.addEventListener('click', function () {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                setOpen(!open);
            });
        }

        bindAccordion('famToggle', 'famPanel');
        bindAccordion('txToggle', 'txPanel');
    })();
</script>

<script>
    (function () {
        'use strict';
        // Full-page Edit button: reuse the SAME #clientFormModal + submission
        // flow the index page owns.  When the index page is loaded, its own
        // openEditModal / clientFormModal / submit handler already exist — this
        // block exits immediately.  On the full-page (show.blade.php) none of
        // those exist, so we create the modal on demand using Alpine (Phase 8)
        // and wire the same fetch-based submission + photo-upload path.
        if (typeof window.openEditModal === 'function') return;

        // Alpine store must exist before any Alpine-powered modal can open.
        document.addEventListener('alpine:init', function () {
            if (Alpine.store('clientFormModal')) return;
            Alpine.store('clientFormModal', {
                open: false,
                title: 'Edit Client',
                subtitle: 'Update client information',
                submitLabel: 'Save Client',
                _prevFocus: null,
                show: function (mode, id) {
                    var isEdit = mode === 'edit';
                    this.title = isEdit ? 'Edit Client' : 'Add Client';
                    this.subtitle = isEdit ? 'Update client information' : 'Register a new client in the registry';
                    this.submitLabel = isEdit ? 'Save Client' : 'Add Client';
                    this._prevFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    this.open = true;
                    var body = document.getElementById('clientFormModalBody');
                    if (body) {
                        body.innerHTML = '<div class="flex items-center justify-center py-[2rem] text-ink-muted"><span>Loading form...</span></div>';
                        var url = '{{ route("clients.edit", "__ID__") }}'.replace('__ID__', id) + '?modal=1';
                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.text(); })
                            .then(function (html) {
                                body.innerHTML = html;
                                var form = body.querySelector('form');
                                if (form) form.dataset.clientId = id;
                                executeScripts(body);
                            })
                            .catch(function () {
                                body.innerHTML = '<div class="flex items-center justify-center py-[2rem] text-danger">Failed to load form.</div>';
                            });
                    }
                },
                hide: function () {
                    this.open = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                    var body = document.getElementById('clientFormModalBody');
                    if (body) body.innerHTML = '';
                }
            });

            // Feedback modal store (Phase 9) — full-page / panel fallback when
            // the index partial is not present. Guarded against redeclaration.
            if (!Alpine.store('clientFeedbackModal')) {
                Alpine.store('clientFeedbackModal', {
                    open: false,
                    title: 'Notice',
                    type: 'info',
                    _onHidden: null,
                    show: function (options) {
                        options = options || {};
                        var type = options.type || 'info';
                        this.type = type;
                        this.title = options.title || (type === 'warning'
                            ? 'Review before continuing'
                            : 'Fix these before continuing');
                        this._onHidden = typeof options.onHidden === 'function' ? options.onHidden : null;
                        var body = document.getElementById('clientFeedbackBody');
                        var actions = document.getElementById('clientFeedbackActions');
                        if (body) {
                            body.innerHTML = '';
                            if (options.body) body.appendChild(options.body);
                        }
                        if (actions) {
                            actions.innerHTML = '';
                            if (options.actions) actions.appendChild(options.actions);
                        }
                        document.body.style.overflow = 'hidden';
                        this._prevFocus = document.activeElement;
                        this.open = true;
                        Alpine.nextTick(function () {
                            var dlg = document.getElementById('clientFeedbackModal');
                            if (!dlg) return;
                            var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                            var target = dlg.querySelector('#clientFeedbackActions button');
                            if (!target && focusables.length) target = focusables[0];
                            if (target && target.focus) target.focus();
                        });
                    },
                    hide: function () {
                        if (!this.open) return;
                        this.open = false;
                        var formStore = Alpine.store('clientFormModal');
                        var formOpen = formStore && formStore.open;
                        if (!formOpen) {
                            document.body.style.overflow = '';
                        }
                        var prev = this._prevFocus;
                        var cb = this._onHidden;
                        this._onHidden = null;
                        this._prevFocus = null;
                        if (prev && typeof prev.focus === 'function' && document.contains(prev)) {
                            prev.focus();
                        }
                        if (typeof cb === 'function') cb();
                    }
                });
            }
        });

        window.clientFormModalComponent = function () {
            return {
                handleTab: function (e) {
                    var dlg = document.getElementById('clientFormModal');
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };

        window.clientFeedbackModalComponent = function () {
            return {
                handleTab: function (e) {
                    var dlg = document.getElementById('clientFeedbackModal');
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };

        function getOrCreateModal() {
            var el = document.getElementById('clientFormModal');
            if (!el) {
                el = document.createElement('div');
                el.id = 'clientFormModal';
                el.setAttribute('x-data', 'clientFormModalComponent()');
                el.setAttribute('role', 'dialog');
                el.setAttribute('aria-modal', 'true');
                el.setAttribute('aria-labelledby', 'cfmTitle');
                el.setAttribute('aria-describedby', 'clientFormModalBody');
                el.className = 'pointer-events-none fixed inset-0 z-[200]';
                el.innerHTML =
                    '<div x-show="$store.clientFormModal.open" x-transition.opacity.duration.200ms class="pointer-events-auto absolute inset-0 bg-ink/40" aria-hidden="true"></div>'
                    + '<div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">'
                    + '<div x-show="$store.clientFormModal.open" x-ref="dialog" x-transition.opacity.duration.200ms @keydown.tab.prevent.stop="handleTab($event)" class="pointer-events-auto flex w-full max-w-[800px] max-h-[90vh] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">'
                    + '<div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">'
                    + '<div class="min-w-0">'
                    + '<h5 id="cfmTitle" class="mb-0 text-dense font-heading font-semibold text-white" x-text="$store.clientFormModal.title"></h5>'
                    + '<p id="cfmSubtitle" class="mb-0 mt-0.5 text-sm text-white/80" x-text="$store.clientFormModal.subtitle"></p>'
                    + '</div>'
                    + '<button type="button" class="cfm-close inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold" @click="$store.clientFormModal.hide()" aria-label="Close">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                    + '</button>'
                    + '</div>'
                    + '<div id="clientFormModalBody" class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]"></div>'
                    + '<div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">'
                    + '<button type="button" class="btn-subtle" data-fp-edit-cancel>Cancel</button>'
                    + '<button type="submit" form="clientForm" class="btn-gold" id="clientFormSubmit" x-text="$store.clientFormModal.submitLabel">Save Client</button>'
                    + '</div>'
                    + '</div></div>';
                document.body.appendChild(el);
                Alpine.initTree(el);
            }
            return el;
        }

        function executeScripts(container) {
            container.querySelectorAll('script').forEach(function (old) {
                var fresh = document.createElement('script');
                fresh.textContent = old.textContent;
                old.parentNode.replaceChild(fresh, old);
            });
        }

        function photoValidationError(input) {
            var f = input && input.files && input.files[0];
            if (!f) return null;
            if (['image/jpeg', 'image/png', 'image/gif'].indexOf(f.type) === -1) {
                return 'Only JPG, PNG, or GIF images are allowed.';
            }
            if (f.size > 1024 * 1024) {
                return 'Photo must be 1MB or smaller.';
            }
            return null;
        }

        function showToast(message) {
            var stack = document.getElementById('clientsToastStack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'clientsToastStack';
                stack.className = 'pointer-events-none fixed inset-x-0 top-20 z-[1100] flex flex-col items-end gap-2 px-[1rem] sm:px-[1.75rem]';
                stack.setAttribute('aria-live', 'polite');
                document.body.appendChild(stack);
            }
            if (!message) return;
            // Tailwind + Alpine migration (Phase 9): no Bootstrap Toast JS —
            // revealed with `.show`, dismissed manually, autohide false by
            // contract (persistent until the user closes).
            var el = document.createElement('div');
            el.className = 'toast pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line';
            el.setAttribute('role', 'status');
            el.innerHTML = '<span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">'
                + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>'
                + '</span>'
                + '<div class="min-w-0 flex-1 text-dense leading-snug text-ink">' + message + '</div>'
                + '<button type="button" class="btn-close shrink-0" aria-label="Close"></button>';
            stack.appendChild(el);
            el.classList.add('show');
            el.querySelector('button[aria-label="Close"]').addEventListener('click', function () { el.remove(); });
        }

        function getOrCreateFeedbackModal() {
            var el = document.getElementById('clientFeedbackModal');
            if (!el) {
                el = document.createElement('div');
                el.id = 'clientFeedbackModal';
                el.setAttribute('x-data', 'clientFeedbackModalComponent()');
                el.setAttribute('role', 'dialog');
                el.setAttribute('aria-modal', 'true');
                el.setAttribute('aria-labelledby', 'clientFeedbackTitle');
                el.className = 'pointer-events-none fixed inset-0 z-[210]';
                el.innerHTML =
                    '<div x-show="$store.clientFeedbackModal.open" x-transition.opacity.duration.200ms @click="$store.clientFeedbackModal.hide()" class="pointer-events-auto absolute inset-0 bg-ink/40" aria-hidden="true"></div>'
                    + '<div class="pointer-events-none absolute inset-0 flex items-center justify-center p-4">'
                    + '<div x-show="$store.clientFeedbackModal.open" x-ref="dialog" x-transition.opacity.duration.200ms @keydown.escape="$store.clientFeedbackModal.hide()" @keydown.tab.prevent.stop="handleTab($event)" class="pointer-events-auto flex w-full max-w-[500px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">'
                    + '<div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">'
                    + '<h5 id="clientFeedbackTitle" class="text-dense font-heading font-semibold text-white" x-text="$store.clientFeedbackModal.title"></h5>'
                    + '<button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold" @click="$store.clientFeedbackModal.hide()" aria-label="Close">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                    + '</button>'
                    + '</div>'
                    + '<div id="clientFeedbackBody" class="min-h-0 max-h-[60vh] overflow-y-auto p-[1.25rem] text-dense leading-snug text-ink"></div>'
                    + '<div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">'
                    + '<div id="clientFeedbackActions" class="flex flex-wrap items-center justify-end gap-[8px]"></div>'
                    + '<button type="button" class="btn-subtle" @click="$store.clientFeedbackModal.hide()">Close</button>'
                    + '</div>'
                    + '</div></div>';
                document.body.appendChild(el);
                Alpine.initTree(el);
            }
            return el;
        }

        function showFeedback(title, type, message) {
            getOrCreateFeedbackModal();
            var body = document.createElement('div');
            body.innerHTML = '<p class="mb-0">' + message + '</p>';
            Alpine.store('clientFeedbackModal').show({ title: title || 'Notice', type: type, body: body });
        }

        window.openEditModal = function (id) {
            getOrCreateModal();
            Alpine.store('clientFormModal').show('edit', id);
            var body = document.getElementById('clientFormModalBody');
            wireFormSubmit(body, id);
        };

        function wireFormSubmit(body, clientId) {
            body.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                e.preventDefault();
                var photoInput = form.querySelector('input[name="photo"]');
                var photoFile = photoInput && photoInput.files && photoInput.files[0];
                var photoErr = photoValidationError(photoInput);
                if (photoErr) {
                    showFeedback('Photo error', 'error', photoErr);
                    return;
                }
                var submitBtn = form.querySelector('button[type="submit"]') || document.getElementById('clientFormSubmit');
                if (submitBtn) submitBtn.disabled = true;
                fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (data) {
                    if (data.success) {
                        var photoPost = Promise.resolve();
                        if (photoFile && form.dataset.clientId) {
                            var fd = new FormData();
                            fd.append('client_id', form.dataset.clientId);
                            fd.append('photo', photoFile);
                            photoPost = fetch('{{ route("clients.photo.store") }}', {
                                method: 'POST',
                                body: fd,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            }).then(function (r) {
                                if (!r.ok) throw new Error('photo_upload_failed');
                            });
                        }
                        photoPost.then(function () {
                            window.location.reload();
                        }).catch(function () {
                            showFeedback('Photo not saved', 'error', 'Client was saved, but the photo could not be uploaded. Please try again from Edit.');
                        });
                    } else if (data.errors) {
                        var list = document.createElement('ul');
                        list.className = 'list-disc list-inside mb-0 space-y-1';
                        Object.keys(data.errors).forEach(function (k) {
                            data.errors[k].forEach(function (msg) {
                                var li = document.createElement('li');
                                li.textContent = msg;
                                list.appendChild(li);
                            });
                        });
                        showFeedback('Validation errors', 'warning', list.outerHTML);
                    } else {
                        showFeedback('Error', 'error', data.message || 'Could not save client.');
                    }
                    if (submitBtn) submitBtn.disabled = false;
                })
                .catch(function () {
                    showFeedback('Error', 'error', 'A network error occurred. Please try again.');
                    if (submitBtn) submitBtn.disabled = false;
                });
            }, { once: true });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-edit-client-modal]');
            if (!btn) return;
            var id = btn.getAttribute('data-edit-client-modal');
            if (!id) return;
            e.preventDefault();
            window.openEditModal(id);
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-fp-edit-cancel]')) {
                Alpine.store('clientFormModal').hide();
            }
        });
    })();
</script>
