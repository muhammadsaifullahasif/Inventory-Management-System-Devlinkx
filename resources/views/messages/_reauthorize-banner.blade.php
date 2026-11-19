@if(!empty($reauthorize))
    <div class="col-12">
        <div class="alert alert-warning d-flex align-items-start gap-3 mb-3" role="alert" id="msg-reauth-banner">
            <i class="feather-alert-triangle fs-4 mt-1"></i>
            <div>
                <strong>{{ count($reauthorize) === 1 ? 'One store has' : count($reauthorize) . ' stores have' }} stopped syncing messages.</strong>
                eBay has not given {{ count($reauthorize) === 1 ? 'it' : 'them' }} permission to read or send messages, so new buyer messages
                from {{ count($reauthorize) === 1 ? 'this store' : 'these stores' }} will not appear until {{ count($reauthorize) === 1 ? 'it is' : 'they are' }} re-authorized.
                <ul class="mb-0 mt-2">
                    @foreach($reauthorize as $store)
                        <li>
                            {{ $store['name'] }}
                            @can('edit sales-channels')
                                &mdash; <a href="{{ route('sales-channels.edit', $store['id']) }}" class="alert-link">open the store</a>, make sure its User Scopes include <code>commerce.message</code>, then click <strong>Reconnect</strong>
                            @endcan
                        </li>
                    @endforeach
                </ul>
                @cannot('edit sales-channels')
                    <div class="mt-2">Ask an administrator to re-authorize {{ count($reauthorize) === 1 ? 'this store' : 'these stores' }} with the messaging permission.</div>
                @endcannot
            </div>
        </div>
    </div>
@endif
