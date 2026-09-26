@extends('layouts.app')

@section('title', 'Client Profile — 2D MIS')

@section('content')
    {{-- Full-page mode only; the ?panel=1 variant renders this same
         partial inside the index offcanvas with no wrapper chrome. --}}

    @include('clients._details', ['panel' => false, 'gip' => $gip, 'hasGipTransaction' => $hasGipTransaction])

    {{-- Full-page delete goes through the shared uiConfirm dialog (Batch F),
         matching the other modules' show pages. --}}
    @include('partials.confirm-modal')
@endsection

@push('scripts')
    <script>
        // Toast feedback for actions that land on the full-page profile via a
        // server redirect (Add Family Member, GIP save, Change Photo all flash
        // session('success')), plus the full-page Editor whose success used to
        // be dropped by window.location.reload(). Both render through the ONE
        // shared notification stack (window.notify / partials.unified-notify) —
        // same channel the layout uses for the login_status flash. Kept on this
        // page only: the ?panel=1 variant renders clients._details directly and
        // never includes this script, so no duplicate toasts can occur.
        (function () {
            var pending = [];
            @if (session('success'))
                pending.push({ type: 'success', title: 'Success', message: {{ Js::from(session('success')) }} });
            @endif
            var stash = null;
            try { stash = sessionStorage.getItem('2dmis_client_flash'); } catch (e) {}
            if (stash) {
                try { sessionStorage.removeItem('2dmis_client_flash'); } catch (e) {}
                try { stash = JSON.parse(stash); } catch (e) { stash = null; }
                if (stash && stash.message) {
                    pending.push({ type: 'success', title: 'Success', message: stash.message });
                }
            }
            if (!pending.length) return;
            function pushOnReady() {
                if (typeof window.notify === 'function' && document.body) {
                    while (pending.length) window.notify(pending.shift());
                } else {
                    setTimeout(pushOnReady, 60);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', pushOnReady);
            } else {
                pushOnReady();
            }
        })();
    </script>
@endpush
