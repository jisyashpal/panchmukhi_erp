@props(['colspan' => 1, 'icon' => 'bi-inbox', 'message' => 'No records found'])

<tr>
    <td colspan="{{ $colspan }}">
        <div class="admin-empty text-center py-4 text-muted">
            <i class="bi {{ $icon }} d-block fs-3 mb-2"></i>
            {{ $message }}
        </div>
    </td>
</tr>
