<?php

namespace PrestaFlow\Tests\Browser\PasswordFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Password\Page as PasswordPage;

    /** The Password page on a chrome-php page handed in by the test, without a suite. */
    class Password extends PasswordPage
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

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Library\Pages\v9\FrontOffice\Password\Page;
    use PrestaFlow\Tests\Browser\PasswordFixtures\Password;

    /** The forgotten-password page, as Classic (1.7 to 9) and hummingbird render it. */
    final class PasswordResetBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /** Stands in for the post: records the address and shows the theme's confirmation (password-infos.tpl). */
        private const SCRIPT = '<script>
            document.addEventListener("submit", function(e){
                e.preventDefault();
                window.__sent = e.target.email.value;
                document.getElementById("content").innerHTML = window.__confirmation;
            });
        </script>';

        private const CLASSIC_FORM = '<form action="#" class="forgotten-password" method="post">'
            . '<ul class="ps-alert-error">%s</ul>'
            . '<header><p class="send-renew-password-link">Please enter the email address you used to register.</p></header>'
            . '<section class="form-fields"><div class="form-group center-email-fields">'
            . '<input type="email" name="email" id="email" value="" class="form-control" required>'
            . '<button id="send-reset-link" class="form-control-submit btn btn-primary hidden-xs-down" name="submit" type="submit">Send reset link</button>'
            . '<button class="form-control-submit btn btn-primary hidden-sm-up" name="submit" type="submit">Send</button>'
            . '</div></section></form>';

        private const CLASSIC_ERROR = '<li class="item"><i><svg viewBox="0 0 24 24"></svg></i><p>%s</p></li>';

        private const CLASSIC_SUCCESS = '<ul class="ps-alert-success"><li class="item"><i><svg viewBox="0 0 24 24"></svg></i>'
            . '<p>If this email address has been registered in our store, you will receive a link to reset your password at pf-ci@example.com.</p></li></ul>';

        private const HUMMINGBIRD_FORM = '<form action="#" class="forgotten-password" method="post" data-ps-action="form-validation">%s'
            . '<header><p class="send-renew-password-link">Please enter the email address you used to register.</p></header>'
            . '<section class="form-fields"><div class="mb-3"><label for="email" class="form-label required">Email address</label>'
            . '<input type="email" name="email" id="email" value="" class="form-control" autocomplete="username" required></div>'
            . '<div class="buttons-wrapper"><button id="send-reset-link" class="btn btn-primary" name="submit" type="submit"'
            . ' data-ps-action="form-validation-submit">Send reset link</button></div></section></form>';

        private const HUMMINGBIRD_ERROR = '<div class="alert alert-danger" role="alert"><ul class="mb-0"><li>%s</li></ul></div>';

        private const HUMMINGBIRD_SUCCESS = '<div class="alert alert-success" role="alert" tabindex="0"><ul class="mb-0">'
            . '<li>If this email address has been registered in our store, you will receive a link to reset your password at pf-ci@example.com.</li></ul></div>';

        private function password(string $content, string $confirmation = ''): Password
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
            $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body><section id="content">' . $content . '</section>'
                . '<script>window.__confirmation = ' . json_encode($confirmation) . ';</script>' . self::SCRIPT . '</body>'))->waitForNavigation();
            Password::$chromePage = $page;

            return new Password();
        }

        public static function themes(): array
        {
            return [
                'classic' => [sprintf(self::CLASSIC_FORM, ''), self::CLASSIC_SUCCESS],
                'hummingbird' => [sprintf(self::HUMMINGBIRD_FORM, ''), self::HUMMINGBIRD_SUCCESS],
            ];
        }

        /** @dataProvider themes */
        public function test_a_reset_link_is_requested_and_confirmed(string $form, string $confirmation): void
        {
            $password = $this->password($form, $confirmation);

            $this->assertTrue($password->hasPasswordForm());
            $this->assertSame(Page::OUTCOME_NONE, $password->getResetOutcome());

            $this->assertSame('pf-ci@example.com', $password->requestResetLink(' pf-ci@example.com '));

            $this->assertSame('pf-ci@example.com', Password::$chromePage->evaluate('window.__sent')->getReturnValue());
            $this->assertSame(Page::OUTCOME_SENT, $password->getResetOutcome());
            $this->assertTrue($password->hasResetConfirmation());
            $this->assertSame('', $password->getUnexpectedResetError());
        }

        public static function throttles(): array
        {
            return [
                'classic, english' => [sprintf(self::CLASSIC_FORM, sprintf(self::CLASSIC_ERROR, 'You can regenerate your password only every 360 minute(s)'))],
                'hummingbird, french' => [sprintf(self::HUMMINGBIRD_FORM, sprintf(self::HUMMINGBIRD_ERROR, 'Vous ne pouvez régénérer votre mot de passe que toutes les 360 minute(s)'))],
            ];
        }

        /** @dataProvider throttles */
        public function test_the_throttle_error_counts_as_handled_whatever_the_language(string $markup): void
        {
            $password = $this->password($markup);

            $this->assertSame(Page::OUTCOME_THROTTLED, $password->getResetOutcome());
            $this->assertTrue($password->hasResetConfirmation());
            $this->assertSame('', $password->getUnexpectedResetError());
        }

        public static function errors(): array
        {
            return [
                'classic' => [sprintf(self::CLASSIC_FORM, sprintf(self::CLASSIC_ERROR, 'An error occurred while sending the email.'))],
                'hummingbird' => [sprintf(self::HUMMINGBIRD_FORM, sprintf(self::HUMMINGBIRD_ERROR, 'Une erreur est survenue lors de l\'envoi de l\'e-mail.'))],
            ];
        }

        /** @dataProvider errors */
        public function test_another_error_fails_with_the_shops_message(string $markup): void
        {
            $password = $this->password($markup);

            $this->assertSame(Page::OUTCOME_ERROR, $password->getResetOutcome());
            $this->assertFalse($password->hasResetConfirmation());
            $this->assertMatchesRegularExpression('/^(An error occurred while sending the email\.|Une erreur est survenue lors de l\'envoi de l\'e-mail\.)$/', $password->getUnexpectedResetError());
        }

        public function test_a_page_with_no_answer_is_not_a_confirmation(): void
        {
            $password = $this->password(sprintf(self::CLASSIC_FORM, ''));

            $this->assertFalse($password->hasResetConfirmation());
            $this->assertSame('The shop answered neither a confirmation nor an error.', $password->getUnexpectedResetError());
        }

        public function test_no_password_form_skips_the_suite(): void
        {
            $password = $this->password('<h1>Page not found</h1>');

            try {
                $password->requirePasswordForm();
                $this->fail('A page without the forgotten-password form must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('This shop has no forgotten-password form on its password recovery page.', $e->getMessage());
                $this->assertTrue($e->skipRemaining);
            }
        }

        public function test_the_account_defaults_to_fo_email_and_skips_without_one(): void
        {
            $password = $this->password(sprintf(self::CLASSIC_FORM, ''));

            try {
                $password->requireAccountEmail(null);
                $this->fail('No account address must skip');
            } catch (SkipTestException $e) {
                $this->assertSame(
                    'No customer account configured: set PRESTAFLOW_FO_EMAIL (the project\'s customer account), or the email param.',
                    $e->getMessage()
                );
                $this->assertTrue($e->skipRemaining);
            }

            $password->setGlobal('FO_EMAIL', 'pf-ci@example.com');
            $this->assertSame('pf-ci@example.com', $password->accountEmail(null));
            $this->assertSame('other@example.com', $password->accountEmail('other@example.com'));
        }
    }
}
