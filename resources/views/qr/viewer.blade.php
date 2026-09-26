<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scholar QR Code Viewer — 2D MIS</title>
    {{-- Batch G migration: standalone public head shares the built app
         stylesheet + ui.css (see auth/login). C3-E switches the QR data
         payload from persisted full_name to qr_token; the public search /
         grantee-search verify fetch contracts and human-facing display
         remain unchanged — the script below is byte-preserved apart from
         the payload wiring; JS-injected markup keeps its Bootstrap classes
         via the ui.css parity layer (Phase 27: the CDN stylesheet link is
         removed; §4.8–4.10 own form-label/accordion/list-group, utilities
         and Reboot/type parity). --}}
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: var(--color-bg);
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            padding: 24px;
        }

        .suggestions-list {
            position: absolute;
            z-index: 2000;
            width: 100%;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
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

        .suggestions-list button:hover {
            background: var(--color-surface-hover);
        }

        #qrContainer img {
            width: 220px;
            height: 220px;
            max-width: 100%;
        }

        .note-text {
            font-size: 0.9rem;
            color: var(--color-ink-secondary);
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="data-card mx-auto !p-[1.75rem] max-w-[600px]">
    <h1 class="mb-3 text-center text-lg font-semibold text-ink">Scholar QR Code Viewer</h1>

    <div class="mb-3">
        <label for="nameInput" class="field-label">Search your name</label>
        <div class="position-relative">
            <input id="nameInput" class="form-control uppercase" placeholder="Type your full name (e.g., DELA CRUZ, JUAN PEDRO)" autocomplete="off">
            <div id="suggestList" class="suggestions-list hidden"></div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="municipalitySelect" class="field-label">Municipality (for verification)</label>
            <select id="municipalitySelect" class="form-select" required>
                <option value="">-- Select Municipality --</option>
            </select>
        </div>
        <div class="col-md-6 flex align-items-end">
            <button id="verifyBtn" class="btn-navy w-full" disabled>Verify &amp; Load My QR Code</button>
        </div>
    </div>

    <div id="alertBox"></div>

    <div id="qrContainer" class="text-center hidden">
        <hr>
        <h2 id="qrName" class="mb-3 text-base font-semibold text-ink"></h2>
        <div id="qrImage"></div>

        <p class="note-text">Take a screenshot or download this QR code</p>

        <div class="mt-3 flex justify-content-center gap-2">
            <a id="downloadLink" class="btn-gold no-underline" download>Download QR Code</a>
            <button class="btn-subtle" id="resetBtn">Search Another</button>
        </div>
    </div>
</div>

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
            suggestList.classList.add('hidden');
            return;
        }
        debounce = setTimeout(() => {
            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.results.length) {
                        suggestList.innerHTML = '<div class="p-2">No matches</div>';
                        suggestList.classList.remove('hidden');
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
                            suggestList.classList.add('hidden');
                            document.getElementById('verifyBtn').disabled = false;
                        };
                        suggestList.appendChild(btn);
                    });
                    suggestList.classList.remove('hidden');
                })
                .catch(err => {
                    console.error(err);
                    suggestList.innerHTML = '<div class="p-2">Error searching</div>';
                    suggestList.classList.remove('hidden');
                });
        }, 220);
    });

    document.addEventListener('click', e => {
        if (!document.querySelector('.position-relative').contains(e.target)) {
            suggestList.classList.add('hidden');
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

                // C3-E: the QR encodes the client's opaque identity token.
                // The persisted full_name continues to be the human-facing
                // display name but is intentionally removed from the QR data
                // payload (scanner resolution was token-first since C3-D).
                const fullName = (data.client.full_name || '').trim();
                const qrPayload = (data.client.qr_token || '').trim();

                const encoded = encodeURIComponent(qrPayload);
                const size = '220x220';
                const qrURL = 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + '&data=' + encoded + '&format=png';

                document.getElementById('qrName').textContent = fullName;
                document.getElementById('qrImage').innerHTML = '<img src="' + qrURL + '" alt="QR Code" class="img-fluid">';
                const downloadLink = document.getElementById('downloadLink');
                downloadLink.href = qrURL;
                downloadLink.setAttribute('download', fullName.replace(/\s+/g, '_') + '.png');

                document.getElementById('qrContainer').classList.remove('hidden');
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
        document.getElementById('qrContainer').classList.add('hidden');
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
