<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\FrontOfficePage;
use PrestaFlow\Library\Pages\v9\FrontOffice\Checkout\Page as CheckoutPage;

/**
 * Tunnel en quatre étapes : formulaire invité vérifié après saisie, et étape de
 * paiement sans moyen de paiement qui saute la suite au lieu d'échouer.
 */
final class CheckoutRobustnessTest extends TestCase
{
    private array $globals = [
        'PS_VERSION' => '9.2.1',
        'LOCALE' => 'fr',
        'PREFIX_LOCALE' => false,
        'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'DEBUG' => false,
        'VERBOSE' => false,
    ];

    /**
     * Page de commande dont evaluate() rejoue des résultats scriptés.
     *
     * @param list<mixed> $results
     */
    private function checkoutPage(array $results): CheckoutPage
    {
        return new class ($results, $this->globals) extends CheckoutPage {
            public array $receivedJs = [];
            private int $calls = 0;

            public function __construct(private array $results, array $globals)
            {
                parent::__construct(locale: 'fr', patchVersion: '9.2.1', globals: $globals, customs: []);
            }

            public function getPage()
            {
                $owner = $this;

                return new class ($owner) {
                    public function __construct(private object $owner)
                    {
                    }

                    public function evaluate(string $js)
                    {
                        $value = $this->owner->next($js);

                        return new class ($value) {
                            public function __construct(private mixed $value)
                            {
                            }

                            public function getReturnValue(?int $timeout = null): mixed
                            {
                                return $this->value;
                            }
                        };
                    }
                };
            }

            public function next(string $js): mixed
            {
                $this->receivedJs[] = $js;

                return $this->results[$this->calls++] ?? false;
            }
        };
    }

    public function testUnfilledFieldsListsOnlyWrongValues(): void
    {
        $this->assertSame([], FrontOfficePage::pfUnfilledFields(
            ['#a' => 'PrestaFlow', '#b' => 'Guest'],
            ['#a' => 'PrestaFlow', '#b' => ' Guest ']
        ));

        // Le cas observé sur Chrome Linux : formulaire invité resté vide.
        $this->assertSame(['#first', '#email'], FrontOfficePage::pfUnfilledFields(
            ['#first' => 'PrestaFlow', '#email' => 'pf@example.com'],
            ['#first' => '', '#email' => '']
        ));
    }

    public function testUnfilledFieldsIgnoresAFieldTheShopDoesNotShow(): void
    {
        // Un champ facultatif masqué (téléphone) ne peut pas bloquer le tunnel.
        $this->assertSame([], FrontOfficePage::pfUnfilledFields(
            ['#street' => '16 Main street', '#phone' => '0102030405'],
            ['#street' => '16 Main street', '#phone' => null]
        ));
    }

    public function testGuestCheckoutVerifiesWhatWasTyped(): void
    {
        $method = new \ReflectionMethod(CheckoutPage::class, 'checkoutAsGuest');
        $lines = file($method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString('pfFillInputsVerified(', $body);
        $this->assertStringContainsString('pfTickRequiredCheckboxes(', $body);
    }
}
