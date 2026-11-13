<?php

namespace App\Support;

/**
 * Strings the storefront's JavaScript shows, sent to the page as window.UDS_T so scripts
 * can call udsT('English text') and get the visitor's language. English pages get nothing:
 * udsT() falls back to the English it was given.
 */
class JsStrings
{
    public const KEYS = [
        // checkout
        'Tubing and driveshafts ship at the carrier rate and do not qualify for free shipping.',
        'Free US ground shipping applied.', 'Invalid coupon',
        'Enter your ZIP to load rates…', 'Enter your postal code to load rates…', 'Free', 'Loading rates…',
        'Loading live UPS & FedEx rates…', 'No rates for this address', 'No carrier rates for this address', 'Choose a method…',
        'UPS and FedEx cannot deliver to a PO box. Enter a street address, or choose local pickup.',
        'We could not get UPS or FedEx rates for that address. Check the ZIP code and state, or call (813) 524-6491.',
        'Freight rates from FedEx Freight, shipping from Florida. Delivery dates are estimates.',
        'Rates shown ship from Florida. Delivery dates are estimates.', 'Could not load rates. Call (813) 524-6491.',
        'Pick up at', 'Shipping From', 'Pickup hours', 'Est. Ship Date', 'Other times: call :phone first', 'Orders before 2pm EST ship same day',
        'Please choose a shipping method.', 'Placing order…', 'Please check your payment details.', 'Place Order', 'Place order',
        'Network error. Please try again.',
        // cart
        ':amount more for free US ground shipping.',
        // delivery date (js/arrival.js)
        'Arrives :date', 'to :zip with :service', 'Change', 'Get a delivery date', 'ZIP code', 'Show date', 'Checking the delivery date…',
        'Enter a 5-digit US ZIP code.', 'Custom builds ship when they are finished.', 'Delivery date for ZIP',
        'Use my location', 'Enter ZIP', 'Delivery date:', 'near you', 'Could not get your location. Enter your ZIP code.', 'Finding your ZIP…',
        'Shipping to :zip with :service', 'Shipping to :zip',   // cart summary: the shipping cost for that ZIP
        // home vehicle finder, sidebar
        'Pick a make first', 'Pick a year first', 'Choose a make', 'See driveshafts for :vehicle', 'See :make driveshafts', 'Expand menu', 'Collapse menu', 'Part not found.', 'Yes', 'No',
        // search overlay
        'Call for price', 'Part number, name or series — 5-1310X, flange yoke, U2594-173', 'Search parts', 'Clear search', 'Close search', 'Close',
        'Recent', 'Clear', 'Popular',
        'Type a part number, a series like <b>1310</b> or a name. Press <kbd>Enter</kbd> for the full results page, <kbd>↑</kbd><kbd>↓</kbd> to move, <kbd>Esc</kbd> to close.',
        'Searching…', 'Search failed — try again.',
        '<b>No parts match “:q”.</b> Check the number against the one stamped on the part, try the series only, or <a href="/catalog/driveshafts">find a complete driveshaft by vehicle</a>.',
        'Showing results for :q', 'Left out, no part mentions it: :words', 'Search', 'Add',
        // every Add to cart button (uds.js udsAdd)
        'Added', 'Could not add to cart. Please try again.', 'Session expired — reload the page and try again.', 'Too many tries — wait a minute and try again.',
        'All :n', 'See all results', 'Open category', 'Replaces', 'Add :sku to cart', 'Add to cart', 'See :n result for “:q”', 'See all :n results for “:q”',
        // phone field
        'Search country', 'Number without the country code', ', change country',
        // assistant
        'In stock', 'Out', 'Parts', 'Hi :name, how can I help?', 'Hi, how can I help?',
        'Ask about parts, fitment, shipping or your orders. Prices and stock come live from our catalog.',
        'Find a 1310 u-joint', 'Driveshaft for my truck', 'Where is my order?', 'How does freight shipping work?', 'Assistant is typing',
        'The assistant is not available right now. Call (813) 524-6491 or use the contact page.', 'Please keep it under :max characters.',
        'Your session expired — reload the page.', 'Slow down a little — try again in a moment.', 'Something went wrong. Try again.',
        'Start a new chat', 'Talk to a person', 'Someone from our shop will reply by e-mail, usually the same business day.',
        'E-mail', 'Name', 'Phone', '(optional)', 'What do you need?', 'Part number, vehicle, measurements, quantity…', 'Cancel', 'Send to the shop',
        'Sending…', 'Could not send. Check the form and try again.', "Sent. We'll reply to <b>:email</b>.", 'Could not reach the shop. Try again.',
        'Could not reach the assistant. Check your connection.',
        'Remove photo', 'That photo is over 12 MB.', "We couldn't read that photo. Try a JPG or PNG.", 'You are chatting with :name from Universal Driveshafts.', 'someone',
        // home vehicle bar (the finder's facet labels)
        'Choose :what (:n)', 'Make', 'Year', 'Model',
        // quick order
        'Not found', 'Pick a match', 'Pick at least one valid part from the suggestions.', 'Added :n item(s) to your cart.', 'Go to cart', 'e.g. 5-1310X or “flange yoke”', 'Remove',
    ];

    /** Extra keys other storefront areas register for their scripts. */
    private static array $extra = [];

    public static function add(array $keys): void
    {
        self::$extra = array_merge(self::$extra, $keys);
    }

    public static function dict(): array
    {
        if (app()->getLocale() === 'en') return [];
        $out = [];
        foreach (array_unique(array_merge(self::KEYS, self::$extra)) as $k) {
            $v = __($k);
            if ($v !== $k) $out[$k] = $v;
        }
        return $out;
    }
}
