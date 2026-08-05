@cancelling_hipay_payment
Feature: Cancelling a HiPay payment via the cancel URL
    In order to handle payment cancellation or refusal from HiPay
    As a Customer
    I want to be redirected properly and informed when my payment is cancelled

    @ui
    Scenario: Visiting the cancel URL for an unknown order returns a 404
        When I visit the HiPay cancel URL for token "unknown-token-404"
        Then the response should be a 404

    @ui
    Scenario: Visiting the cancel URL when no payment is processing redirects to the order show page
        Given there is an order with token "order-no-processing"
        When I visit the HiPay cancel URL for token "order-no-processing"
        Then I should be redirected to the order show page for "order-no-processing"
        And no payment should have been cancelled

    @ui
    Scenario: Visiting the cancel URL cancels the processing payment and redirects
        Given there is an order with token "order-processing" and a payment in processing state
        When I visit the HiPay cancel URL for token "order-processing"
        Then the payment for order "order-processing" should be cancelled
        And I should be redirected to the order show page for "order-processing"
