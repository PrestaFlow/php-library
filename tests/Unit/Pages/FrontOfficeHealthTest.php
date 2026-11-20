<?php

namespace PrestaFlow\Tests\Unit\Pages\HealthFixtures {

    use PrestaFlow\Library\Pages\FrontOfficePage;

    class Page extends FrontOfficePage
    {
        public function __construct()
        {
        }
    }
}

namespace PrestaFlow\Tests\Unit\Pages {

    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Support\FailedRequests;
    use PrestaFlow\Tests\Unit\Pages\HealthFixtures\Page;

    /** failedServerRequests(): only the 5xx of the running test, worded like failedRequests(). */
    final class FrontOfficeHealthTest extends TestCase
    {
        protected function setUp(): void
        {
            FailedRequests::reset();
            FailedRequests::instance()->beginTest(null);
        }

        protected function tearDown(): void
        {
            FailedRequests::instance()->endTest();
            FailedRequests::reset();
        }

        private function respond(string $id, string $method, string $url, int $status, string $type = 'XHR'): void
        {
            $requests = FailedRequests::instance();
            $requests->onRequest(['requestId' => $id, 'request' => ['url' => $url, 'method' => $method]]);
            $requests->onResponse(['requestId' => $id, 'type' => $type, 'response' => ['url' => $url, 'status' => $status]]);
        }

        public function test_only_server_errors_are_reported(): void
        {
            $this->respond('1', 'GET', 'https://shop.test/img/missing.jpg', 404, 'Image');
            $this->respond('2', 'POST', 'https://shop.test/module/x/ajax', 500);
            $this->respond('3', 'GET', 'https://shop.test/en/', 200, 'Document');
            $this->respond('4', 'GET', 'https://shop.test/api', 503);

            $this->assertSame(
                ['POST https://shop.test/module/x/ajax → 500', 'GET https://shop.test/api → 503'],
                (new Page())->failedServerRequests()
            );
        }

        public function test_nothing_failed(): void
        {
            $this->respond('1', 'GET', 'https://shop.test/img/missing.jpg', 404, 'Image');

            $this->assertSame([], (new Page())->failedServerRequests());
        }

        public function test_the_scenario_defaults_to_the_suite_locale(): void
        {
            $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\FrontOfficeHealth::class))->getDefaultProperties()['params'];

            $this->assertNull($params['locale']);
        }
    }
}
