<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;
use App\Libraries\DomainEvents;
use App\Libraries\DomainEventLogListener;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * --------------------------------------------------------------------
 * Domain Events (D-115)
 * --------------------------------------------------------------------
 * The application's own domain-event layer, built on this same native
 * Events facade — previously used only for framework-internal hooks
 * above. Publishers (App\Libraries\*Service classes) fire a named
 * event from App\Libraries\DomainEvents at the exact point a real
 * business action completes; consumers subscribe here, with zero
 * knowledge of the publisher. Only one real consumer exists today
 * (DomainEventLogListener, a persistent event store) — a future
 * queue-backed consumer (real notifications, analytics, an AI Gateway
 * hook) registers the same way, against the same event names, without
 * any publisher or this listener needing to change.
 */
foreach ([
    DomainEvents::AUCTION_CREATED,
    DomainEvents::BID_PLACED,
    DomainEvents::SETTLEMENT_COMPLETED,
    DomainEvents::KYC_APPROVED,
    DomainEvents::DISPUTE_FILED,
] as $domainEventName) {
    Events::on($domainEventName, static function (array $payload) use ($domainEventName): void {
        DomainEventLogListener::handle($domainEventName, $payload);
    });
}
