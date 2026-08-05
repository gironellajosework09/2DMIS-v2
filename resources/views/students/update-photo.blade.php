<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile Photo Update — 2D MIS</title>
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
        <div class="card shadow p-4">
            <h4 class="text-center mb-3">Search Your Name</h4>

            <form method="GET" class="input-group mb-3">
                <input type="text" name="search" class="form-control"
                    placeholder="Enter your name..." value="{{ $search }}" required>
                <button class="btn btn-primary">Search</button>
            </form>

            @if (count($results) > 0)
                <ul class="list-group">
                    @foreach ($results as $row)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ $row->lastname }}, {{ $row->firstname }} {{ $row->middlename }}
                            <a href="{{ route('student.verify', $row->id) }}" class="btn btn-sm btn-success">Select</a>
                        </li>
                    @endforeach
                </ul>
            @elseif ($search !== '')
                <div class="alert alert-warning text-center">No record found.</div>
            @endif
        </div>
    </div>
</body>
</html>
