<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container-fluid px-3">
        <button class="btn btn-dark me-2" id="sidebarToggle" type="button" title="Toggle Sidebar">☰</button>

        <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
            <img src="{{ asset('seal_logo.png') }}" alt="Logo" style="height:40px; margin-right:10px;">
            <span class="fw-bold">2D MIS</span>
        </a>

        <div class="ms-auto">
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle text-white d-flex align-items-center" type="button"
                        id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span>Welcome, {{ auth()->user()->username }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
