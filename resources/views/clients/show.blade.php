@extends('layouts.app')

@section('title', 'Client Profile — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Client Profile</h3>
            <div>
                <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">Back</a>
                @if (app(\App\Services\AccessControlService::class)->canAccessPage(auth()->user(), 'all_transactions.php'))
                    <a href="{{ route('transactions.create', $client) }}" class="btn btn-success btn-sm">+ Add Transaction</a>
                @endif
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#photoModal">Photo</button>
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary btn-sm">Edit</a>
                <form method="POST" action="{{ route('clients.destroy', $client) }}" class="d-inline"
                    onsubmit="return confirm('Are you sure you want to delete this client?');">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 text-center mb-3">
                @php($photo = $client->photos->first())
                @if ($photo)
                    <img src="{{ asset('storage/uploads/client_photos/'.$photo->photo_path) }}" alt="Client photo"
                        class="img-fluid rounded border" style="max-width:180px;">
                @else
                    <div class="border rounded d-flex align-items-center justify-content-center text-muted"
                        style="width:180px;height:200px;margin:0 auto;">No photo</div>
                @endif
                <div class="mt-2"><strong>{{ $client->full_name }}</strong></div>
            </div>

            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Lastname</label>
                        <div>{{ $client->lastname }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Firstname</label>
                        <div>{{ $client->firstname }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Middlename</label>
                        <div>{{ $client->middlename }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Extension</label>
                        <div>{{ $client->extensionname }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Birthdate</label>
                        <div>{{ $client->birthdate }} ({{ $client->age }})</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Category</label>
                        <div>{{ $client->category }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Sex</label>
                        <div>{{ $client->sex }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Civil Status</label>
                        <div>{{ $client->civil_status }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">PWD / IP</label>
                        <div>{{ $client->pwd }} / {{ $client->ip }} {{ $client->ip_group ? '('.$client->ip_group.')' : '' }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Municipality</label>
                        <div>{{ $client->municipality->name ?? '' }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Barangay</label>
                        <div>{{ $client->barangayInfo->name ?? '' }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">House No.</label>
                        <div>{{ $client->house_no }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Mobile No.</label>
                        <div>{{ $client->mobile_no }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Email</label>
                        <div>{{ $client->email }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Occupation</label>
                        <div>{{ $client->occupation }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Monthly Income</label>
                        <div>{{ $client->monthly_income }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Precinct No.</label>
                        <div>{{ $client->precinct_no }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Voter's ID</label>
                        <div>{{ $client->voter_id }}</div></div>
                    <div class="col-md-4 mb-2"><label class="form-label mb-0 text-muted">Affiliated Organizations</label>
                        <div>{{ $client->affOrgs->pluck('organization')->join(', ') }}</div></div>
                </div>
            </div>
        </div>

        @if ($client->household)
            <hr class="my-4">
            <h5 class="mb-3">Household</h5>
            <table class="table table-sm table-bordered" style="max-width:600px;">
                <tr>
                    <th>Household ID</th>
                    <td><a href="{{ route('households.show', $client->household) }}">{{ $client->household->household_id }}</a></td>
                </tr>
                <tr>
                    <th>Head of Household</th>
                    <td>{{ $client->household->headClient->full_name ?? '' }}</td>
                </tr>
            </table>
        @endif

        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Family Members</h5>
            <a href="{{ route('family-members.create', $client) }}" class="btn btn-primary btn-sm">+ Add Family Member</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered">
                <thead class="table-dark">
                    <tr><th>Name</th><th>Relationship</th></tr>
                </thead>
                <tbody>
                    @forelse ($client->familyMembers as $member)
                        <tr>
                            <td>{{ $member->relative->full_name ?? '—' }}</td>
                            <td>{{ $member->relationship }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted">No family members linked.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Transactions</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Program</th><th>Date Applied</th><th>Status</th><th>Amount</th><th>Payout Date</th></tr>
                </thead>
                <tbody>
                    @forelse ($client->transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->id }}</td>
                            <td>{{ $transaction->program }}</td>
                            <td>{{ $transaction->date_applied }}</td>
                            <td>{{ $transaction->status }}</td>
                            <td>{{ $transaction->amount_paid }}</td>
                            <td>{{ $transaction->payout_date }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No transactions recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('clients.photo.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Client Profile Photo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="camera_image" id="cameraImage">
                        <video id="video" width="100%" autoplay class="d-none"></video>
                        <canvas id="canvas" class="d-none"></canvas>
                        <img id="capturedPreview" class="w-100 rounded mb-2 d-none">
                        <input type="file" name="photo" id="photoFile" class="form-control mb-2" accept="image/*">
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary" id="startCameraBtn">Use Camera</button>
                            <button type="button" class="btn btn-outline-success d-none" id="captureBtn">Capture</button>
                            <button type="button" class="btn btn-secondary d-none" id="retakeBtn">Retake</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary">Save Photo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const modal = document.getElementById('photoModal');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturedPreview = document.getElementById('capturedPreview');
        const startBtn = document.getElementById('startCameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const cameraImage = document.getElementById('cameraImage');
        const photoFile = document.getElementById('photoFile');
        let stream;

        modal.addEventListener('shown.bs.modal', function () {
            photoFile.classList.remove('d-none');
            capturedPreview.classList.add('d-none');
        });

        modal.addEventListener('hidden.bs.modal', function () {
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
            }
            video.classList.add('d-none');
            startBtn.classList.remove('d-none');
            captureBtn.classList.add('d-none');
            retakeBtn.classList.add('d-none');
        });

        startBtn.addEventListener('click', async function () {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                video.classList.remove('d-none');
                startBtn.classList.add('d-none');
                captureBtn.classList.remove('d-none');
            } catch (err) {
                alert('Camera access denied or not available.');
            }
        });

        captureBtn.addEventListener('click', function () {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            cameraImage.value = imageData;
            capturedPreview.src = imageData;
            capturedPreview.classList.remove('d-none');
            video.classList.add('d-none');
            captureBtn.classList.add('d-none');
            retakeBtn.classList.remove('d-none');
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
            }
        });

        retakeBtn.addEventListener('click', function () {
            capturedPreview.classList.add('d-none');
            cameraImage.value = '';
            video.classList.remove('d-none');
            retakeBtn.classList.add('d-none');
            captureBtn.classList.remove('d-none');
        });
    </script>
@endpush
