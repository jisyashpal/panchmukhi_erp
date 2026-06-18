@foreach($products as $p)
<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
@endforeach
