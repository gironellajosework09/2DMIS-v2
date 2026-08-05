@extends('layouts.app')

@section('title', 'View Household — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Household Details</h3>
            <a href="{{ route('households.index') }}" class="btn btn-secondary btn-sm">Back</a>
        </div>

        <div class="section-title fw-semibold mb-3">Household Information</div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Household ID</label>
                <input type="text" class="form-control" readonly value="{{ $household->household_id }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Head of Household</label>
                <input type="text" class="form-control" readonly value="{{ $household->headClient->full_name ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Municipality</label>
                <input type="text" class="form-control" readonly value="{{ $household->headClient->municipality->name ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label mb-1">Barangay</label>
                <input type="text" class="form-control" readonly value="{{ $household->headClient->barangayInfo->name ?? '' }}">
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="section-title fw-semibold mb-0">Household Members</div>
            <span class="badge bg-primary rounded-pill">{{ count($members) }} Member{{ count($members) != 1 ? 's' : '' }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover align-middle" style="font-size:.85rem;">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th width="80">Age</th>
                        <th width="120">Sex</th>
                        <th width="90">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>
                                <strong>{{ $member->full_name }}</strong>
                                @if ($member->id == $household->head_household)
                                    <span class="badge bg-success ms-2">Head of Household</span>
                                @endif
                            </td>
                            <td>{{ $member->age }}</td>
                            <td>{{ $member->sex }}</td>
                            <td><a href="{{ route('clients.show', $member->id) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No household members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
