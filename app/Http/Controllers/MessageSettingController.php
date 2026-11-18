<?php

namespace App\Http\Controllers;

use App\Models\MessageSavedReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Settings > Messages: the saved replies staff can insert from the inbox reply box.
 * (Office hours and auto-reply settings arrive with the auto-reply release.)
 */
class MessageSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('manage message-settings'));
    }

    public function edit(): View
    {
        return view('settings.messages', [
            'replies' => MessageSavedReply::query()->orderBy('title')->get(),
            'placeholders' => ['{buyer_name}', '{item_title}', '{order_number}'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MessageSavedReply::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('settings.messages.edit')->with('success', 'Saved reply added.');
    }

    public function update(Request $request, MessageSavedReply $reply): RedirectResponse
    {
        $reply->update($this->validated($request));

        return redirect()->route('settings.messages.edit')->with('success', 'Saved reply updated.');
    }

    public function destroy(MessageSavedReply $reply): RedirectResponse
    {
        $reply->delete();

        return redirect()->route('settings.messages.edit')->with('success', 'Saved reply deleted.');
    }

    /**
     * @return array{title:string, body:string, is_active:bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
