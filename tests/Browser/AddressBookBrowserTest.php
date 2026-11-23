<?php

namespace PrestaFlow\Tests\Browser\AddressBookFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Addresses\Page as AddressesPage;

    /** The address book on a chrome-php page handed in by the test, without a suite. */
    class Addresses extends AddressesPage
    {
        public static $chromePage = null;

        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return self::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser\AddressBookFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Address\Page as AddressPage;

    class Address extends AddressPage
    {
        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return Addresses::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\AddressBookFixtures\Address;
    use PrestaFlow\Tests\Browser\AddressBookFixtures\Addresses;

    /** The address book and the address form, as Classic (1.7 to 9) and hummingbird render them. */
    final class AddressBookBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        private const CLASSIC_BLOCK = '<div class="col-lg-4 col-md-6 col-sm-6"><article id="address-%1$s" class="address" data-id-address="%1$s">'
            . '<div class="address-body"><h4>%2$s</h4><address>Jane Doe<br>%3$s<br>75002 %4$s<br>France</address></div>'
            . '<div class="address-footer"><a href="data:text/html,edit-%1$s" data-link-action="edit-address"><i class="material-icons">&#xE254;</i><span>Update</span></a>'
            . '<a href="data:text/html,delete-%1$s" data-link-action="delete-address"><i class="material-icons">&#xE872;</i><span>Delete</span></a></div>'
            . '</article></div>';

        private const CLASSIC_LIST = '<section id="content" class="page-content">%s<div class="clearfix"></div>'
            . '<div class="addresses-footer"><a href="data:text/html,new-address" data-link-action="add-address"><span>Create new address</span></a></div></section>';

        private const HUMMINGBIRD_BLOCK = '<article id="address-%1$s" class="address-card" data-id-address="%1$s"><div class="address-card__container">'
            . '<div class="address-card__header"><span class="address-card__alias">%2$s</span></div>'
            . '<address class="address-card__content">Jane Doe<br>%3$s<br>75002 %4$s<br>France</address>'
            . '<div class="address-card__actions"><a class="address-card__edit link-body-emphasis" href="data:text/html,edit-%1$s" data-link-action="edit-address" role="button">Edit</a>'
            . '<a class="address-card__delete link-danger" href="data:text/html,delete-%1$s" data-link-action="delete-address" role="button">Delete</a></div>'
            . '</div></article>';

        private const HUMMINGBIRD_LIST = '<div class="addresses"><div class="addresses__list">%s'
            . '<a class="address-card address-card--add-address" href="data:text/html,new-address" data-link-action="add-address" role="button">'
            . '<span class="address-card__add-text">Add new address</span></a></div></div>';

        private const CLASSIC_FORM = '<div class="address-form"><div class="js-address-form"><form method="POST" action="#" data-id-address="%s"><section class="form-fields">'
            . '<input type="hidden" name="id_address" value="%s">'
            . '<input class="form-control" name="alias" type="text" value="%s">'
            . '<input class="form-control" name="firstname" type="text" value="Jane" required>'
            . '<input class="form-control" name="lastname" type="text" value="%s" required>'
            . '<input class="form-control" name="address1" type="text" value="%s" required>'
            . '<input class="form-control" name="postcode" type="text" value="%s" required>'
            . '<input class="form-control" name="city" type="text" value="%s" required>'
            . '<select class="form-control form-control-select js-country" name="id_country" required><option value="8" selected>France</option><option value="21">United States</option></select>'
            . '<input class="form-control" name="phone" type="tel" value="">'
            . '</section><footer class="form-footer clearfix"><input type="hidden" name="submitAddress" value="1">'
            . '<button class="btn btn-primary form-control-submit float-xs-right" type="submit">Save</button></footer></form></div></div>';

        private const HUMMINGBIRD_FORM = '<div class="address-form"><div class="js-address-form"><form method="POST" action="#" data-id-address="%s" data-ps-action="form-validation"><section class="form-fields">'
            . '<input type="hidden" name="id_address" value="%s">'
            . '<div class="mb-3"><input class="form-control" name="alias" type="text" value="%s"></div>'
            . '<div class="mb-3"><input class="form-control" name="firstname" type="text" value="Jane" required></div>'
            . '<div class="mb-3"><input class="form-control" name="lastname" type="text" value="%s" required></div>'
            . '<div class="mb-3"><input class="form-control" name="address1" type="text" value="%s" required></div>'
            . '<div class="mb-3"><input class="form-control" name="postcode" type="text" value="%s" required></div>'
            . '<div class="mb-3"><input class="form-control" name="city" type="text" value="%s" required></div>'
            . '<div class="mb-3"><select class="form-select js-country" name="id_country" required><option value="8" selected>France</option><option value="21">United States</option></select></div>'
            . '<div class="mb-3"><input class="form-control" name="phone" type="tel" value=""></div>'
            . '</section><footer class="buttons-wrapper buttons-wrapper--end"><input type="hidden" name="submitAddress" value="1">'
            . '<button class="btn btn-primary form-control-submit" type="submit" data-ps-action="form-validation-submit">Save</button></footer></form></div></div>';

        /** Stands in for the post: the posted fields end up in the hash of the next document. */
        private const SUBMIT_SCRIPT = '<script>
            document.addEventListener("submit", function(e){
                e.preventDefault();
                var out = {};
                new FormData(e.target).forEach(function(v, k){ out[k] = v; });
                location.href = "about:blank#" + encodeURIComponent(JSON.stringify(out));
            });
        </script>';

        private function open(string $markup): void
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [1000, 800], 'noSandbox' => true]);
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            $page = self::$browser->createPage();
            $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body>' . $markup . self::SUBMIT_SCRIPT . '</body>'))->waitForNavigation();
            Addresses::$chromePage = $page;
        }

        private function list(string $theme, array $addresses): Addresses
        {
            $block = $theme === 'classic' ? self::CLASSIC_BLOCK : self::HUMMINGBIRD_BLOCK;
            $blocks = '';
            foreach ($addresses as [$id, $alias, $street, $city]) {
                $blocks .= sprintf($block, $id, $alias, $street, $city);
            }
            $this->open(sprintf($theme === 'classic' ? self::CLASSIC_LIST : self::HUMMINGBIRD_LIST, $blocks));

            return new Addresses();
        }

        private function form(string $theme, string $id = '', string $alias = '', string $lastname = '', string $street = '', string $postcode = '', string $city = ''): Address
        {
            $this->open(sprintf($theme === 'classic' ? self::CLASSIC_FORM : self::HUMMINGBIRD_FORM, $id, $id, $alias, $lastname, $street, $postcode, $city));

            return new Address();
        }

        private function posted(): array
        {
            Addresses::$chromePage->waitUntilContainsElement('body', 5000);
            $hash = (string) Addresses::$chromePage->evaluate('location.hash')->getReturnValue();

            return json_decode(rawurldecode(substr($hash, 1)), true) ?: [];
        }

        private function location(): string
        {
            return (string) Addresses::$chromePage->evaluate('location.href')->getReturnValue();
        }

        public static function themes(): array
        {
            return ['classic' => ['classic'], 'hummingbird' => ['hummingbird']];
        }

        /** @dataProvider themes */
        public function test_an_address_is_found_by_its_alias_only(string $theme): void
        {
            $book = $this->list($theme, [
                ['12', 'My Address', '16 Main street', 'Paris'],
                ['40', 'PF test 20261121-120000-abcdef', '16 Main street', 'Lyon'],
            ]);

            $this->assertSame(['12', '40'], $book->addressIds());
            $this->assertSame('40', $book->addressIdByAlias('PF test 20261121-120000-abcdef'));
            $this->assertTrue($book->hasAddress('My Address'));
            $this->assertFalse($book->hasAddress('PF test'));
            $this->assertFalse($book->hasAddress(null));
            // Line breaks of the formatted address become spaces.
            $this->assertSame('Jane Doe 16 Main street 75002 Lyon France', $book->addressText('PF test 20261121-120000-abcdef'));
            $this->assertSame('', $book->addressText('Unknown'));
            $this->assertTrue($book->hasAddressIds(['12']));
            $this->assertTrue($book->hasAddressIds([]));
            $this->assertFalse($book->hasAddressIds(['12', '13']));
        }

        /** @dataProvider themes */
        public function test_only_the_named_address_is_deleted(string $theme): void
        {
            $book = $this->list($theme, [
                ['12', 'My Address', '16 Main street', 'Paris'],
                ['40', 'PF test x', '16 Main street', 'Lyon'],
            ]);

            $book->deleteAddress('PF test x');

            $this->assertSame('data:text/html,delete-40', $this->location());
        }

        /** @dataProvider themes */
        public function test_only_the_named_address_is_edited(string $theme): void
        {
            $book = $this->list($theme, [
                ['40', 'PF test x', '16 Main street', 'Lyon'],
                ['12', 'My Address', '16 Main street', 'Paris'],
            ]);

            $book->editAddress('PF test x');

            $this->assertSame('data:text/html,edit-40', $this->location());
        }

        /** @dataProvider themes */
        public function test_the_new_address_form_opens_through_the_list(string $theme): void
        {
            $book = $this->list($theme, [['12', 'My Address', '16 Main street', 'Paris']]);

            $book->openNewAddressForm();

            $this->assertSame('data:text/html,new-address', $this->location());
        }

        /** @dataProvider themes */
        public function test_a_new_address_is_saved_with_the_preselected_country(string $theme): void
        {
            $form = $this->form($theme);

            $this->assertTrue($form->hasAddressForm());
            $form->saveAddress([
                'alias' => 'PF test x',
                'street' => '16 Main street',
                'city' => 'Paris',
                'postcode' => null,
                'country' => null,
                'state' => null,
                'phone' => '0102030405',
            ]);

            $posted = $this->posted();
            $this->assertSame('PF test x', $posted['alias']);
            // The prefilled first name stays, an empty last name is filled.
            $this->assertSame('Jane', $posted['firstname']);
            $this->assertSame('Test', $posted['lastname']);
            $this->assertSame('16 Main street', $posted['address1']);
            // France (id 8) is preselected: a French postcode.
            $this->assertSame('75002', $posted['postcode']);
            $this->assertSame('Paris', $posted['city']);
            $this->assertSame('8', $posted['id_country']);
            $this->assertSame('0102030405', $posted['phone']);
            $this->assertSame('1', $posted['submitAddress']);
        }

        /** @dataProvider themes */
        public function test_changing_the_city_keeps_the_rest_of_the_address(string $theme): void
        {
            $form = $this->form($theme, '40', 'PF test x', 'Doe', '16 Main street', '75002', 'Paris');

            $form->changeCity('Lyon');

            $posted = $this->posted();
            $this->assertSame('40', $posted['id_address']);
            $this->assertSame('PF test x', $posted['alias']);
            $this->assertSame('Doe', $posted['lastname']);
            $this->assertSame('75002', $posted['postcode']);
            $this->assertSame('Lyon', $posted['city']);
        }

        public function test_an_account_without_address_skips_the_suite(): void
        {
            $book = $this->list('hummingbird', []);

            try {
                $book->requireExistingAddress();
                $this->fail('An account without address must skip');
            } catch (SkipTestException $e) {
                $this->assertSame(
                    'The customer account has no address yet: PrestaShop would give the new address to the customer\'s cart,'
                    . ' then refuse to delete it. Give the account an address first (a checkout scenario adds one).',
                    $e->getMessage()
                );
                $this->assertTrue($e->skipRemaining);
            }

            $this->list('classic', [['12', 'My Address', '16 Main street', 'Paris']]);
            (new Addresses())->requireExistingAddress();
            $this->addToAssertionCount(1);
        }

        public function test_a_page_without_address_form_skips_the_suite(): void
        {
            $this->open('<section id="content"><h1>New address</h1></section>');

            try {
                (new Address())->requireAddressForm();
                $this->fail('A page without the address form must skip');
            } catch (SkipTestException $e) {
                $this->assertSame(
                    'This shop shows no address form on its address page'
                    . ' (the customer address book is replaced or hidden by a module or the theme).',
                    $e->getMessage()
                );
                $this->assertTrue($e->skipRemaining);
            }
        }
    }
}
