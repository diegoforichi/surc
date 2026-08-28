<h1>{{ $title }}</h1>
<p><strong>{{ terminology('organization', 'Sede') }}:</strong> {{ $subject->organization?->name ?? '—' }}</p>
<p><strong>{{ terminology('subject', 'Sujeto') }}:</strong> {{ $subject->label_name }}{{ $subject->code ? ' ('.$subject->code.')' : '' }}</p>
<p><strong>{{ terminology('client', 'Titular') }}:</strong> {{ $subject->owner?->display_name ?? '—' }}</p>
