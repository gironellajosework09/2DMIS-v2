<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile Photo — 2D MIS</title>
    {{-- Batch G + Phase 18: standalone public head shares the built app
         stylesheet + app.js (Alpine) + ui.css (see auth/login). The camera
         capture flow (getUserMedia/initCamera/switchCamera/capture/retake)
         and the photo-upload POST contract are unchanged — only the modal's
         Bootstrap lifecycle was replaced by the photoModal Alpine store.
         Phase 27: the Bootstrap CSS CDN link is removed; ui.css §4.8–4.10
         owns the shared families. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none; }
        body {
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: var(--color-bg);
        }
    </style>
</head>
<body x-data="photoModalComponent()">
    <div class="mx-auto mt-5 max-w-[560px] px-3">
        <div class="data-card !p-[1.75rem] text-center">
            @if (session('success'))
                <div class="mb-3 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-teal)] bg-[rgb(45_139_122_/_0.08)] p-[12px_16px] text-dense text-ink">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-3 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[12px_16px] text-dense text-ink">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h1 class="mb-3 text-lg font-semibold text-ink">Update Profile Photo</h1>

            @php($photo = $client->photos->first())
            <img id="previewImage"
                src="{{ $photo ? asset('storage/uploads/client_photos/'.$photo->photo_path) : asset('seal_logo.png') }}"
                class="rounded-panel shadow-card mb-3"
                style="width:180px;height:180px;object-fit:cover;">

            <button class="btn-navy mb-2 w-full"
                @click="$store.photoModal.openModal()">
                Take Photo
            </button>

            <a href="{{ route('student.update-photo') }}" class="text-dense text-ink-muted no-underline hover:text-navy">⬅ Search another name</a>
        </div>
    </div>

    {{-- Phase 18: #photoModal moved off Bootstrap JS. Presentation is
         Tailwind + Alpine (photoModal store: open/close/focus-restore/ESC/
         backdrop/scroll-lock/Tab-trap). Camera start (initCamera) and stop
         (stopCamera) logic is preserved verbatim in the page script below and
         is invoked from the Alpine open/close lifecycle exactly as the old
         shown.bs.modal / hidden.bs.modal handlers did. --}}
    <div id="photoModal"
         x-cloak
         x-show="$store.photoModal.open"
         role="dialog"
         aria-modal="true"
         aria-labelledby="photoModalTitle"
         class="pointer-events-none fixed inset-0 z-[200]">
        <div x-show="$store.photoModal.open"
             x-transition.opacity.duration.200ms
             @click="$store.photoModal.close()"
             class="pointer-events-auto absolute inset-0 bg-ink/40"
             aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div x-ref="dialog"
                 x-show="$store.photoModal.open"
                 x-transition.opacity.duration.200ms
                 @keydown.escape.window="$store.photoModal.close()"
                 @keydown.tab.prevent.stop="handleTab($event)"
                 class="pointer-events-auto w-full max-w-[500px] rounded-panel bg-surface p-[0.75rem] text-center shadow-pop ring-1 ring-line">
                <h2 id="photoModalTitle" class="mb-2 text-base font-semibold text-ink">Capture Photo</h2>

                <select id="cameraSelect" class="form-select mb-2" aria-label="Select camera"></select>
                <video id="video" autoplay class="w-full rounded-[var(--radius-control)] mb-2"></video>
                <img id="capturedPreview" class="w-full rounded-[var(--radius-control)] mb-2 hidden" alt="Captured photo preview">

                <div id="cameraButtons">
                    <button class="btn-navy" id="captureBtn">Capture</button>
                </div>
                <div id="previewButtons" class="hidden">
                    <button class="btn-subtle" id="retakeBtn">Retake</button>
                    <button class="btn-navy" id="saveBtn">Save</button>
                </div>

                <form method="POST" id="photoForm" action="{{ route('student.photo-upload.store') }}">
                    @csrf
                    <input type="hidden" name="camera_image" id="cameraImage">
                </form>
            </div>
        </div>
    </div>

    <script>
        let video = document.getElementById('video');
        let cameraSelect = document.getElementById('cameraSelect');
        let captureBtn = document.getElementById('captureBtn');
        let retakeBtn = document.getElementById('retakeBtn');
        let saveBtn = document.getElementById('saveBtn');
        let capturedPreview = document.getElementById('capturedPreview');
        let cameraButtons = document.getElementById('cameraButtons');
        let previewButtons = document.getElementById('previewButtons');
        let cameraImageInput = document.getElementById('cameraImage');
        let stream;

        async function initCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                await loadCameraDevices();
            } catch (err) {
                alert("Camera access denied or not available.");
            }
        }

        async function loadCameraDevices() {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            cameraSelect.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(option);
            });
        }

        async function switchCamera(deviceId) {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            stream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: { exact: deviceId } }
            });
            video.srcObject = stream;
        }

        cameraSelect.addEventListener('change', function () {
            switchCamera(this.value);
        });

        captureBtn.addEventListener('click', function () {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            capturedPreview.src = imageData;
            capturedPreview.classList.remove('hidden');
            video.classList.add('hidden');
            cameraButtons.classList.add('hidden');
            previewButtons.classList.remove('hidden');
            cameraImageInput.value = imageData;
        });

        retakeBtn.addEventListener('click', function () {
            capturedPreview.classList.add('hidden');
            video.classList.remove('hidden');
            cameraButtons.classList.remove('hidden');
            previewButtons.classList.add('hidden');
        });

        saveBtn.addEventListener('click', function () {
            document.getElementById('photoForm').submit();
        });

        // Phase 18: the old hidden.bs.modal handler stopped the stream; the
        // photoModal store's close() now calls this same logic (stop every
        // track, mirroring the old behavior exactly).
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        }
    </script>

    <script>
        // Phase 18: photoModal store + component — presentation only (open/
        // close state, backdrop, ESC, focus restore, body scroll lock, Tab
        // trap). Camera start/stop stay in the page script above: openModal()
        // waits for the modal to become visible (x-show applied) then calls
        // initCamera(), exactly like the old shown.bs.modal handler did;
        // close() stops the stream, exactly like the old hidden.bs.modal
        // handler did.
        document.addEventListener('alpine:init', function () {
            Alpine.store('photoModal', {
                open: false,
                _prevFocus: null,
                openModal: function () {
                    if (this.open) return;
                    this._prevFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    this.open = true;
                    Alpine.nextTick(function () {
                        var sel = document.getElementById('cameraSelect');
                        if (sel) sel.focus();
                        if (typeof initCamera === 'function') initCamera();
                    });
                },
                close: function () {
                    if (!this.open) return;
                    this.open = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                    if (typeof stopCamera === 'function') stopCamera();
                }
            });
        });

        window.photoModalComponent = function () {
            return {
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
    </script>
</body>
</html>
