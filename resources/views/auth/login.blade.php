<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>Login — 2D MIS</title>
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
        .logo {
            display: block;
            margin: 0 auto 1rem auto;
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
    </style>
</head>
<body>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
        <div class="card shadow-lg border-0 p-4">
            <div class="text-center">
                <img src="{{ asset('seal_logo.png') }}" alt="Logo" class="logo">
                <h3>2D MIS</h3>
                <p>Welcome</p>
            </div>

            @if (session('login_status') === 'expired')
                <div class="alert alert-warning text-center">Session expired. Please login again.</div>
            @endif

            @if (session('login_status') === 'forced')
                <div class="alert alert-warning text-center">You have been logged out by the system.</div>
            @endif

            @if ($errors->has('username'))
                <div class="alert alert-danger text-center">{{ $errors->first('username') }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
