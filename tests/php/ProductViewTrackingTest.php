<?php

namespace SilverShop\Analytics\Tests;

use SilverShop\Analytics\Extension\ProductViewTrackingExtension;
use SilverShop\Analytics\Model\ProductView;
use SilverShop\Page\ProductController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class ProductViewTrackingTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function request(string $method = 'GET', array $routeParams = [], bool $ajax = false): HTTPRequest
    {
        $request = new HTTPRequest($method, '/shop/some-product/');
        $request->setSession(new Session([]));
        if ($routeParams) {
            $request->setRouteParams($routeParams);
        }
        if ($ajax) {
            $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        }
        return $request;
    }

    private function controller(): ProductController
    {
        return ProductController::create();
    }

    public function testCountsPlainProductPageGet(): void
    {
        $this->assertTrue($this->controller()->shouldTrack($this->request()));
    }

    public function testIgnoresPosts(): void
    {
        $this->assertFalse($this->controller()->shouldTrack($this->request('POST')));
    }

    public function testIgnoresSubActions(): void
    {
        $this->assertFalse($this->controller()->shouldTrack($this->request('GET', ['Action' => 'Form'])));
    }

    public function testIgnoresAjax(): void
    {
        $this->assertFalse($this->controller()->shouldTrack($this->request('GET', [], true)));
    }

    public function testRespectsEnabledFlag(): void
    {
        Config::modify()->set(ProductViewTrackingExtension::class, 'enabled', false);
        $this->assertFalse($this->controller()->shouldTrack($this->request()));
    }

    public function testConsentGateBlocksUntilFlagSet(): void
    {
        Config::modify()->set(ProductViewTrackingExtension::class, 'respect_consent', true);

        $withoutConsent = $this->request();
        $this->assertFalse($this->controller()->shouldTrack($withoutConsent), 'blocked without consent');

        $withConsent = $this->request();
        $withConsent->getSession()->set('analytics_consent', true);
        $this->assertTrue($this->controller()->shouldTrack($withConsent), 'allowed once consent is given');
    }

    public function testRecordWritesARow(): void
    {
        $view = ProductView::record(123, 'hash-123', 0);

        $this->assertNotNull($view);
        $this->assertSame(1, ProductView::get()->count());
        $this->assertSame(123, (int) ProductView::get()->first()->ProductID);
    }

    public function testRecordIgnoresEmptyProduct(): void
    {
        $this->assertNull(ProductView::record(0));
        $this->assertSame(0, ProductView::get()->count());
    }
}
