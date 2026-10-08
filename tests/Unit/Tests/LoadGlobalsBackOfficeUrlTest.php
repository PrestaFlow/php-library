<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

final class LoadGlobalsBackOfficeUrlTest extends TestCase
{
    /**
     * Construit une suite qui appelle loadGlobals() avec ces variables, puis restaure $_ENV.
     * Une valeur null retire la variable (cas « absente »).
     *
     * @param array<string, ?string> $env
     */
    private function suite(array $env): TestsSuite
    {
        $saved = [];
        foreach ($env as $key => $value) {
            $saved[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
            }
        }
        try {
            return new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
                public function relative(): ?string
                {
                    return $this->backOfficeRelative;
                }
            };
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /** @return array<string, array{string, string, string, ?string}> */
    public static function cases(): array
    {
        return [
            'slash initial' => ['https://x/', '/admin', 'https://x/admin/', '/admin'],
            'relatif' => ['https://x/', 'admin123', 'https://x/admin123/', 'admin123'],
            'requête FO ignorée' => ['https://x/?lang=2', 'admin-dev/', 'https://x/admin-dev/', 'admin-dev/'],
            'protocole' => ['https://x/', '//autre.test/admin', 'https://autre.test/admin/', null],
            'absolue majuscules' => ['https://x/', 'HTTPS://bo.test/admin', 'HTTPS://bo.test/admin/', null],
            'absolue' => ['https://x/', 'https://bo.test/admin/', 'https://bo.test/admin/', null],
            'relatif rogné' => ['https://x/', '  admin  ', 'https://x/admin/', 'admin'],
            'vide' => ['https://x/', '', 'https://x/', null],
        ];
    }

    /** @dataProvider cases */
    public function test_back_office_url_follows_the_single_rule(string $fo, string $bo, string $expected, ?string $relative): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => $fo, 'PRESTAFLOW_BO_URL' => $bo]);

        $this->assertSame($expected, $suite->getGlobals()['BO']['URL']);
        $this->assertSame($relative, $suite->relative());
    }

    public function test_front_office_url_is_unchanged(): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => 'https://x/?lang=2', 'PRESTAFLOW_BO_URL' => 'admin-dev/']);

        $this->assertSame('https://x/?lang=2/', $suite->getGlobals()['FO']['URL']);
    }

    public function test_missing_back_office_url_defaults_to_admin_dev(): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => 'https://x/fr?y=1', 'PRESTAFLOW_BO_URL' => null]);

        $this->assertSame('https://x/fr/admin-dev/', $suite->getGlobals()['BO']['URL']);
        $this->assertSame('admin-dev/', $suite->relative());
    }
}
