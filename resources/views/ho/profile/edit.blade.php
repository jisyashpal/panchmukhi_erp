@extends('layouts.ho')
@section('title', 'Profile')
@section('content')
@php
    $profileUpdateRoute = route('ho.profile.update');
    $passwordUpdateRoute = route('ho.password.update');
    $destroyRoute = route('ho.profile.destroy');
@endphp

<x-admin-page-header title="My Profile" subtitle="Manage your account settings and security">
    <a href="{{ route('ho.dashboard') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
</x-admin-page-header>

<div class="admin-profile-summary admin-card mb-4">
    <div class="admin-card-body d-flex flex-wrap align-items-center gap-3">
        <span class="admin-profile-avatar">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </span>
        <div class="flex-grow-1 min-w-0">
            <h2 class="h5 mb-1 text-truncate">{{ $user->name }}</h2>
            <p class="text-muted small mb-1 text-truncate">{{ $user->email }}</p>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">HO Data Operator</span>
        </div>
        <div class="text-muted small">
            <div><i class="bi bi-shield-check me-1"></i>Head Office</div>
            @if($user->email_verified_at)
                <div class="text-success mt-1"><i class="bi bi-patch-check me-1"></i>Email verified</div>
            @else
                <div class="text-warning mt-1"><i class="bi bi-exclamation-circle me-1"></i>Email not verified</div>
            @endif
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2><i class="bi bi-person-vcard me-2 text-primary"></i>Profile Information</h2>
    </div>
    <div class="admin-card-body">
        @include('profile.partials.update-profile-information-form', compact('profileUpdateRoute'))
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2><i class="bi bi-key me-2 text-primary"></i>Update Password</h2>
    </div>
    <div class="admin-card-body">
        @include('profile.partials.update-password-form', compact('passwordUpdateRoute'))
    </div>
</div>

<div class="admin-card border-danger-subtle">
    <div class="admin-card-header">
        <h2 class="text-danger mb-0"><i class="bi bi-trash me-2"></i>Delete Account</h2>
    </div>
    <div class="admin-card-body">
        @include('profile.partials.delete-user-form', compact('destroyRoute'))
    </div>
</div>
@endsection
