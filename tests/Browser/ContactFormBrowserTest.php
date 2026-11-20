<?php

namespace PrestaFlow\Tests\Browser\ContactFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Contact\Page as ContactPage;

    /** The Contact page on a chrome-php page handed in by the test, without a suite. */
    class Contact extends ContactPage
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

namespace PrestaFlow\Tests\Browser\ContactFixtures {

    use PrestaFlow\Library\Pages\v9\BackOffice\CustomerService\Page as CustomerServicePage;

    class CustomerService extends CustomerServicePage
    {
        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return Contact::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\ContactFixtures\Contact;
    use PrestaFlow\Tests\Browser\ContactFixtures\CustomerService;

    /** The contactform module's form, as Classic (1.7 to 9) and hummingbird render it. */
    final class ContactFormBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /** Stands in for the post: records what was sent and shows the module's success alert. */
        private const SCRIPT = '<script>
            document.addEventListener("submit", function(e){
                e.preventDefault();
                var f = e.target;
                window.__sent = {id_contact: f.id_contact.value, from: f.from.value, message: f.message.value,
                    gdpr: !!(f.querySelector("[required][type=checkbox]") || {}).checked};
                document.querySelector(".contact-form").innerHTML = "<div class=\"alert alert-success\">OK</div>";
            });
        </script>';

        private const CLASSIC = '<section class="contact-form"><form action="#" method="post"><section class="form-fields">'
            . '<select name="id_contact" id="id_contact"><option value="2">Customer service</option><option value="1">Webmaster</option></select>'
            . '<input id="email" name="from" type="email" value="%s">'
            . '<textarea id="contactform-message" name="message"></textarea>'
            . '<input type="checkbox" name="psgdpr_consent_checkbox" value="1" required>'
            . '</section><footer class="form-footer"><input type="text" name="url" value="" style="display:none">'
            . '<input class="btn btn-primary" type="submit" name="submitMessage" value="Send"></footer></form></section>';

        private const HUMMINGBIRD = '<section class="contact-form"><form class="form-validation" action="#" method="post"><section class="form-fields">'
            . '<select id="contact-us-subject-select" name="id_contact" class="form-select"><option value="2">Customer service</option></select>'
            . '<input id="contact-us-email-input" class="form-control" name="from" type="email" value="%s">'
            . '<textarea id="contact-us-message-textarea" class="form-control" name="message"></textarea>'
            . '<input type="checkbox" name="psgdpr_consent_checkbox" value="1" required>'
            . '</section><footer class="buttons-wrapper"><input type="hidden" name="url" value="">'
            . '<button class="btn btn-primary" type="submit" name="submitMessage">Send</button></footer></form></section>';

        private function contact(string $markup): Contact
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            $page = self::$browser->createPage();
            $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body>' . $markup . self::SCRIPT . '</body>'))->waitForNavigation();
            Contact::$chromePage = $page;

            return new Contact();
        }

        public static function themes(): array
        {
            return ['classic' => [self::CLASSIC], 'hummingbird' => [self::HUMMINGBIRD]];
        }

        /** @dataProvider themes */
        public function test_a_guest_message_is_sent_with_the_given_address(string $markup): void
        {
            $contact = $this->contact(sprintf($markup, ''));

            $this->assertTrue($contact->hasContactForm());
            $from = $contact->sendMessage('pf-contact-x@example.com', 'PrestaFlow test message', null);

            $this->assertSame('pf-contact-x@example.com', $from);
            $sent = Contact::$chromePage->evaluate('JSON.stringify(window.__sent)')->getReturnValue();
            $this->assertSame(
                ['id_contact' => '2', 'from' => 'pf-contact-x@example.com', 'message' => 'PrestaFlow test message', 'gdpr' => true],
                json_decode($sent, true)
            );
            $this->assertTrue($contact->hasSentConfirmation());
        }

        public function test_a_prefilled_address_is_kept_and_a_subject_can_be_chosen(): void
        {
            $contact = $this->contact(sprintf(self::CLASSIC, 'customer@shop.test'));

            $this->assertSame('customer@shop.test', $contact->sendMessage('pf-contact-x@example.com', 'Hello', '1'));
            $sent = json_decode(Contact::$chromePage->evaluate('JSON.stringify(window.__sent)')->getReturnValue(), true);
            $this->assertSame('1', $sent['id_contact']);
            $this->assertSame('customer@shop.test', $sent['from']);
        }

        public function test_no_contact_form_skips_the_suite(): void
        {
            $contact = $this->contact('<div id="content"><h1>Contact us</h1></div>');

            try {
                $contact->requireContactForm();
                $this->fail('A page without the contact form must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('This shop has no contact form on its contact page (the contactform module is missing or not shown).', $e->getMessage());
                $this->assertTrue($e->skipRemaining);
            }
        }

        public function test_an_error_alert_is_not_a_confirmation(): void
        {
            $contact = $this->contact('<section class="contact-form"><div class="alert alert-danger">Invalid email</div></section>');

            $this->assertFalse($contact->hasSentConfirmation());
        }

        public function test_the_customer_service_list_finds_a_thread_by_email(): void
        {
            $this->contact('<table class="table customer_thread" id="table-customer_thread"><tbody>'
                . '<tr><td>12</td><td>--</td><td>PF-Contact-X@example.com</td><td>PrestaFlow test message 2026...</td></tr>'
                . '<tr><td>11</td><td>John DOE</td><td>pub@prestashop.com</td><td>Hello</td></tr></tbody></table>');
            $list = new CustomerService();

            $this->assertTrue($list->hasThreadFrom('pf-contact-x@example.com'));
            $this->assertFalse($list->hasThreadFrom('someone@else.test'));
        }
    }
}
