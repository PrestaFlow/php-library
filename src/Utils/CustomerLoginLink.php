<?php

namespace PrestaFlow\Library\Utils;

use RuntimeException;

/**
 * Customer login link of a cloud run (PRESTAFLOW_FO_LOGIN_URL).
 *
 * On a shop linked by the PrestaFlow module, the cloud never gets a customer
 * password: the module hands out a single-use link that opens the test
 * customer's session. The front-office login() opens it instead of filling the
 * form, at its first call without explicit credentials, so guest tests stay
 * guest until then. The link only works once per run.
 */
final class CustomerLoginLink
{
    private static bool $used = false;

    public static function url(): ?string
    {
        $url = Env::get('PRESTAFLOW_FO_LOGIN_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The link to open now, or null when the run has none (form login).
     *
     * @throws RuntimeException when the run's link was already used
     */
    public static function take(): ?string
    {
        $url = self::url();
        if ($url === null) {
            return null;
        }

        if (self::$used) {
            throw new RuntimeException(
                'The cloud customer login link only works once per run: this suite logs the customer in a second time. '
                . 'Keep the customer logged in, or pass explicit credentials to login().'
            );
        }

        self::$used = true;

        return $url;
    }

    /** Tests only. */
    public static function reset(): void
    {
        self::$used = false;
    }
}
