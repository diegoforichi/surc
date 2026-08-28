@props(['path', 'alt' => '', 'class' => 'h-40 w-full object-cover'])

@if ($path)
    <img src="{{ asset('storage/'.$path) }}" alt="{{ $alt }}" class="{{ $class }}">
@endif
