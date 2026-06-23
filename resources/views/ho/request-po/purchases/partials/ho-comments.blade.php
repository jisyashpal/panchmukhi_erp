<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h2 class="mb-0">HO comments</h2>
        <span class="badge bg-secondary">{{ $purchase->hoComments->count() }}</span>
    </div>
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.purchases.comments.store', $purchase) }}" class="mb-3">
            @csrf
            <label class="form-label small mb-1" for="ho_comment_body">Add comment</label>
            <textarea name="body" id="ho_comment_body" rows="3" class="form-control form-control-sm @error('body') is-invalid @enderror"
                placeholder="Internal HO note for this purchase order..." maxlength="2000" required>{{ old('body') }}</textarea>
            @error('body')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-admin-primary btn-sm mt-2">
                <i class="bi bi-chat-left-text me-1"></i>Post comment
            </button>
        </form>

        @if($purchase->hoComments->isEmpty())
            <p class="small text-muted mb-0">No HO comments yet.</p>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach($purchase->hoComments as $comment)
                    <div class="border rounded p-2 small">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <strong>{{ $comment->user?->name ?? 'HO User' }}</strong>
                            <span class="text-muted">{{ $comment->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="text-body">{{ $comment->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
