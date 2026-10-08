@extends('layout')
@section('title','Add customer')
@section('subtitle','Save customer details and choose their default pricing category.')
@section('action')<a class="btn secondary" href="/customers">← Back to Customers</a>@endsection
@section('content')
<form method="post" action="/customers" class="card">@csrf
    <h2>Customer details</h2>
    @include('customer-fields')
    <div class="inline section-gap"><button>Add customer</button><a class="btn secondary" href="/customers">Cancel</a></div>
</form>
@endsection
