<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Lockout;
use App\Models\AuditLog;

class AuditLogSubscriber
{
    public function onLogin(Login $event): void
    {
        $actorType = $event->guard === 'staff' ? 'staff' : 'user';
        $actorId   = optional($event->user)->id;
        AuditLog::create([
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
            'action'     => 'login',
            'entity'     => '-',
            'entity_id'  => null,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => json_encode(['guard' => $event->guard]),
        ]);
    }

    public function onFailed(Failed $event): void
    {
        AuditLog::create([
            'actor_type' => $event->guard === 'staff' ? 'staff' : 'user',
            'actor_id'   => 0,
            'action'     => 'login',
            'entity'     => '-',
            'entity_id'  => null,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => json_encode(['failed_for' => $event->credentials['email'] ?? $event->credentials['login_code'] ?? null, 'guard' => $event->guard]),
        ]);
    }

    public function onLogout(Logout $event): void
    {
        AuditLog::create([
            'actor_type' => $event->guard === 'staff' ? 'staff' : 'user',
            'actor_id'   => optional($event->user)->id,
            'action'     => 'logout',
            'entity'     => '-',
            'entity_id'  => null,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => json_encode(['guard' => $event->guard]),
        ]);
    }

    public function onLockout(Lockout $event): void
    {
        AuditLog::create([
            'actor_type' => 'staff', // だいたい職員側で発生。必要なら判定強化
            'actor_id'   => 0,
            'action'     => 'setting', // もしくは 'login'
            'entity'     => 'rate_limit',
            'entity_id'  => null,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => json_encode(['key' => $event->key]),
        ]);
    }

    public function subscribe($events): void
    {
        $events->listen(Login::class,  [self::class, 'onLogin']);
        $events->listen(Failed::class, [self::class, 'onFailed']);
        $events->listen(Logout::class, [self::class, 'onLogout']);
        $events->listen(Lockout::class,[self::class, 'onLockout']);
    }
}
