@if($pkg && (($pkg['weight'] ?? null) !== null || ($pkg['length'] ?? null) !== null || ($pkg['customer_reference'] ?? null) || ($pkg['declared_value'] ?? null) !== null))
    <div class="fs-11 text-muted mt-1">
        @if(($pkg['weight'] ?? null) !== null)
            <span class="me-2"><i class="feather-box me-1"></i>{{ rtrim(rtrim(number_format($pkg['weight'], 2), '0'), '.') }} {{ $pkg['weight_unit'] ?? '' }}</span>
        @endif
        @if(($pkg['length'] ?? null) !== null)
            <span class="me-2"><i class="feather-maximize me-1"></i>{{ rtrim(rtrim(number_format($pkg['length'], 2), '0'), '.') }} &times; {{ rtrim(rtrim(number_format($pkg['width'], 2), '0'), '.') }} &times; {{ rtrim(rtrim(number_format($pkg['height'], 2), '0'), '.') }} {{ $pkg['dimension_unit'] ?? '' }}</span>
        @endif
        @if(!empty($pkg['customer_reference']))
            <span class="me-2"><i class="feather-hash me-1"></i>Ref: {{ $pkg['customer_reference'] }}</span>
        @endif
        @if(($pkg['declared_value'] ?? null) !== null)
            <span class="me-2"><i class="feather-dollar-sign me-1"></i>Declared: {{ number_format($pkg['declared_value'], 2) }}</span>
        @endif
    </div>
@endif
