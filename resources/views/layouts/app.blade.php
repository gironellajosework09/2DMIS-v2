<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', '2D MIS')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f8f9fa;
            margin: 0;
        }
        .navbar {
            z-index: 1030;
        }
        .sidebar {
            height: 100vh;
            width: 220px;
            position: fixed;
            top: 56px;
            left: 0;
            background: #212529;
            padding-top: 10px;
            overflow-y: auto;
            z-index: 1020;
            transition: margin-left 0.3s ease;
        }
        .sidebar.collapsed {
            margin-left: -220px;
        }
        .sidebar a {
            display: block;
            color: #adb5bd;
            padding: 12px 20px;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .sidebar a:hover,
        .sidebar a.active {
            color: #fff;
            background: #343a40;
        }
        .content {
            margin-left: 220px;
            padding: 76px 24px 24px;
            transition: margin-left 0.3s ease;
        }
        .content.shifted {
            margin-left: 0;
        }
        .card {
            border-radius: 1rem;
        }
    </style>
    @stack('styles')
</head>
<body>

@include('partials.navbar')
@include('partials.sidebar')

<div class="content" id="content">
    @if (session('login_status'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('login_status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        content.classList.toggle('shifted');
    });

    async function checkSession() {
        try {
            const response = await fetch('{{ route('session.status') }}', { cache: 'no-store' });
            const data = await response.json();

            if (data.status === 'another_device') {
                alert('Your account was logged out by the system.');
                window.location.href = '{{ route('login') }}';
            } else if (data.status === 'logged_out') {
                window.location.href = '{{ route('login') }}';
            }
        } catch (error) {
            console.log(error);
        }
    }

    setInterval(checkSession, 2000);
</script>
@stack('scripts')
</body>
</html>
