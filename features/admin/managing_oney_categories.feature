@managing_oney_categories
Feature: Managing Oney categories
    In order to map Sylius taxons to Oney standard categories for payments
    As an Administrator
    I want to be able to manage Oney categories

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator
        And the store classifies its products as "Oney Root"
        And the "Oney Root" taxon has child taxon "Oney A"
        And the "Oney Root" taxon has child taxon "Oney B"
        And there is an Oney category for taxon "Oney A" with Oney standard category ClothingAndAccessories

    @ui
    Scenario: Browsing Oney categories
        When I browse Oney categories
        Then I should see 1 Oney category in the list
        And I should see an Oney category for taxon with code "oney_a" in the list

    @ui
    Scenario: Only unmapped taxons are available when creating an Oney category
        When I want to create a new Oney category
        Then the Sylius category field should only list taxon "Oney B"

    @ui
    Scenario: Creating a new Oney category
        When I want to create a new Oney category
        And I select taxon "Oney B" for the Oney category
        And I select Oney standard category GiftsAndFlowers
        And I add it
        Then the Oney category for taxon with code "oney_b" should appear in the list

    @ui
    Scenario: Creating an Oney category without required fields
        When I want to create a new Oney category
        And I add it
        Then I should be notified that the form contains errors

    @ui
    Scenario: Updating an existing Oney category
        Given there is an Oney category for taxon "Oney B" with Oney standard category GiftsAndFlowers
        When I want to modify the Oney category for taxon with code "oney_a"
        And I select Oney standard category HealthAndBeauty
        And I save my changes
        Then the Oney category for taxon with code "oney_a" should have Oney standard category HealthAndBeauty

    @ui
    Scenario: Deleting an Oney category
        When I browse Oney categories
        And I delete the Oney category for taxon with code "oney_a"
        Then I should be notified that it has been successfully deleted
        When I browse Oney categories
        Then there should be no Oney category for taxon with code "oney_a"
