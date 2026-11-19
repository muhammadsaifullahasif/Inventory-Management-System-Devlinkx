<?php

namespace App\Http\Controllers;

use App\Models\MessageChannelState;
use App\Models\MessageSavedReply;
use App\Models\MessageSetting;
use App\Models\SalesChannel;
use App\Services\Messaging\MessageDriverManager;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\OfficeHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Settings > Messages: office hours, the automatic reply, and the saved replies staff can insert
 * from the inbox reply box.
 */
class MessageSettingController extends Controller
{
    public const DAY_LABELS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];

    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('manage message-settings'));
    }

    public function edit(MessageDriverManager $drivers): View
    {
        $settings = MessageSetting::current();

        return view('settings.messages', [
            'replies' => MessageSavedReply::query()->orderBy('title')->get(),
            'placeholders' => ['{buyer_name}', '{item_title}', '{order_number}'],
            'settings' => $settings,
            'days' => self::DAY_LABELS,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'officeIsOpen' => (new OfficeHours($settings))->isOpen(),
            'officeTimezone' => (new OfficeHours($settings))->timezone(),
            'stores' => SalesChannel::query()->orderBy('name')->get()
                ->filter(fn (SalesChannel $c) => $drivers->supports($c))
                ->map(fn (SalesChannel $c) => ['channel' => $c, 'state' => MessageChannelState::forChannel($c)])
                ->values(),
        ]);
    }

    /**
     * Office hours, the automatic reply and which stores use it.
     */
    public function updateAutoReply(Request $request, MessageDriverManager $drivers): RedirectResponse
    {
        $enabled = $request->boolean('auto_reply_enabled');

        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
            'auto_reply_text' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:' . MessageSender::MAX_LENGTH],
            'auto_reply_cooldown_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'hours' => ['required', 'array'],
            'stores' => ['nullable', 'array'],
            'stores.*' => ['integer'],
        ]);

        $hours = [];
        $errors = [];
        foreach (self::DAY_LABELS as $key => $label) {
            $row = (array) ($data['hours'][$key] ?? []);

            if (!empty($row['closed'])) {
                $hours[$key] = null;
                continue;
            }

            $open = $row['open'] ?? '';
            $close = $row['close'] ?? '';
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $close)) {
                $errors["hours.$key"] = "$label: enter an opening and a closing time, or mark the day as closed.";
            } elseif ($close <= $open) {
                $errors["hours.$key"] = "$label: closing time must be later than opening time.";
            } else {
                $hours[$key] = ['open' => $open, 'close' => $close];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $row = MessageSetting::query()->first() ?? new MessageSetting();
        $row->fill([
            'timezone' => $data['timezone'],
            'office_hours' => $hours,
            'auto_reply_enabled' => $enabled,
            'auto_reply_text' => trim((string) ($data['auto_reply_text'] ?? '')),
            'auto_reply_cooldown_hours' => (int) $data['auto_reply_cooldown_hours'],
        ])->save();

        $on = array_map('intval', $data['stores'] ?? []);
        SalesChannel::query()->get()
            ->filter(fn (SalesChannel $c) => $drivers->supports($c))
            ->each(fn (SalesChannel $c) => MessageChannelState::forChannel($c)->update(['auto_reply_enabled' => in_array($c->id, $on, true)]));

        return redirect()->route('settings.messages.edit')->with('success', 'Office hours and automatic reply saved.');
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
