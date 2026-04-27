@managing_oney_shipping_methods
Feature: Managing Oney shipping methods
    In order to map Sylius shipping methods to Oney standard shipping methods for payments
    As an Administrator
    I want to be able to manage Oney shipping methods

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator
        And the store allows shipping with "Oney Ship A" identified by "oney_a"
        And the store allows shipping with "Oney Ship B" identified by "oney_b"
        And there is an Oney shipping method for shipping method with code "oney_a" with Oney standard shipping method CarrierStandard

    @ui
    Scenario: Browsing Oney shipping methods
        When I browse Oney shipping methods
        Then I should see 1 Oney shipping method in the list
        And I should see an Oney shipping method for shipping method with code "oney_a" in the list

    @ui
    Scenario: Only unmapped shipping methods are available when creating an Oney shipping method
        When I want to create a new Oney shipping method
        Then the Sylius shipping method field should only list shipping method "Oney Ship B"

    @ui
    Scenario: Creating a new Oney shipping method
        When I want to create a new Oney shipping method
        And I select shipping method "Oney Ship B" for the Oney shipping method
        And I select Oney standard shipping method CarrierExpress
        And I set Oney preparation time to 1
        And I set Oney delivery time to 2
        And I add it
        Then the Oney shipping method for shipping method with code "oney_b" should appear in the list

    @ui
    Scenario: Creating an Oney shipping method without required fields
        When I want to create a new Oney shipping method
        And I add it
        Then I should be notified that the form contains errors

    @ui
    Scenario: Updating an existing Oney shipping method
        Given there is an Oney shipping method for shipping method with code "oney_b" with Oney standard shipping method CarrierExpress
        When I want to modify the Oney shipping method for shipping method with code "oney_a"
        And I select Oney standard shipping method CarrierPriority24h
        And I save my changes
        Then the Oney shipping method for shipping method with code "oney_a" should have Oney standard shipping method CarrierPriority24h

    @ui
    Scenario: Deleting an Oney shipping method
        When I browse Oney shipping methods
        And I delete the Oney shipping method for shipping method with code "oney_a"
        Then I should be notified that it has been successfully deleted
        When I browse Oney shipping methods
        Then there should be no Oney shipping method for shipping method with code "oney_a"
