@managing_hipay_payment_method_configuration
Feature: Managing HiPay Hosted Fields payment method configuration
    In order to accept card payments via HiPay Hosted Fields
    As an Administrator
    I want to configure and update HiPay Hosted Fields payment methods

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator
        And there is a HiPay account "Behat Test Account" with code "behat-hipay-account"

  @ui
  Scenario: Seeing a HiPay Hosted Fields payment method in the list
    Given there is a HiPay Hosted Fields payment method named "HiPay Card" with code "hipay-card-list" using account "behat-hipay-account"
    When I browse payment methods
    Then I should see the payment method "HiPay Card" in the list

  @ui
  Scenario: Updating a HiPay payment method preserves saved values
        Given there is a HiPay Hosted Fields payment method named "Card payment" with code "hipay-card-update" using account "behat-hipay-account"
        When I want to modify the "Card payment" payment method
        And I set gateway configuration text color to "#FF0000"
        And I save my changes
        And I want to modify the "Card payment" payment method
        Then the gateway configuration text color should be "#FF0000"
