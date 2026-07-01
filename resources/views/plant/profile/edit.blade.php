@extends('layouts.site')
@section('title', 'Profile')
@section('content')
@php
    $profileUpdateRoute = route('site.profile.update');
    $passwordUpdateRoute = route('site.password.update');
    $destroyRoute = route('site.profile.destroy');
@endphp

<x-admin-page-header title="My Profile" subtitle="Manage your account">
    <a href="{{ route('site.dashboard') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
</x-admin-page-header>

<div class="admin-card mb-4">
    <div class="admin-card-body d-flex flex-wrap align-items-center gap-3">
        <span class="admin-profile-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.25rem">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </span>
        <div>
            <h2 class="h5 mb-1">{{ $user->name }}</h2>
            <p class="text-muted small mb-1">{{ $user->email }}</p>
            <span class="badge bg-success-subtle text-success">{{ $user->role?->name }}</span>
            @if($user->site)
                <span class="badge bg-light text-dark border ms-1">{{ $user->site->name }}</span>
            @endif
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header"><h2 class="h6 mb-0">Profile Information</h2></div>
    <div class="admin-card-body">
        @include('profile.partials.update-profile-information-form', compact('profileUpdateRoute'))
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header"><h2 class="h6 mb-0">Update Password</h2></div>
    <div class="admin-card-body">
        @include('profile.partials.update-password-form', compact('passwordUpdateRoute'))
    </div>
</div>

<div class="admin-card border-danger-subtle">
    <div class="admin-card-header"><h2 class="h6 mb-0 text-danger">Delete Account</h2></div>
    <div class="admin-card-body">
        @include('profile.partials.delete-user-form', compact('destroyRoute'))
    </div>
</div>
@endsection
