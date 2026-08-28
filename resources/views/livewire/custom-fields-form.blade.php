<div class="space-y-4">
    @foreach ($this->definitions as $field)
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ $field->label }}</label>
            @if ($field->help_text)
                <p class="text-xs text-gray-500 mb-1">{{ $field->help_text }}</p>
            @endif

            @switch($field->field_type->value)
                @case('textarea')
                    <textarea wire:model="metadata.{{ $field->key }}" rows="3"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
                    @break
                @case('number')
                    <input type="number" wire:model="metadata.{{ $field->key }}"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
                    @break
                @case('date')
                    <input type="date" wire:model="metadata.{{ $field->key }}"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
                    @break
                @case('select')
                    <select wire:model="metadata.{{ $field->key }}"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        <option value="">Seleccionar...</option>
                        @foreach ($field->options['values'] ?? [] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @break
                @case('checkbox')
                    <input type="checkbox" wire:model="metadata.{{ $field->key }}"
                        class="mt-1 rounded border-gray-300">
                    @break
                @default
                    <input type="text" wire:model="metadata.{{ $field->key }}"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
            @endswitch

            @error('metadata.' . $field->key)
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
