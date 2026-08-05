<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile Photo — 2D MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow p-4 text-center">
            @if (session('success'))
                <div class="alert alert-success text-center">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger text-center">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h4 class="mb-3">Update Profile Photo</h4>

            @php($photo = $client->photos->first())
            <img id="previewImage"
                src="{{ $photo ? asset('storage/uploads/client_photos/'.$photo->photo_path) : asset('seal_logo.png') }}"
                class="rounded shadow mb-3"
                style="width:180px;height:180px;object-fit:cover;">

            <button class="btn btn-primary w-100 mb-2"
                data-bs-toggle="modal"
                data-bs-target="#photoModal">
                Take Photo
            </button>

            <a href="{{ route('student.update-photo') }}" class="btn btn-link">⬅ Search another name</a>
        </div>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 text-center">
                <h5>Capture Photo</h5>

                <select id="cameraSelect" class="form-select mb-2"></select>
                <video id="video" autoplay class="w-100 rounded mb-2"></video>
                <img id="capturedPreview" class="w-100 rounded mb-2 d-none">

                <div id="cameraButtons">
                    <button class="btn btn-success" id="captureBtn">Capture</button>
                </div>
                <div id="previewButtons" class="d-none">
                    <button class="btn btn-secondary" id="retakeBtn">Retake</button>
                    <button class="btn btn-primary" id="saveBtn">Save</button>
                </div>

                <form method="POST" id="photoForm" action="{{ route('student.photo-upload.store') }}">
                    @csrf
                    <input type="hidden" name="camera_image" id="cameraImage">
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
            capturedPreview.classList.remove('d-none');
            video.classList.add('d-none');
            cameraButtons.classList.add('d-none');
            previewButtons.classList.remove('d-none');
            cameraImageInput.value = imageData;
        });

        retakeBtn.addEventListener('click', function () {
            capturedPreview.classList.add('d-none');
            video.classList.remove('d-none');
            cameraButtons.classList.remove('d-none');
            previewButtons.classList.add('d-none');
        });

        saveBtn.addEventListener('click', function () {
            document.getElementById('photoForm').submit();
        });

        document.getElementById('photoModal').addEventListener('shown.bs.modal', initCamera);
        document.getElementById('photoModal').addEventListener('hidden.bs.modal', function () {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>
