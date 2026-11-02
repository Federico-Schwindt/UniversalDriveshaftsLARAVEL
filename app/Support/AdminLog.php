<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AdminLog
{
    public static function record(string $action, string $label, ?string $subjectType = null, string|int|null $subjectId = null, array|string|null $detail = null): void
    {
        try {
            DB::table('admin_activity')->insert([
                'user_id' => auth()->id(), 'action' => $action, 'label' => mb_substr($label, 0, 200),
                'subject_type' => $subjectType, 'subject_id' => $subjectId === null ? null : (string) $subjectId,
                'detail' => is_array($detail) ? json_encode($detail) : $detail,
                'ip' => request()?->ip(), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    public static function for(string $subjectType, string|int $subjectId, int $limit = 30)
    {
        return DB::table('admin_activity as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.subject_type', $subjectType)->where('a.subject_id', (string) $subjectId)
            ->orderByDesc('a.id')->limit($limit)->get(['a.*', 'u.name as user_name']);
    }
}
