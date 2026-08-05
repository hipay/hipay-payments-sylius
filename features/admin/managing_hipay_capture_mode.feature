@managing_hipay_payment_method_configuration
Feature: Configuring the HiPay capture mode
    In order to either charge customers immediately or only authorise and capture later
    As an Administrator
    I want to configure the capture mode of HiPay payment methods

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator
        And there is a HiPay account "Behat Test Account" with code "behat-hipay-account"

    @ui
    Scenario: Switching a HiPay Hosted Fields payment method to instant capture
        Given there is a HiPay Hosted Fields payment method named "Card payment" with code "hipay-card-instant" using account "behat-hipay-account" in "on-demand" capture mode
        When I want to modify the "Card payment" payment method
        And I set the capture mode to "instant"
        And I save my changes
        And I want to modify the "Card payment" payment method
        Then the capture mode should be "instant"

    @ui
    Scenario: Switching a HiPay Hosted Fields payment method to on-demand capture
        Given there is a HiPay Hosted Fields payment method named "Card payment" with code "hipay-card-on-demand" using account "behat-hipay-account" in "instant" capture mode
        When I want to modify the "Card payment" payment method
        And I set the capture mode to "on-demand"
        And I save my changes
        And I want to modify the "Card payment" payment method
        Then the capture mode should be "on-demand"

    @ui
    Scenario: Switching a HiPay Hosted Page payment method to instant capture
        Given there is a HiPay Hosted Page payment method named "Hosted page payment" with code "hipay-hp-instant" using account "behat-hipay-account" in "on-demand" capture mode
        When I want to modify the "Hosted page payment" payment method
        And I set the capture mode to "instant"
        And I save my changes
        And I want to modify the "Hosted page payment" payment method
        Then the capture mode should be "instant"

    @ui
    Scenario: Switching a HiPay Hosted Page payment method to on-demand capture
        Given there is a HiPay Hosted Page payment method named "Hosted page payment" with code "hipay-hp-on-demand" using account "behat-hipay-account" in "instant" capture mode
        When I want to modify the "Hosted page payment" payment method
        And I set the capture mode to "on-demand"
        And I save my changes
        And I want to modify the "Hosted page payment" payment method
        Then the capture mode should be "on-demand"
