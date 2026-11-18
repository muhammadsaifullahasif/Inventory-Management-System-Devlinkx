@extends('layouts.app')

@push('styles')
<style>
    /* ---------- Messages inbox: three panes (list | conversation | details) ---------- */
    .msg-app {
        --msg-panel: #ffffff;
        --msg-panel-alt: #f8fafc;
        --msg-border: #e5e9f2;
        --msg-text: #1b2540;
        --msg-muted: #6b7a99;
        --msg-hover: #f1f5fb;
        --msg-active: #eaf0ff;
        --msg-in-bg: #f1f4f9;
        --msg-in-fg: #1b2540;
        --msg-out-bg: #3454d1;
        --msg-out-fg: #ffffff;
        --msg-auto-bg: #e3f6ef;
        --msg-auto-fg: #0f5132;
        --msg-note-bg: #fff6d8;
        --msg-note-fg: #5c4600;
        color: var(--msg-text);
    }
    html.app-skin-dark .msg-app {
        --msg-panel: #0f172a;
        --msg-panel-alt: #121b2e;
        --msg-border: #1f2a44;
        --msg-text: #d6dae6;
        --msg-muted: #8b95ad;
        --msg-hover: #16213a;
        --msg-active: #1b2a4d;
        --msg-in-bg: #1a2540;
        --msg-in-fg: #d6dae6;
        --msg-out-bg: #3454d1;
        --msg-out-fg: #ffffff;
        --msg-auto-bg: #12352c;
        --msg-auto-fg: #9be3c6;
        --msg-note-bg: #3a2f10;
        --msg-note-fg: #f2dc8e;
    }

    .msg-tabs { display: flex; flex-wrap: wrap; gap: .25rem; padding: .35rem; margin-bottom: 1rem;
        background: var(--msg-panel); border: 1px solid var(--msg-border); border-radius: .6rem; width: fit-content; max-width: 100%; }
    .msg-tab { border: 0; background: transparent; color: var(--msg-muted); font-weight: 600; font-size: .85rem;
        padding: .45rem .8rem; border-radius: .45rem; display: inline-flex; align-items: center; gap: .4rem; cursor: pointer; }
    .msg-tab:hover { background: var(--msg-hover); color: var(--msg-text); }
    .msg-tab.active { background: #3454d1; color: #fff; }
    .msg-tab .msg-count { font-size: .72rem; padding: .05rem .4rem; border-radius: 999px; background: rgba(120,130,160,.18); color: inherit; }
    .msg-tab.active .msg-count { background: rgba(255,255,255,.25); }

    .msg-grid { display: grid; grid-template-columns: 340px minmax(0, 1fr) 300px; gap: 1rem;
        height: calc(100vh - 260px); min-height: 540px; }
    .msg-pane { background: var(--msg-panel); border: 1px solid var(--msg-border); border-radius: .75rem;
        display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    .msg-pane-head { padding: .75rem; border-bottom: 1px solid var(--msg-border); display: flex; flex-direction: column; gap: .5rem; }

    /* list */
    .msg-list { overflow-y: auto; flex: 1; }
    .msg-row { display: flex; gap: .65rem; padding: .7rem .8rem; border-bottom: 1px solid var(--msg-border); cursor: pointer;
        border-left: 3px solid transparent; }
    .msg-row:hover { background: var(--msg-hover); }
    .msg-row.active { background: var(--msg-active); border-left-color: #3454d1; }
    .msg-avatar { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .78rem; background: var(--msg-in-bg); color: var(--msg-muted); }
    .msg-row-main { min-width: 0; flex: 1; }
    .msg-row-top { display: flex; justify-content: space-between; gap: .5rem; }
    .msg-row-name { font-weight: 600; font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-row-time { font-size: .72rem; color: var(--msg-muted); white-space: nowrap; }
    .msg-row-preview { font-size: .8rem; color: var(--msg-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: .1rem 0 .3rem; }
    .msg-row.unread .msg-row-name, .msg-row.unread .msg-row-preview { color: var(--msg-text); font-weight: 700; }
    .msg-row-foot { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
    .msg-unread-dot { min-width: 20px; height: 20px; padding: 0 .35rem; border-radius: 999px; background: #3454d1; color: #fff;
        font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }

    /* store chips: 8 deterministic colors */
    .msg-chip { display: inline-block; max-width: 100%; padding: .1rem .5rem; border-radius: .35rem; font-size: .7rem; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: var(--chip-bg); color: var(--chip-fg); }
    .msg-chip-1 { --chip-bg: #e0ecff; --chip-fg: #1d4ed8; } .msg-chip-2 { --chip-bg: #dcfce7; --chip-fg: #15803d; }
    .msg-chip-3 { --chip-bg: #fef3c7; --chip-fg: #b45309; } .msg-chip-4 { --chip-bg: #fce7f3; --chip-fg: #be185d; }
    .msg-chip-5 { --chip-bg: #ede9fe; --chip-fg: #6d28d9; } .msg-chip-6 { --chip-bg: #cffafe; --chip-fg: #0e7490; }
    .msg-chip-7 { --chip-bg: #ffedd5; --chip-fg: #c2410c; } .msg-chip-8 { --chip-bg: #e2e8f0; --chip-fg: #334155; }
    html.app-skin-dark .msg-chip-1 { --chip-bg: #1c2f5e; --chip-fg: #9db8ff; } html.app-skin-dark .msg-chip-2 { --chip-bg: #14352a; --chip-fg: #8de0ae; }
    html.app-skin-dark .msg-chip-3 { --chip-bg: #3b2f10; --chip-fg: #f1cf7a; } html.app-skin-dark .msg-chip-4 { --chip-bg: #3d1a2f; --chip-fg: #f5a3cb; }
    html.app-skin-dark .msg-chip-5 { --chip-bg: #2c2350; --chip-fg: #c3b2f7; } html.app-skin-dark .msg-chip-6 { --chip-bg: #123640; --chip-fg: #86dff0; }
    html.app-skin-dark .msg-chip-7 { --chip-bg: #3d2512; --chip-fg: #f5b98a; } html.app-skin-dark .msg-chip-8 { --chip-bg: #232c40; --chip-fg: #b6c0d6; }

    .msg-status { font-size: .68rem; font-weight: 700; padding: .1rem .45rem; border-radius: .35rem; white-space: nowrap; }
    .msg-status-pending { background: #fff1d6; color: #9a5b00; }
    .msg-status-awaiting_buyer { background: #e0ecff; color: #1d4ed8; }
    .msg-status-resolved { background: #dcfce7; color: #15803d; }
    html.app-skin-dark .msg-status-pending { background: #3b2f10; color: #f1cf7a; }
    html.app-skin-dark .msg-status-awaiting_buyer { background: #1c2f5e; color: #9db8ff; }
    html.app-skin-dark .msg-status-resolved { background: #14352a; color: #8de0ae; }

    .msg-more { padding: .6rem; text-align: center; }

    /* conversation */
    .msg-thread-head { padding: .75rem 1rem; border-bottom: 1px solid var(--msg-border); display: flex; align-items: center; gap: .75rem; }
    .msg-thread-head .msg-title { min-width: 0; flex: 1; }
    .msg-thread-head h6 { margin: 0; font-size: .95rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-thread-body { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .5rem; background: var(--msg-panel-alt); }
    .msg-day { align-self: center; font-size: .72rem; color: var(--msg-muted); margin: .5rem 0; }
    .msg-bubble-wrap { display: flex; flex-direction: column; max-width: 78%; }
    .msg-bubble-wrap.out { align-self: flex-end; align-items: flex-end; }
    .msg-bubble-wrap.in { align-self: flex-start; align-items: flex-start; }
    .msg-bubble { padding: .55rem .8rem; border-radius: .8rem; font-size: .88rem; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
    .msg-b-in { background: var(--msg-in-bg); color: var(--msg-in-fg); border-bottom-left-radius: .2rem; }
    .msg-b-out { background: var(--msg-out-bg); color: var(--msg-out-fg); border-bottom-right-radius: .2rem; }
    .msg-b-auto { background: var(--msg-auto-bg); color: var(--msg-auto-fg); border-bottom-right-radius: .2rem; }
    .msg-b-note { background: var(--msg-note-bg); color: var(--msg-note-fg); }
    .msg-meta { font-size: .7rem; color: var(--msg-muted); margin-top: .15rem; }
    .msg-meta.failed { color: #dc3545; }
    .msg-attach { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .4rem; }
    .msg-attach img { max-width: 180px; max-height: 140px; border-radius: .5rem; border: 1px solid var(--msg-border); display: block; }
    .msg-attach a.file { font-size: .78rem; text-decoration: underline; color: inherit; }
    .msg-thread-foot { padding: .7rem 1rem; border-top: 1px solid var(--msg-border); font-size: .8rem; color: var(--msg-muted); }
    .msg-banner { margin: .5rem 1rem 0; padding: .45rem .7rem; border-radius: .5rem; font-size: .78rem; background: #fff1d6; color: #7a4a00; }
    html.app-skin-dark .msg-banner { background: #3b2f10; color: #f1cf7a; }

    .msg-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; color: var(--msg-muted); padding: 2rem; text-align: center; }
    .msg-empty i { font-size: 2rem; opacity: .6; }

    /* details */
    .msg-details { overflow-y: auto; }
    .msg-card { padding: .9rem 1rem; border-bottom: 1px solid var(--msg-border); }
    .msg-card h6 { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: var(--msg-muted); margin: 0 0 .6rem; font-weight: 700; }
    .msg-kv { display: flex; justify-content: space-between; gap: .5rem; font-size: .83rem; padding: .2rem 0; }
    .msg-kv span:first-child { color: var(--msg-muted); }
    .msg-product { display: flex; gap: .7rem; align-items: center; margin-top: .5rem; padding: .6rem; border: 1px solid var(--msg-border); border-radius: .6rem; }
    .msg-product img { width: 52px; height: 52px; object-fit: cover; border-radius: .4rem; background: var(--msg-in-bg); }
    .msg-product .noimg { width: 52px; height: 52px; border-radius: .4rem; background: var(--msg-in-bg); display: flex; align-items: center; justify-content: center; color: var(--msg-muted); }

    .msg-back, .msg-info { display: none; }
    .msg-close { display: inline-flex; flex: 0 0 auto; }

    /* narrower screens: one pane at a time */
    @media (max-width: 1399.98px) {
        .msg-grid { grid-template-columns: 320px minmax(0, 1fr); }
        .msg-details-pane { display: none; }
        .msg-app.show-details .msg-details-pane { display: flex; position: fixed; inset: 70px 0 0 auto; width: min(340px, 100vw); z-index: 1040; border-radius: 0; box-shadow: -8px 0 24px rgba(0,0,0,.18); }
        .msg-info { display: inline-flex; }
    }
    @media (max-width: 991.98px) {
        .msg-grid { grid-template-columns: minmax(0, 1fr); height: calc(100vh - 230px); }
        .msg-thread-pane { display: none; }
        .msg-app.show-thread .msg-list-pane { display: none; }
        .msg-app.show-thread .msg-thread-pane { display: flex; }
        .msg-back { display: inline-flex; }
        .msg-close { display: none; }   /* the back arrow does the same job on phones */
        .msg-bubble-wrap { max-width: 92%; }
    }
</style>
@endpush

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Messages</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Messages</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @if($health['total'] > 0)
                        @if(empty($health['problems']))
                            <span class="badge bg-soft-success text-success" title="Every store synced within the last 15 minutes">
                                <i class="feather-check me-1"></i>{{ $health['total'] }} {{ \Illuminate\Support\Str::plural('store', $health['total']) }} syncing
                            </span>
                        @else
                            <span class="badge bg-soft-warning text-warning"
                                  title="@foreach($health['problems'] as $p){{ $p['name'] }}: {{ \Illuminate\Support\Str::limit($p['reason'], 120) }}&#10;@endforeach">
                                <i class="feather-alert-triangle me-1"></i>{{ count($health['problems']) }} of {{ $health['total'] }} {{ \Illuminate\Support\Str::plural('store', $health['total']) }} need attention
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
<div class="col-12">
    <div class="msg-app" id="msg-app">
        <div class="msg-tabs" id="msg-tabs" role="tablist">
            <button class="msg-tab active" data-tab="all" type="button">All <span class="msg-count" data-count="all">0</span></button>
            <button class="msg-tab" data-tab="pending" type="button">Needs reply <span class="msg-count" data-count="pending">0</span></button>
            <button class="msg-tab" data-tab="unread" type="button">Unread <span class="msg-count" data-count="unread">0</span></button>
            <button class="msg-tab" data-tab="mine" type="button">Assigned to me <span class="msg-count" data-count="mine">0</span></button>
            <button class="msg-tab" data-tab="awaiting" type="button">Awaiting buyer <span class="msg-count" data-count="awaiting">0</span></button>
            <button class="msg-tab" data-tab="resolved" type="button">Resolved <span class="msg-count" data-count="resolved">0</span></button>
        </div>

        <div class="msg-grid">
            {{-- ===== Thread list ===== --}}
            <section class="msg-pane msg-list-pane">
                <div class="msg-pane-head">
                    <input type="search" id="msg-search" class="form-control form-control-sm" placeholder="Search buyer, order or message" autocomplete="off">
                    <select id="msg-store" class="form-select form-select-sm">
                        <option value="">All stores ({{ count($stores) }})</option>
                        @foreach($stores as $store)
                            <option value="{{ $store['id'] }}">{{ $store['short'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="msg-list" id="msg-list"></div>
            </section>

            {{-- ===== Conversation ===== --}}
            <section class="msg-pane msg-thread-pane" id="msg-thread-pane">
                <div class="msg-empty" id="msg-thread-empty">
                    <i class="feather-mail"></i>
                    <strong>Select a conversation</strong>
                    <span>Pick a buyer on the left to read the messages.</span>
                </div>
                <div class="d-none flex-column flex-grow-1" id="msg-thread-view" style="min-height:0">
                    <div class="msg-thread-head">
                        <button type="button" class="btn btn-sm btn-light-brand msg-back" id="msg-back" title="Back to list"><i class="feather-arrow-left"></i></button>
                        <div class="msg-avatar" id="msg-th-avatar"></div>
                        <div class="msg-title">
                            <h6 id="msg-th-name"></h6>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-1" id="msg-th-meta"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-brand msg-info" id="msg-info" title="Buyer & order details"><i class="feather-info"></i></button>
                        <button type="button" class="btn btn-sm btn-light-brand msg-close" id="msg-close" title="Close conversation" aria-label="Close conversation"><i class="feather-x"></i></button>
                    </div>
                    <div id="msg-th-banner"></div>
                    <div class="msg-thread-body" id="msg-th-body"></div>
                    <div class="msg-thread-foot">
                        <i class="feather-lock me-1"></i>Replying from the inbox is coming in the next update. For now, reply from the sales channel itself.
                    </div>
                </div>
            </section>

            {{-- ===== Details ===== --}}
            <aside class="msg-pane msg-details-pane msg-details" id="msg-details-pane">
                <div class="msg-empty" id="msg-details-empty">
                    <i class="feather-user"></i>
                    <span>Buyer and order details appear here.</span>
                </div>
                <div class="d-none" id="msg-details-view"></div>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var URLS = {
        feed: @json(route('messages.feed')),
        base: @json(url('/messages'))
    };
    var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var POLL_MS = 20000;

    var state = { tab: 'all', store: '', q: '', page: 1, hasMore: false, threads: [], counts: null,
                  selectedId: null, selectedLastAt: null, token: 0, signature: '' };

    var $app = document.getElementById('msg-app');
    var $list = document.getElementById('msg-list');

    // ---------------------------------------------------------------- helpers
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function safeUrl(u) { return /^https?:\/\//i.test(u || '') ? u : null; }
    function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function fmtListTime(iso) {
        if (!iso) return '';
        var d = new Date(iso), now = new Date();
        if (sameDay(d, now)) return d.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
        if (now - d < 6 * 864e5) return d.toLocaleDateString([], {weekday: 'short'});
        return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
    }
    function fmtDay(iso) {
        var d = new Date(iso), now = new Date(), y = new Date(now.getTime() - 864e5);
        if (sameDay(d, now)) return 'Today';
        if (sameDay(d, y)) return 'Yesterday';
        return d.toLocaleDateString([], {weekday: 'short', month: 'short', day: 'numeric', year: d.getFullYear() === now.getFullYear() ? undefined : 'numeric'});
    }
    function fmtTime(iso) { return iso ? new Date(iso).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : ''; }

    function request(method, url) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF}
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) { return {ok: r.ok, status: r.status, data: data}; });
        });
    }

    // ------------------------------------------------------------------- list
    function feedUrl(page) {
        var p = new URLSearchParams({tab: state.tab, page: page});
        if (state.store) p.set('store', state.store);
        if (state.q) p.set('q', state.q);
        return URLS.feed + '?' + p.toString();
    }

    function signatureOf(threads, counts) {
        return threads.map(function (t) { return [t.id, t.last_at, t.unread, t.status].join(':'); }).join('|') + JSON.stringify(counts);
    }

    function setCounts(counts) {
        state.counts = counts;
        document.querySelectorAll('[data-count]').forEach(function (el) {
            el.textContent = counts[el.getAttribute('data-count')] || 0;
        });
        window.dispatchEvent(new Event('messages:counts-changed'));
    }

    function rowHtml(t) {
        var store = t.store ? '<span class="msg-chip msg-chip-' + t.store.color + '" title="' + esc(t.store.name) + '">' + esc(t.store.short) + '</span>' : '';
        var badge = t.unread > 0 ? '<span class="msg-unread-dot">' + t.unread + '</span>'
                                 : '<span class="msg-status msg-status-' + esc(t.status) + '">' + esc(t.status_label) + '</span>';
        var you = t.direction === 'outbound' ? 'You: ' : '';
        return '<div class="msg-row' + (t.unread > 0 ? ' unread' : '') + (t.id === state.selectedId ? ' active' : '') + '" data-id="' + t.id + '">'
             + '<div class="msg-avatar">' + esc(t.initials) + '</div>'
             + '<div class="msg-row-main">'
             +   '<div class="msg-row-top"><span class="msg-row-name">' + esc(t.buyer) + '</span><span class="msg-row-time">' + esc(fmtListTime(t.last_at)) + '</span></div>'
             +   '<div class="msg-row-preview">' + esc(you + t.preview) + '</div>'
             +   '<div class="msg-row-foot">' + store + badge + '</div>'
             + '</div></div>';
    }

    function renderList() {
        if (!state.threads.length) {
            $list.innerHTML = '<div class="msg-empty"><i class="feather-inbox"></i><strong>No conversations</strong><span>'
                + (state.q ? 'Nothing matches your search.' : 'Nothing in this view yet.') + '</span></div>';
            return;
        }
        $list.innerHTML = state.threads.map(rowHtml).join('')
            + (state.hasMore ? '<div class="msg-more"><button type="button" class="btn btn-sm btn-light-brand" id="msg-more">Load more</button></div>' : '');
    }

    function loadList(reset) {
        var page = reset ? 1 : state.page + 1;
        return request('GET', feedUrl(page)).then(function (res) {
            if (!res.ok) { return; }
            var d = res.data;
            if (reset) { state.threads = d.threads; state.page = 1; }
            else { state.threads = state.threads.concat(d.threads); state.page = page; }
            state.hasMore = d.has_more;
            state.signature = signatureOf(state.threads.slice(0, 30), d.counts);
            setCounts(d.counts);
            renderList();
        });
    }

    // Poll: re-read page 1; redraw only when something changed, and only if the user is on page 1.
    function poll() {
        if (document.hidden) return;
        var requestedFilters = [state.tab, state.store, state.q].join('|');
        request('GET', feedUrl(1)).then(function (res) {
            if (!res.ok || requestedFilters !== [state.tab, state.store, state.q].join('|')) return;
            var d = res.data;
            setCounts(d.counts);
            if (state.page === 1) {
                var sig = signatureOf(d.threads, d.counts);
                if (sig !== state.signature) {
                    state.signature = sig;
                    state.threads = d.threads;
                    state.hasMore = d.has_more;
                    renderList();
                }
            }
            // The open conversation got a newer message: reload it from our database.
            if (state.selectedId) {
                var cur = d.threads.filter(function (t) { return t.id === state.selectedId; })[0];
                if (cur && cur.last_at && cur.last_at !== state.selectedLastAt) openThread(state.selectedId, {skipRefresh: true, keepScroll: false});
            }
        });
    }

    // ----------------------------------------------------------- conversation
    function attachmentsHtml(list) {
        if (!list || !list.length) return '';
        return '<div class="msg-attach">' + list.map(function (a) {
            var url = safeUrl(a.url);
            if (!url) return '';
            if ((a.type || '').toUpperCase() === 'IMAGE') {
                return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer"><img src="' + esc(url) + '" alt="' + esc(a.name || 'attachment') + '" loading="lazy"></a>';
            }
            return '<a class="file" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer"><i class="feather-paperclip me-1"></i>' + esc(a.name || 'attachment') + '</a>';
        }).join('') + '</div>';
    }

    function messagesHtml(messages) {
        var out = '', lastDay = '';
        messages.forEach(function (m) {
            if (m.sent_at) {
                var day = fmtDay(m.sent_at);
                if (day !== lastDay) { out += '<div class="msg-day">' + esc(day) + '</div>'; lastDay = day; }
            }
            var side = (m.kind === 'inbound') ? 'in' : 'out';
            var cls = {inbound: 'msg-b-in', outbound: 'msg-b-out', auto_reply: 'msg-b-auto', note: 'msg-b-note'}[m.kind] || 'msg-b-in';
            var label = m.kind === 'auto_reply' ? 'Automatic reply' : (m.kind === 'note' ? 'Internal note' : (m.sender || ''));
            out += '<div class="msg-bubble-wrap ' + side + '">'
                 +   '<div class="msg-bubble ' + cls + '">' + esc(m.body) + attachmentsHtml(m.attachments) + '</div>'
                 +   '<div class="msg-meta' + (m.failed ? ' failed' : '') + '">' + esc(label ? label + ' · ' : '') + esc(fmtTime(m.sent_at)) + (m.failed ? ' · not delivered' : '') + '</div>'
                 + '</div>';
        });
        return out || '<div class="msg-empty"><span>No messages stored for this conversation yet.</span></div>';
    }

    function renderThread(data, opts) {
        var t = data.thread;
        document.getElementById('msg-thread-empty').classList.add('d-none');
        var view = document.getElementById('msg-thread-view');
        view.classList.remove('d-none'); view.classList.add('d-flex');

        document.getElementById('msg-th-avatar').textContent = t.initials;
        document.getElementById('msg-th-name').textContent = t.buyer;
        var store = t.store ? '<span class="msg-chip msg-chip-' + t.store.color + '">' + esc(t.store.short) + '</span>' : '';
        document.getElementById('msg-th-meta').innerHTML = store
            + '<span class="msg-status msg-status-' + esc(t.status) + '">' + esc(t.status_label) + '</span>'
            + '<span class="text-muted fs-12">' + esc(t.kind_label) + '</span>'
            + (t.subject ? '<span class="text-muted fs-12">· ' + esc(t.subject) + '</span>' : '');

        var body = document.getElementById('msg-th-body');
        var atBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 80;
        body.innerHTML = messagesHtml(data.messages);
        if (!opts || !opts.keepScroll || atBottom) body.scrollTop = body.scrollHeight;

        renderDetails(data);
    }

    function renderDetails(data) {
        var t = data.thread, o = data.order, p = data.product;
        var h = '';

        h += '<div class="msg-card"><h6>Buyer &amp; order</h6>'
           + '<div class="d-flex align-items-center gap-2 mb-2"><div class="msg-avatar">' + esc(t.initials) + '</div><div style="min-width:0">'
           + '<div class="fw-semibold text-truncate">' + esc(t.buyer) + '</div>'
           + (t.store ? '<span class="msg-chip msg-chip-' + t.store.color + '">' + esc(t.store.short) + '</span>' : '')
           + '</div></div>'
           + '<div class="msg-kv"><span>Type</span><span>' + esc(t.kind_label) + '</span></div>';
        if (o) {
            h += '<div class="msg-kv"><span>Order</span><span>' + (o.url ? '<a href="' + esc(o.url) + '">#' + esc(o.number) + '</a>' : '#' + esc(o.number)) + '</span></div>'
               + '<div class="msg-kv"><span>Status</span><span>' + esc(o.status || '-') + '</span></div>'
               + '<div class="msg-kv"><span>Total</span><span>' + esc((o.currency ? o.currency + ' ' : '') + (o.total != null ? o.total : '-')) + '</span></div>';
        } else if (t.kind !== 'platform_system') {
            h += '<div class="msg-kv"><span>Order</span><span class="text-muted">No linked order yet</span></div>';
        }
        h += '</div>';

        if (p) {
            var img = safeUrl(p.image);
            h += '<div class="msg-card"><h6>Listing</h6><div class="msg-product">'
               + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy">' : '<div class="noimg"><i class="feather-package"></i></div>')
               + '<div style="min-width:0"><div class="fw-semibold" style="font-size:.84rem">' + esc(p.name || ('Listing ' + p.reference)) + '</div>'
               + (p.sku ? '<div class="text-muted fs-12">SKU ' + esc(p.sku) + '</div>' : '')
               + (p.price != null ? '<div class="fs-12">' + esc(p.price) + '</div>' : '')
               + '</div></div>'
               + '<div class="msg-kv mt-2"><span>Listing ID</span><span>' + (safeUrl(p.listing_url) ? '<a href="' + esc(p.listing_url) + '" target="_blank" rel="noopener noreferrer">' + esc(p.reference) + '</a>' : esc(p.reference)) + '</span></div>'
               + (p.linked ? '' : '<div class="text-muted fs-12">Not matched to a product in your catalog.</div>')
               + '</div>';
        }

        h += '<div class="msg-card"><h6>Team activity</h6>'
           + '<div class="msg-kv"><span>Assigned to</span><span>' + (t.assignee ? esc(t.assignee.name) : '<span class="text-muted">Unassigned</span>') + '</span></div>'
           + '<div class="text-muted fs-12 mt-1">Internal notes are visible to your team only.</div></div>';

        document.getElementById('msg-details-empty').classList.add('d-none');
        var view = document.getElementById('msg-details-view');
        view.classList.remove('d-none');
        view.innerHTML = h;
    }

    function showBanner(text) {
        document.getElementById('msg-th-banner').innerHTML = text ? '<div class="msg-banner"><i class="feather-alert-triangle me-1"></i>' + esc(text) + '</div>' : '';
    }

    // Show what we have stored right away, then ask the platform for anything newer.
    function openThread(id, opts) {
        opts = opts || {};
        state.selectedId = id;
        var token = ++state.token;
        $app.classList.add('show-thread');
        document.querySelectorAll('.msg-row').forEach(function (r) { r.classList.toggle('active', Number(r.dataset.id) === id); });

        return request('GET', URLS.base + '/' + id).then(function (res) {
            if (token !== state.token) return;
            if (!res.ok) { showBanner('Could not load this conversation.'); return; }
            state.selectedLastAt = res.data.thread.last_at;
            showBanner('');
            renderThread(res.data, opts);
            if (opts.skipRefresh) return;

            return request('POST', URLS.base + '/' + id + '/refresh').then(function (r2) {
                if (token !== state.token) return;
                if (r2.data && r2.data.thread) {
                    state.selectedLastAt = r2.data.thread.last_at;
                    if (r2.ok && r2.data.new_messages > 0) { renderThread(r2.data, {keepScroll: true}); loadList(true); }
                }
                showBanner(r2.ok ? '' : 'Could not check for newer messages right now. Showing what is stored.');
            });
        });
    }

    // Close the open conversation and go back to the empty state (also what the phone back arrow does).
    function closeThread() {
        state.selectedId = null;
        state.selectedLastAt = null;
        state.token++;   // any request still in flight for the closed conversation is ignored when it returns

        $app.classList.remove('show-thread', 'show-details');
        document.querySelectorAll('.msg-row.active').forEach(function (r) { r.classList.remove('active'); });

        var view = document.getElementById('msg-thread-view');
        view.classList.add('d-none'); view.classList.remove('d-flex');
        document.getElementById('msg-thread-empty').classList.remove('d-none');

        document.getElementById('msg-details-view').classList.add('d-none');
        document.getElementById('msg-details-empty').classList.remove('d-none');
        showBanner('');
    }

    // ----------------------------------------------------------------- events
    $list.addEventListener('click', function (e) {
        var more = e.target.closest('#msg-more');
        if (more) { loadList(false); return; }
        var row = e.target.closest('.msg-row');
        if (row) openThread(Number(row.dataset.id));
    });

    document.getElementById('msg-tabs').addEventListener('click', function (e) {
        var tab = e.target.closest('.msg-tab');
        if (!tab) return;
        document.querySelectorAll('.msg-tab').forEach(function (b) { b.classList.toggle('active', b === tab); });
        state.tab = tab.dataset.tab;
        loadList(true);
    });

    document.getElementById('msg-store').addEventListener('change', function () { state.store = this.value; loadList(true); });

    var searchTimer = null;
    document.getElementById('msg-search').addEventListener('input', function () {
        var v = this.value.trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.q = v; loadList(true); }, 300);
    });

    document.getElementById('msg-back').addEventListener('click', closeThread);
    document.getElementById('msg-close').addEventListener('click', closeThread);
    document.getElementById('msg-info').addEventListener('click', function () { $app.classList.toggle('show-details'); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (/^(INPUT|SELECT|TEXTAREA)$/.test((e.target.tagName || ''))) return;   // Esc inside the search box is not "close"
        if ($app.classList.contains('show-details')) { $app.classList.remove('show-details'); return; }
        if (state.selectedId) closeThread();
    });

    loadList(true);
    setInterval(poll, POLL_MS);
})();
</script>
@endpush
