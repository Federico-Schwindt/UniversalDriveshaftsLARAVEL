<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    // Ported 1:1 from the FluentBooking "Universal Driveshafts LLC" calendar.
    private const TZ = 'America/New_York';
    private const INTERVAL = 45;      // slot_interval (minutes)
    private const DURATION = 60;      // event duration (minutes)
    private const BUFFER_BEFORE = 30; // buffer_time_before (minutes)
    private const RANGE_DAYS = 60;    // booking window
    private const SERVICES = [
        'U-Joint Replacement', 'Balance', 'Overall Repair', 'Other Service (Specify)',
    ];
    // Business hours in shop timezone (from the migrated availability): Mon-Fri 8-17, Sat 9-14, Sun closed.
    private const HOURS = [
        1 => ['08:00', '17:00'], 2 => ['08:00', '17:00'], 3 => ['08:00', '17:00'],
        4 => ['08:00', '17:00'], 5 => ['08:00', '17:00'], 6 => ['09:00', '14:00'],
    ];

    public function page()
    {
        return view('appointment', ['services' => self::SERVICES]);
    }

    /** GET /appointments/slots?date=YYYY-MM-DD&timezone=... — available start times for one day. */
    public function slots(Request $r)
    {
        $tz = $this->safeTz($r->query('timezone'));
        $date = $r->query('date');
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'slots' => []]);
        }
        return response()->json(['success' => true, 'slots' => $this->slotsForDay($day, $tz)]);
    }

    /** GET /appointments/days?timezone=... — which of the next RANGE_DAYS have availability. */
    public function days(Request $r)
    {
        $tz = $this->safeTz($r->query('timezone'));
        $now = Carbon::now($tz);
        $out = [];
        for ($i = 0; $i < self::RANGE_DAYS; $i++) {
            $day = $now->copy()->addDays($i)->startOfDay();
            $out[$day->format('Y-m-d')] = count($this->slotsForDay($day, $tz)) > 0;
        }
        return response()->json(['success' => true, 'days' => $out, 'timezone' => $tz]);
    }

    private function slotsForDay(Carbon $day, string $tz): array
    {
        $dow = (int) $day->isoWeekday();               // 1=Mon..7=Sun
        if (!isset(self::HOURS[$dow])) return [];

        // Build the shop-hours window on that calendar date, in shop tz.
        [$open, $close] = self::HOURS[$dow];
        $shopOpen = Carbon::parse($day->format('Y-m-d') . ' ' . $open, self::TZ);
        $shopClose = Carbon::parse($day->format('Y-m-d') . ' ' . $close, self::TZ);

        $minLead = Carbon::now(self::TZ)->addMinutes(self::BUFFER_BEFORE);
        $taken = $this->takenRanges($shopOpen, $shopClose);

        $slots = [];
        $cursor = $shopOpen->copy();
        while ($cursor->copy()->addMinutes(self::DURATION)->lte($shopClose)) {
            $end = $cursor->copy()->addMinutes(self::DURATION);
            $free = $cursor->gte($minLead) && !$this->overlaps($cursor, $end, $taken);
            if ($free) {
                $slots[] = [
                    'start_utc' => $cursor->copy()->utc()->toIso8601String(),
                    'label' => $cursor->copy()->setTimezone($tz)->format('g:i A'),
                ];
            }
            $cursor->addMinutes(self::INTERVAL);
        }
        return $slots;
    }

    private function takenRanges(Carbon $from, Carbon $to): array
    {
        return DB::table('appointments')->where('status', 'scheduled')
            ->where('ends_at', '>', $from->copy()->utc())
            ->where('starts_at', '<', $to->copy()->utc())
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($a) => [Carbon::parse($a->starts_at, 'UTC'), Carbon::parse($a->ends_at, 'UTC')])
            ->all();
    }

    private function overlaps(Carbon $s, Carbon $e, array $taken): bool
    {
        foreach ($taken as [$ts, $te]) {
            if ($s->lt($te) && $e->gt($ts)) return true;
        }
        return false;
    }

    /** POST /appointments — book. */
    public function book(Request $r)
    {
        $data = $r->validate([
            'service' => 'required|in:' . implode(',', self::SERVICES),
            'start_utc' => 'required|date',
            'timezone' => 'nullable|string|max:64',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'required|string|max:40',
            'address' => 'nullable|string|max:200',
            'car_make_model' => 'required|string|max:160',
            'details' => 'nullable|string|max:2000',
        ]);

        $tz = $this->safeTz($data['timezone'] ?? null);
        $start = Carbon::parse($data['start_utc'])->utc();

        // re-validate the slot server-side (must be a real, still-free business slot)
        $localDay = $start->copy()->setTimezone(self::TZ)->startOfDay();
        $valid = collect($this->slotsForDay($localDay, self::TZ))
            ->contains(fn ($s) => Carbon::parse($s['start_utc'])->eq($start));
        if (!$valid) {
            return response()->json(['success' => false, 'errors' => ['Sorry, that time was just taken or is no longer available. Please pick another slot.']], 422);
        }

        $ref = 'APT-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        $token = Str::random(32);
        $end = $start->copy()->addMinutes(self::DURATION);
        DB::table('appointments')->insert([
            'ref' => $ref, 'manage_token' => $token, 'service' => $data['service'], 'duration' => self::DURATION,
            'starts_at' => $start, 'ends_at' => $end, 'customer_timezone' => $tz,
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
            'address' => $data['address'] ?? null, 'car_make_model' => $data['car_make_model'],
            'details' => $data['details'] ?? null, 'ip' => $r->ip(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $appt = DB::table('appointments')->where('ref', $ref)->first();
        $this->sendConfirmation($appt);
        $this->notifyShop($appt);

        return response()->json(['success' => true, 'data' => [
            'ref' => $ref,
            'when' => $start->copy()->setTimezone(self::TZ)->format('l, F j, Y \a\t g:i A') . ' EST',
            'service' => $data['service'],
        ]]);
    }

    // ---- ICS calendar invite -----------------------------------------

    public function ics(Request $r, string $ref)
    {
        $a = $this->findByToken($ref, $r->query('token'));
        abort_unless($a, 404);
        return response($this->buildIcs($a), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $a->ref . '.ics"',
        ]);
    }

    private function buildIcs(object $a): string
    {
        $fmt = fn ($dt) => Carbon::parse($dt, 'UTC')->format('Ymd\THis\Z');
        $esc = fn ($s) => addcslashes((string) $s, ",;\\\n");
        $summary = 'UDS Appointment — ' . $a->service;
        $desc = "Service: {$a->service}\\nVehicle: {$a->car_make_model}\\nReference: {$a->ref}\\nUniversal Driveshafts LLC — (813) 524-6491";
        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Universal Driveshafts//Appointments//EN',
            'CALSCALE:GREGORIAN', 'METHOD:' . ($a->status === 'cancelled' ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT', 'UID:' . $a->ref . '@udshafts.com', 'DTSTAMP:' . $fmt(now()),
            'DTSTART:' . $fmt($a->starts_at), 'DTEND:' . $fmt($a->ends_at),
            'STATUS:' . ($a->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'SUMMARY:' . $esc($summary), 'DESCRIPTION:' . $desc,
            'LOCATION:' . $esc('11611 Prospect Drive STE 4, Odessa FL 33556'),
            'END:VEVENT', 'END:VCALENDAR',
        ]) . "\r\n";
    }

    // ---- Cancel -------------------------------------------------------

    public function cancelPage(Request $r, string $ref)
    {
        $a = $this->findByToken($ref, $r->query('token'));
        abort_unless($a, 404);
        return view('appointment-manage', ['a' => $a, 'mode' => 'cancel', 'token' => $r->query('token'), 'services' => self::SERVICES]);
    }

    public function cancel(Request $r, string $ref)
    {
        $a = $this->findByToken($ref, $r->input('token'));
        abort_unless($a, 404);
        if ($a->status !== 'cancelled') {
            DB::table('appointments')->where('id', $a->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            $a->status = 'cancelled';
            $this->sendCancellation($a);
        }
        return redirect('/appointment/' . $a->ref . '/cancel?token=' . $a->manage_token . '&done=1');
    }

    // ---- Reschedule ---------------------------------------------------

    public function reschedulePage(Request $r, string $ref)
    {
        $a = $this->findByToken($ref, $r->query('token'));
        abort_unless($a && $a->status !== 'cancelled', 404);
        return view('appointment-manage', ['a' => $a, 'mode' => 'reschedule', 'token' => $r->query('token'), 'services' => self::SERVICES]);
    }

    public function reschedule(Request $r, string $ref)
    {
        $a = $this->findByToken($ref, $r->input('token'));
        abort_unless($a && $a->status !== 'cancelled', 404);
        $start = Carbon::parse($r->input('start_utc'))->utc();
        $localDay = $start->copy()->setTimezone(self::TZ)->startOfDay();
        $valid = collect($this->slotsForDay($localDay, self::TZ))->contains(fn ($s) => Carbon::parse($s['start_utc'])->eq($start));
        if (!$valid) return response()->json(['success' => false, 'errors' => ['That time is no longer available. Please pick another.']], 422);

        DB::table('appointments')->where('id', $a->id)->update([
            'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(self::DURATION),
            'reminder_sent' => false, 'updated_at' => now(),
        ]);
        $a = DB::table('appointments')->where('id', $a->id)->first();
        $this->sendConfirmation($a, true);
        $this->notifyShop($a, true);
        return response()->json(['success' => true, 'data' => ['ref' => $a->ref,
            'when' => Carbon::parse($a->starts_at)->setTimezone(self::TZ)->format('l, F j, Y \a\t g:i A') . ' EST']]);
    }

    // ---- Emails -------------------------------------------------------

    private function manageBase(object $a): string
    {
        return url('/appointment/' . $a->ref);
    }

    private function sendConfirmation(object $a, bool $rescheduled = false): void
    {
        $when = Carbon::parse($a->starts_at)->setTimezone(self::TZ)->format('l, F j, Y \a\t g:i A') . ' EST';
        $base = $this->manageBase($a);
        $links = "Add to calendar: {$base}.ics?token={$a->manage_token}\n"
            . "Reschedule: {$base}/reschedule?token={$a->manage_token}\n"
            . "Cancel: {$base}/cancel?token={$a->manage_token}";
        $lead = $rescheduled ? "Your appointment has been rescheduled." : "Your appointment with Universal Driveshafts is confirmed.";
        try {
            Mail::send([], [], function ($m) use ($a, $when, $links, $lead) {
                $body = "Hi {$a->name},\n\n{$lead}\n\nReference: {$a->ref}\nService: {$a->service}\nWhen: {$when}\n"
                    . "Vehicle: {$a->car_make_model}\n\nLocation: 11611 Prospect Drive STE 4, Odessa FL 33556\n"
                    . "Questions? Call (813) 524-6491.\n\n{$links}\n\n— Universal Driveshafts LLC";
                $m->to($a->email)
                  ->subject(($lead === 'Your appointment has been rescheduled.' ? 'Appointment Rescheduled' : 'Appointment Confirmed') . " — {$a->service} ({$a->ref})")
                  ->text($body)
                  ->attachData($this->buildIcs($a), $a->ref . '.ics', ['mime' => 'text/calendar']);
            });
        } catch (\Throwable $e) {}
        try { app(\App\Services\SmsService::class)->send($a->phone, "Universal Driveshafts: your {$a->service} appointment is confirmed for {$when}. Ref {$a->ref}. (813) 524-6491"); } catch (\Throwable $e) {}
    }

    private function sendCancellation(object $a): void
    {
        $when = Carbon::parse($a->starts_at)->setTimezone(self::TZ)->format('l, F j, Y \a\t g:i A') . ' EST';
        try {
            Mail::raw("Hi {$a->name},\n\nYour appointment ({$a->ref}) for {$a->service} on {$when} has been cancelled.\n\n"
                . "Want to book again? Visit " . url('/make-an-appointment') . " or call (813) 524-6491.\n\n— Universal Driveshafts LLC",
                fn ($m) => $m->to($a->email)->subject("Appointment Cancelled — {$a->service} ({$a->ref})"));
            Mail::raw("Appointment CANCELLED.\nRef: {$a->ref}\nService: {$a->service}\nWhen: {$when}\nName: {$a->name}\nPhone: {$a->phone}",
                fn ($m) => $m->to('Inquiry@universaldriveshafts.com')->subject("Cancelled — {$a->service} ({$a->ref})"));
        } catch (\Throwable $e) {}
    }

    private function notifyShop(object $a, bool $rescheduled = false): void
    {
        $when = Carbon::parse($a->starts_at)->setTimezone(self::TZ)->format('l, F j, Y \a\t g:i A') . ' EST';
        $tag = $rescheduled ? 'Rescheduled' : 'New Appointment';
        try {
            Mail::raw("{$tag}.\n\nRef: {$a->ref}\nService: {$a->service}\nWhen: {$when}\n"
                . "Name: {$a->name}\nEmail: {$a->email}\nPhone: {$a->phone}\n"
                . "Address: " . ($a->address ?? '-') . "\nVehicle: {$a->car_make_model}\nDetails: " . ($a->details ?? '-'),
                fn ($m) => $m->to('Inquiry@universaldriveshafts.com')->subject("{$tag} — {$a->service} ({$a->ref})"));
        } catch (\Throwable $e) {}
    }

    private function findByToken(string $ref, ?string $token)
    {
        if (!$token) return null;
        return DB::table('appointments')->where('ref', $ref)->where('manage_token', $token)->first();
    }

    private function safeTz(?string $tz): string
    {
        // Single fixed timezone: everything is shown in the shop's local (Eastern) time.
        return self::TZ;
    }
}
