{{-- Small hover-info icon explaining how a summary card's number is calculated.
     Usage: @include('partials.info-tooltip', ['text' => 'Sum of paid orders total...'])
     Pass 'light' => true on dark/colored card backgrounds so the icon stays visible. --}}
<i class="feather-info {{ ($light ?? false) ? 'text-white-50' : 'text-muted' }} ms-1"
   data-bs-toggle="tooltip"
   data-bs-placement="top"
   title="{{ $text }}"
   style="cursor: help; font-size: 0.75rem; vertical-align: middle;"></i>
