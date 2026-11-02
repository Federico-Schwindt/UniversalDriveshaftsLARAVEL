<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind';
    protected $description = 'Email a reminder ~24h before each scheduled appointment';

    public function handle(): int
    {
        $from = now()->addHours(23);
        $to = now()->addHours(25);
        $due = DB::table('appointments')
            ->where('status', 'scheduled')
            ->where('reminder_sent', false)
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        foreach ($due as $a) {
            $when = Carbon::parse($a->starts_at)->setTimezone('America/New_York')->format('l, F j, Y \a\t g:i A') . ' ET';
            $base = url('/appointment/' . $a->ref);
            try {
                Mail::raw(
                    "Hi {$a->name},\n\nReminder: your appointment with Universal Driveshafts is tomorrow.\n\n"
                    . "Service: {$a->service}\nWhen: {$when}\nVehicle: {$a->car_make_model}\n"
                    . "Location: 11611 Prospect Drive STE 4, Odessa FL 33556\n\n"
                    . "Reschedule: {$base}/reschedule?token={$a->manage_token}\n"
                    . "Cancel: {$base}/cancel?token={$a->manage_token}\n\n"
                    . "Questions? Call (813) 524-6491.\n\n— Universal Driveshafts LLC",
                    fn ($m) => $m->to($a->email)->subject("Reminder: your appointment is tomorrow — {$a->service}")
                );
                DB::table('appointments')->where('id', $a->id)->update(['reminder_sent' => true, 'updated_at' => now()]);
                $this->info("Reminded {$a->ref}");
            } catch (\Throwable $e) {
                $this->error("Failed {$a->ref}: " . $e->getMessage());
            }
        }
        $this->info('Done: ' . $due->count() . ' due.');
        return self::SUCCESS;
    }
}
