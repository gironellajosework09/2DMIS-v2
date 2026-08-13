<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scholar QR Code Viewer — 2D MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f7f9fb;
            font-family: system-ui, Segoe UI, Roboto, Helvetica, Arial;
            padding: 24px;
        }

        .card {
            max-width: 600px;
            margin: 0 auto;
        }

        .suggestions-list {
            position: absolute;
            z-index: 2000;
            width: 100%;
            background: #fff;
            border: 1px solid #ccc;
            max-height: 220px;
            overflow: auto;
        }

        .suggestions-list button {
            width: 100%;
            border: none;
            background: none;
            padding: 8px 12px;
            text-align: left;
        }

        .uppercase {
            text-transform: uppercase;
        }

        #qrContainer img {
            width: 220px;
            height: 220px;
            max-width: 100%;
        }

        .note-text {
            font-size: 0.9rem;
            color: #555;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="card shadow-sm p-4">
    <h3 class="mb-3 text-center">Scholar QR Code Viewer</h3>

    <div class="mb-3">
        <label class="form-label">Search your name</label>
        <div class="position-relative">
            <input id="nameInput" class="form-control uppercase" placeholder="Type your full name (e.g., DELA CRUZ, JUAN PEDRO)" autocomplete="off">
            <div id="suggestList" class="suggestions-list d-none"></div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label class="form-label">Municipality (for verification)</label>
            <select id="municipalitySelect" class="form-select" required>
                <option value="">-- Select Municipality --</option>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button id="verifyBtn" class="btn btn-primary w-100" disabled>Verify &amp; Load My QR Code</button>
        </div>
    </div>

    <div id="alertBox"></div>

    <div id="qrContainer" class="text-center d-none">
        <hr>
        <h5 id="qrName" class="mb-3"></h5>
        <div id="qrImage"></div>

        <p class="note-text">Take a screenshot or download this QR code</p>

        <div class="mt-3 d-flex justify-content-center gap-2">
            <a id="downloadLink" class="btn btn-success" download>Download QR Code</a>
            <button class="btn btn-secondary" id="resetBtn">Search Another</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const searchUrl = '{{ route('grantee-search', ['kind' => 'grantee']) }}';
    const verifyUrl = '{{ route('grantee-search.verify', ['kind' => 'grantee']) }}';

    let selectedClientId = null;

    fetch(searchUrl + '?munis=1').then(r => r.json()).then(data => {
        if (data.success) {
            const sel = document.getElementById('municipalitySelect');
            data.municipalities.forEach(m => {
                const o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name;
                sel.appendChild(o);
            });
        } else {
            console.warn('Could not load municipalities', data);
        }
    });

    const nameInput = document.getElementById('nameInput');
    const suggestList = document.getElementById('suggestList');
    let debounce = null;

    nameInput.addEventListener('input', () => {
        const q = nameInput.value.trim();
        if (debounce) clearTimeout(debounce);
        if (!q) {
            suggestList.classList.add('d-none');
            return;
        }
        debounce = setTimeout(() => {
            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.results.length) {
                        suggestList.innerHTML = '<div class="p-2">No matches</div>';
                        suggestList.classList.remove('d-none');
                        return;
                    }
                    suggestList.innerHTML = '';
                    data.results.forEach(r => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'text-start';
                        btn.innerHTML = '<strong>' + r.full_name.toUpperCase() + '</strong>';
                        btn.onclick = () => {
                            nameInput.value = r.full_name.toUpperCase();
                            selectedClientId = r.id;
                            suggestList.classList.add('d-none');
                            document.getElementById('verifyBtn').disabled = false;
                        };
                        suggestList.appendChild(btn);
                    });
                    suggestList.classList.remove('d-none');
                })
                .catch(err => {
                    console.error(err);
                    suggestList.innerHTML = '<div class="p-2">Error searching</div>';
                    suggestList.classList.remove('d-none');
                });
        }, 220);
    });

    document.addEventListener('click', e => {
        if (!document.querySelector('.position-relative').contains(e.target)) {
            suggestList.classList.add('d-none');
        }
    });

    document.getElementById('verifyBtn').addEventListener('click', () => {
        const muni = document.getElementById('municipalitySelect').value;
        if (!selectedClientId || !muni) {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-danger">Please select your name and municipality first.</div>';
            return;
        }
        document.getElementById('alertBox').innerHTML = '';

        fetch(verifyUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'verify',
                client_id: selectedClientId,
                municipality_id: muni
            })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('alertBox').innerHTML = '<div class="alert alert-danger">' + (data.message || 'Verification failed') + '</div>';
                    return;
                }

                // Decision C: the QR must encode the persisted comma-form
                // full_name (what the P4 scanners match against), never a
                // re-composed name.
                const fullName = (data.client.full_name || '').trim();

                const encoded = encodeURIComponent(fullName);
                const size = '220x220';
                const qrURL = 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + '&data=' + encoded + '&format=png';

                document.getElementById('qrName').textContent = fullName;
                document.getElementById('qrImage').innerHTML = '<img src="' + qrURL + '" alt="QR Code" class="img-fluid">';
                const downloadLink = document.getElementById('downloadLink');
                downloadLink.href = qrURL;
                downloadLink.setAttribute('download', fullName.replace(/\s+/g, '_') + '.png');

                document.getElementById('qrContainer').classList.remove('d-none');
                document.getElementById('verifyBtn').disabled = true;
                document.getElementById('nameInput').disabled = true;
                document.getElementById('municipalitySelect').disabled = true;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('alertBox').innerHTML = '<div class="alert alert-danger">Server error during verification.</div>';
            });
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
        document.getElementById('qrContainer').classList.add('d-none');
        document.getElementById('nameInput').disabled = false;
        document.getElementById('municipalitySelect').disabled = false;
        document.getElementById('verifyBtn').disabled = true;
        document.getElementById('nameInput').value = '';
        document.getElementById('municipalitySelect').value = '';
        selectedClientId = null;
        document.getElementById('alertBox').innerHTML = '';
    });
</script>
</body>
</html>
