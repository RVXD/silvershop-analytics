<?php

declare(strict_types=1);

namespace SilverShop\Analytics\Extension;

use SilverShop\Analytics\Model\ProductView;
use SilverShop\Page\Product;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;

/**
 * Records an anonymous product-page view for the most-viewed report.
 *
 * Fires on the product's own GET request only — not form posts, ajax calls, or sub-actions (Form, AddProductForm).
 * Stores no raw PII: unique-viewer counts come from a hashed, anonymous per-session token, and the member link is
 * off unless track_members is enabled. When respect_consent is on, nothing is recorded until a consent flag is set
 * in the session, so the module can sit behind a cookie-consent banner.
 *
 * @extends Extension<\SilverShop\Page\ProductController>
 */
class ProductViewTrackingExtension extends Extension
{
    private static bool $enabled = true;

    private static bool $track_members = false;

    private static bool $respect_consent = false;

    private static string $consent_session_key = 'analytics_consent';

    private static string $visitor_session_key = 'SilverShop.AnalyticsVisitor';

    public function onAfterInit(): void
    {
        $request = $this->owner->getRequest();
        if (!$request instanceof HTTPRequest || !$this->shouldTrack($request)) {
            return;
        }

        $record = $this->owner->data();
        if (!$record instanceof Product || !$record->exists()) {
            return;
        }

        ProductView::record($record->ID, $this->visitorHash($request), $this->trackedMemberID());
    }

    /**
     * Whether this request is a genuine product-page view that should be counted.
     */
    public function shouldTrack(HTTPRequest $request): bool
    {
        if (!$this->setting('enabled')) {
            return false;
        }
        if (!$request->isGET() || $request->isAjax()) {
            return false;
        }
        // Only the product page itself, not sub-actions such as Form / AddProductForm.
        if ($request->param('Action')) {
            return false;
        }
        if ($this->setting('respect_consent') && !$this->hasConsent($request)) {
            return false;
        }

        return true;
    }

    /**
     * Read one of this extension's own config values.
     */
    private function setting(string $name): mixed
    {
        return Config::forClass(static::class)->get($name);
    }

    private function hasConsent(HTTPRequest $request): bool
    {
        return (bool) $request->getSession()->get($this->setting('consent_session_key'));
    }

    /**
     * A one-way hash of an anonymous per-session token (minted on first view). Never an IP or login — just a
     * best-effort way to count distinct sessions for the unique-viewers column.
     */
    private function visitorHash(HTTPRequest $request): ?string
    {
        $session = $request->getSession();
        $key = $this->setting('visitor_session_key');

        $token = $session->get($key);
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $session->set($key, $token);
        }

        return sha1((string) $token);
    }

    private function trackedMemberID(): int
    {
        if (!$this->setting('track_members')) {
            return 0;
        }

        $member = Security::getCurrentUser();

        return $member ? (int) $member->ID : 0;
    }
}
