<?php

namespace App\Libraries;

use App\Models\DomainEventLogModel;

// D-115: the first real, decoupled consumer of the domain-event layer.
// Persists every fired domain event with zero knowledge of which
// service published it — genuinely decoupled, not a relabeled direct
// call. Registered against each event name in app/Config/Events.php,
// never invoked directly by a publisher.
//
// A future queue-backed consumer (real notifications, analytics, an
// AI Gateway hook) would register the same way, against the same
// event names, without this listener or any publisher needing to
// change — that composability is the actual point of building this
// now, ahead of Background Jobs / AI Gateway themselves.
final class DomainEventLogListener
{
    public static function handle(string $eventName, array $payload): void
    {
        (new DomainEventLogModel())->record($eventName, $payload);
    }
}
