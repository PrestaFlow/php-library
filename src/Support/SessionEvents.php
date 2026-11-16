<?php

namespace PrestaFlow\Library\Support;

use Throwable;

/**
 * chrome-php ne distribue les événements CDP (console, réseau…) qu'en lisant le socket.
 * drain() lit ceux qui attendent, pour qu'une liste relevée soit à jour.
 */
final class SessionEvents
{
    /** Lit les événements en attente sur le socket (au plus $maxSeconds). */
    public static function drain(?object $session, float $maxSeconds = 0.1): void
    {
        if ($session === null || ! method_exists($session, 'getConnection')) {
            return;
        }
        try {
            $connection = $session->getConnection();
            $deadline = microtime(true) + $maxSeconds;
            while (microtime(true) < $deadline && $connection->waitForData(0.02)) {
                for ($i = 0; $i < 50; $i++) {
                    $connection->readLine();
                }
            }
        } catch (Throwable) {
            // Socket fermé (page détruite) : rien à lire.
        }
    }
}
