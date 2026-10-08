@extends('layout')
@section('title','Edit customer')
@section('subtitle','Update customer details. Existing orders keep their saved information.')
@section('action')<a class="btn secondary" href="/customers">← Back to Customers</a>@endsection
@section('content')
<form method="post" action="/customers/{{ $customer->id }}" class="card">@csrf
    <h2>Customer details</h2>
    @include('customer-fields')
    <div class="inline section-gap"><button>Save changes</button><a class="btn secondary" href="/customers">Cancel</a></div>
</form>
@endsection
