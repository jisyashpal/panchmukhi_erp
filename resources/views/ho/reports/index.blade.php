@extends('layouts.ho')
@section('title', 'Reports')
@section('content')
<x-admin-page-header title="Reports" subtitle="Analytics and summaries" />
<div class="row g-3">
    <div class="col-md-6"><a href="{{ route('ho.reports.product-life') }}" class="admin-card admin-card-body text-decoration-none d-block"><h3 class="h6">Product Life Usage</h3><p class="small text-muted mb-0">Vehicle-wise changes with meter readings and life used</p></a></div>
    <div class="col-md-6"><a href="{{ route('ho.reports.category-purchase') }}" class="admin-card admin-card-body text-decoration-none d-block"><h3 class="h6">Category Purchase Summary</h3><p class="small text-muted mb-0">Purchase amount by category</p></a></div>
    <div class="col-md-6"><a href="{{ route('ho.reports.multiple-usage') }}" class="admin-card admin-card-body text-decoration-none d-block"><h3 class="h6">Multiple Usage Highlights</h3><p class="small text-muted mb-0">Products used more than once</p></a></div>
    <div class="col-md-6"><a href="{{ route('ho.reports.usage-by-category') }}" class="admin-card admin-card-body text-decoration-none d-block"><h3 class="h6">Usage by Category</h3><p class="small text-muted mb-0">Consumption grouped by category</p></a></div>
    <div class="col-md-6"><a href="{{ route('ho.reports.party-purchase') }}" class="admin-card admin-card-body text-decoration-none d-block"><h3 class="h6">Party-wise Purchase Summary</h3><p class="small text-muted mb-0">Purchase amount and order count by supplier</p></a></div>
</div>
@endsection
