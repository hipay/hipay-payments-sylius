@managing_hipay_accounts
Feature: Managing HiPay accounts
    In order to configure HiPay payment credentials
    As an Administrator
    I want to be able to manage HiPay accounts

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Browsing HiPay accounts
        Given there is a HiPay account "Test Account" with code "test-account"
        When I browse HiPay accounts
        Then I should see 1 HiPay account in the list
        And I should see the HiPay account "Test Account" in the list

    @ui
    Scenario: Creating a new HiPay account
        When I want to create a new HiPay account
        And I name it "Production Account"
        And I specify its code as "production-account"
        And I set its environment to "test"
        And I set the API username to "prod_user"
        And I set the API password to "prod_pass"
        And I set the secret passphrase to "prod_secret"
        And I set the test API username to "test_user"
        And I set the test API password to "test_pass"
        And I set the test secret passphrase to "test_secret"
        And I set the test API public username to "test_public_user"
        And I set the test API public password to "test_public_pass"
        And I add it
        Then the HiPay account with code "production-account" should appear in the list

    @ui
    Scenario: Creating a HiPay account without required fields
        When I want to create a new HiPay account
        And I add it
        Then I should be notified that the form contains errors

    @ui
    Scenario: Updating an existing HiPay account
        Given there is a HiPay account "Old Name" with code "update-test"
        When I want to modify the HiPay account "Old Name"
        And I rename it to "New Name"
        And I save my changes
        Then the HiPay account with code "update-test" should appear in the list

    @ui
    Scenario: Code cannot be changed on existing account
        Given there is a HiPay account "Locked Code" with code "locked-code"
        When I want to modify the HiPay account "Locked Code"
        Then the code field should be disabled

    @ui
    Scenario: Deleting a HiPay account
        Given there is a HiPay account "To Delete" with code "delete-me"
        When I browse HiPay accounts
        And I delete the HiPay account "To Delete"
        Then I should be notified that it has been successfully deleted
        When I browse HiPay accounts
        Then the HiPay account "To Delete" should no longer exist in the list
