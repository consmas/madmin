@foreach ($definition['fields'] as $name => $field)
    @php
        $rawValue = old($name, isset($record) ? $record->{$name} : $field['default']);
        if ($name === 'uuid' && blank($rawValue)) {
            $rawValue = (string) \Illuminate\Support\Str::uuid();
        }
        $value = $field['type'] === 'json' && filled($rawValue) && ! is_string($rawValue)
            ? json_encode($rawValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $rawValue;
    @endphp
    <div class="{{ in_array($field['type'], ['textarea', 'json']) ? 'col-md-12' : 'col-md-6' }}">
        <div class="form-group">
            @if ($field['type'] === 'boolean')
                <div class="custom-control custom-checkbox mt-4">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" class="custom-control-input" name="{{ $name }}" value="1"
                        id="{{ $formId }}-{{ $name }}" @checked((bool) $rawValue)>
                    <label class="custom-control-label" for="{{ $formId }}-{{ $name }}">{{ $field['label'] }}</label>
                </div>
            @else
                <label class="input-label" for="{{ $formId }}-{{ $name }}">{{ $field['label'] }}</label>
                @if ($field['type'] === 'select')
                    <select class="form-control" name="{{ $name }}" id="{{ $formId }}-{{ $name }}">
                        @foreach ($field['options'] as $option)
                            <option value="{{ $option }}" @selected((string) $value === (string) $option)>
                                {{ $option === '' ? 'Not set' : ucfirst(str_replace('_', ' ', $option)) }}
                            </option>
                        @endforeach
                    </select>
                @elseif (in_array($field['type'], ['textarea', 'json']))
                    <textarea class="form-control" rows="{{ $field['type'] === 'json' ? 6 : 4 }}"
                        name="{{ $name }}" id="{{ $formId }}-{{ $name }}"
                        placeholder="{{ $field['type'] === 'json' ? '{&quot;key&quot;: &quot;value&quot;}' : $field['label'] }}">{{ $value }}</textarea>
                @else
                    <input class="form-control" type="{{ $field['type'] }}" name="{{ $name }}"
                        id="{{ $formId }}-{{ $name }}" value="{{ $value }}"
                        @if ($field['type'] === 'number') step="any" @endif>
                @endif
            @endif
            @error($name)
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>
    </div>
@endforeach
