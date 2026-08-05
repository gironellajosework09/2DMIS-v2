@extends('layouts.app')

@section('title', 'View Transaction — 2D MIS')

@section('content')
    @php($fmt = fn ($d) => in_array((string) $d, ['', '0000-00-00', '0000-00-00 00:00:00', null], true) ? '' : date('m/d/Y', strtotime($d)))

    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3 text-center">Transaction Details</h3>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <dl class="row">
            <dt class="col-sm-3">Client</dt>
            <dd class="col-sm-9">
                @if ($transaction->client)
                    <a href="{{ route('clients.show', $transaction->client) }}">{{ $transaction->client->full_name }}</a>
                @else
                    —
                @endif
            </dd>

            <dt class="col-sm-3">Program</dt>
            <dd class="col-sm-9">{{ $transaction->program }}</dd>

            <dt class="col-sm-3">Assistance Type</dt>
            <dd class="col-sm-9">{{ $transaction->type }}</dd>

            <dt class="col-sm-3">Patient</dt>
            <dd class="col-sm-9">{{ $transaction->patient_name }}</dd>

            <dt class="col-sm-3">Date Applied</dt>
            <dd class="col-sm-9">{{ $fmt($transaction->date_applied) }}</dd>

            <dt class="col-sm-3">Remarks</dt>
            <dd class="col-sm-9">{{ $transaction->remarks }}</dd>

            <dt class="col-sm-3">Comments</dt>
            <dd class="col-sm-9">{{ $transaction->comments }}</dd>

            <dt class="col-sm-3">Suggested Amount</dt>
            <dd class="col-sm-9">₱{{ number_format($transaction->suggested_amount ?? 0, 2) }}</dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">{{ $transaction->status }}</dd>

            <dt class="col-sm-3">Amount Paid</dt>
            <dd class="col-sm-9">₱{{ number_format($transaction->amount_paid ?? 0, 2) }}</dd>

            <dt class="col-sm-3">Pay Out Date</dt>
            <dd class="col-sm-9">{{ $fmt($transaction->payout_date) }}</dd>

            <dt class="col-sm-3">Date Paid</dt>
            <dd class="col-sm-9">{{ $fmt($transaction->date_paid) }}</dd>

            <dt class="col-sm-3">GWA</dt>
            <dd class="col-sm-9">{{ $transaction->gwa }}</dd>

            <dt class="col-sm-3">Units</dt>
            <dd class="col-sm-9">{{ $transaction->units }}</dd>
        </dl>

        <div class="d-flex justify-content-between mt-3">
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Back to All Transactions</a>
            <div class="d-flex gap-2">
                <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-warning">Edit</a>
                <form method="POST" action="{{ route('transactions.destroy', $transaction->id) }}"
                    onsubmit="return confirm('Are you sure you want to delete this transaction?')">
                    @csrf
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
@endsection
