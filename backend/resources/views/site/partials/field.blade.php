@php
    $id = ($prefix ?? 'f').'-'.$name;
    $invalid = $errors->has($name);
    $type = $type ?? 'text';
@endphp
<label class="field {{ $full ?? false ? 'full' : '' }}" for="{{ $id }}">
    {{ $label }}
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}" placeholder="{{ $placeholder ?? '' }}" @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>{{ old($name, $value ?? '') }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if($required ?? false) required @endif @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
            @isset($placeholderOption)<option value="">{{ $placeholderOption }}</option>@endisset
            @foreach ($options as $opt)
                <option @selected(old($name, $value ?? '') === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value ?? '') }}" placeholder="{{ $placeholder ?? '' }}" @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset @isset($inputmode) inputmode="{{ $inputmode }}" @endisset @if($required ?? false) required @endif @isset($min) min="{{ $min }}" @endisset @isset($max) max="{{ $max }}" @endisset @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
    @endif
    @error($name)<span class="err" id="{{ $id }}-err">{{ $message }}</span>@enderror
</label>
