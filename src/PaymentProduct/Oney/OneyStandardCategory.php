<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Oney;

/** Oney payment category identifiers (official numeric codes for merchant integrations). */
enum OneyStandardCategory: int
{
    case HomeAndGardening = 1;
    case ClothingAndAccessories = 2;
    case HomeAppliances = 3;
    case SportsAndRecreations = 4;
    case BabiesAndChildren = 5;
    case HiFiPhotoVideoEquipment = 6;
    case ItEquipment = 7;
    case PhoneAndInternetServices = 8;
    case PhysicalGoodsBooksMediaMusicMovies = 9;
    case DigitalGoodsBooksMediaMusicMovies = 10;
    case ConsolesAndVideoGames = 11;
    case GiftsAndFlowers = 12;
    case HealthAndBeauty = 13;
    case CarAndMotorcycle = 14;
    case Traveling = 15;
    case FoodAndGastronomy = 16;
    case AuctionsAndGroupBuying = 17;
    case ServicesToProfessionals = 18;
    case ServicesToIndividuals = 19;
    case CultureAndEntertainment = 20;
    case GamesDigitalGoods = 21;
    case GamesPhysicalGoods = 22;
    case Ticketing = 23;
    case OpticiansGoodsAndEyeglasses = 24;

    /** Translation key for admin forms and grid filters (avoids closures in Symfony PHP config). */
    public static function choiceTranslationKey(self $case): string
    {
        return sprintf('sylius_hipay_plugin.oney_standard_category.%s', $case->getTranslationKey());
    }

    public function getTranslationKey(): string
    {
        return match ($this) {
            self::HomeAndGardening => 'home_and_gardening',
            self::ClothingAndAccessories => 'clothing_and_accessories',
            self::HomeAppliances => 'home_appliances',
            self::SportsAndRecreations => 'sports_and_recreations',
            self::BabiesAndChildren => 'babies_and_children',
            self::HiFiPhotoVideoEquipment => 'hifi_photo_video_equipment',
            self::ItEquipment => 'it_equipment',
            self::PhoneAndInternetServices => 'phone_and_internet_services',
            self::PhysicalGoodsBooksMediaMusicMovies => 'physical_goods_books_media_music_movies',
            self::DigitalGoodsBooksMediaMusicMovies => 'digital_goods_books_media_music_movies',
            self::ConsolesAndVideoGames => 'consoles_and_video_games',
            self::GiftsAndFlowers => 'gifts_and_flowers',
            self::HealthAndBeauty => 'health_and_beauty',
            self::CarAndMotorcycle => 'car_and_motorcycle',
            self::Traveling => 'traveling',
            self::FoodAndGastronomy => 'food_and_gastronomy',
            self::AuctionsAndGroupBuying => 'auctions_and_group_buying',
            self::ServicesToProfessionals => 'services_to_professionals',
            self::ServicesToIndividuals => 'services_to_individuals',
            self::CultureAndEntertainment => 'culture_and_entertainment',
            self::GamesDigitalGoods => 'games_digital_goods',
            self::GamesPhysicalGoods => 'games_physical_goods',
            self::Ticketing => 'ticketing',
            self::OpticiansGoodsAndEyeglasses => 'opticians_goods_and_eyeglasses',
        };
    }
}
