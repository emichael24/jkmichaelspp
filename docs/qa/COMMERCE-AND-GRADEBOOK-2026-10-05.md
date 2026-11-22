# Commerce recovery and gradebook boundaries — 5 October 2026

Project: JK Michaels, `C:\JK Michaels app`. This record supplements the A01–A17 audit; it is not an enterprise-release approval.

## Implemented contracts

- Checkout shows only configured providers. Unpublished course products, bundles containing unpublished courses and empty bundles are unavailable. Product payloads expose display metadata only. Individual checkout buys access for one account; it does not silently sell unimplemented organization seats.
- Orders and payment references commit before provider initialization. A user-row lock serializes duplicate matching-cart submissions; recent matching pending orders are reused. Provider initialization has bounded connection/request timeouts. Existing checkout URLs and references are reused, and uncertain failures preserve the order.
- Product-specific coupons apply only to eligible lines. Coupon allocation locks prevent exceeding the recorded limit; zero usage limits reject and discounts cannot make totals negative. Allocation currently remains reserved with its pending order; automated expiration/release is not implemented.
- Zero-value orders fulfill without a provider request. Verified status refresh checks amount, currency and reference. Provider outages/mismatches are not treated as failed payments. Successful receipts retain only settlement fields, not reusable payment authorizations or customer/card payloads.
- The legacy initialization endpoint uses the canonical payment reference. Historical legacy callback records remain supported without storing entire provider responses. Orders lacking a recoverable canonical checkout require reconciliation instead of a new reference being fabricated.
- Full Paystack refunds have one durable intent per payment, an encrypted internal reason, administrator confirmation, provider identity/amount/currency verification and a monotonic settlement state. An uncertain POST is never automatically resent. A scheduler and signed webhook handler reconcile the existing refund. Failed/needs-attention/ambiguous outcomes require provider review; partial refunds and bank-detail collection are outside this implementation.
- Only verified `processed` refunds mark the payment/order refunded. New order-sourced course access then stops; independently granted and historical enrollments remain unchanged. Earned completion dates/progress/certificates are retained. Repurchase can restore a refunded grant without overriding an administrator suspension. Historical entitlement provenance requires review.
- The workspace displays explicit test-payment mode when a Paystack test key is configured. The storefront no longer advertises an unsupported money-back guarantee.
- Gradebook queries retain course constraints even with zero quizzes/mock exams, exclude stale members from another organization, reject unrelated facilitators/revoked organizations and resolve tied completion timestamps by attempt ID.

## Automated and browser evidence

`CheckoutRecoveryTest` covers initialization failure/recovery, duplicate cart submissions and legacy entry-point reuse, uncertain/mismatched verification, single fulfillment, product-specific coupon capacity, unavailable providers, one-account quantities, zero-value orders, ownership/revocation and draft catalog boundaries. `PaymentRefundTest` covers duplicate and uncertain requests, reconciled receipts, preserved earned records, unrelated independent grants, mismatched amounts and administrator confirmation. `GradebookBoundaryTest` uses two organizations and unrelated course attempts.

The combined counts and archive hash are recorded in [verification](VERIFICATION.md). Browser checks used populated synthetic fixtures and a non-delivering mail transport:

- [Refund review dialog](billing-refund-review.png): labelled reason, explicit financial confirmation and disabled submit until confirmed. The browser did not submit this synthetic refund.
- [Pending order recovery](order-payment-recovery.png): saved-order summary and explicit check/resume controls. Browser did not initiate an additional provider checkout.

## Real provider test evidence

The existing simulated NGN 100 charge was verified against Paystack's real test API from a separate local database. The application subscription service confirmed it idempotently, linked the provider subscription and canceled renewal. The provider returned `non-renewing`; paid-period access remained until its recorded end. This was not a new live charge.

One full refund request was submitted through `PaymentRefundService`. The provider first returned **pending**. At **11:16:38 UTC**, reconciliation fetched **processed** and the application payment changed to **refunded**. This is verified sandbox settlement, not movement of real funds. Repeated checks reuse the same refund record. The redacted [provider evidence](commerce-provider-evidence.json) excludes keys, tokens, card authorization data and customer details.

Provider contracts were checked against [Paystack refunds](https://paystack.com/docs/api/refund/) and [refund states/webhooks](https://paystack.com/docs/payments/refunds/). Pending or processing is not settled. The local provider-link event was supplied to the application service and reconciled against the provider API; it was **not** an inbound webhook delivery from Paystack. Actual deployed callback/webhook delivery, a recurring invoice/renewal cycle, recurring-refund policy, tax/invoice policy and other provider sandboxes remain release acceptance work.

## Production boundary

No application/database cutover was performed by this continuation. The isolated production-copy SQL/HTTP/queue/scanner checks passed separately. Test mode does not authorize live billing. Historical completion classification remains pending, organization SSO and independent Contabo backup verification remain user-deferred, and the main audit continues to list unresolved gates.
