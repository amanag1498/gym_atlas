@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">All Gym Billing</x-action-button>
        <x-action-button as="a" href="{{ route('web.admin.platform-subscription-plans.index') }}">Platform Plans</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $subscription) }}">Billing Ledger</x-action-button>
        <form method="POST" action="{{ route('web.admin.gym-platform-subscriptions.renew', $subscription) }}">
            @csrf
            <x-action-button type="submit">Renew Now</x-action-button>
        </form>
    @endsection

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Invoices" :value="$invoiceSummary['invoice_count']" hint="Tracked platform bills" tone="sky" />
            <x-stat-card label="Invoiced" :value="'₹'.number_format($invoiceSummary['total_invoiced'], 0)" hint="Lifetime platform billing" tone="violet" />
            <x-stat-card label="Collected" :value="'₹'.number_format($invoiceSummary['paid_revenue'], 0)" hint="Paid platform revenue" tone="emerald" />
            <x-stat-card label="Open Balance" :value="'₹'.number_format($invoiceSummary['open_balance'], 0)" hint="Due or overdue exposure" tone="amber" />
        </div>

        <form method="POST" action="{{ route('web.admin.gym-platform-subscriptions.update', $subscription) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('web.admin.gym-platform-subscriptions._form', ['submitLabel' => 'Save Subscription'])
        </form>
    </div>
@endsection
