<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property-read Quarter|null $quarter
 * @property-read User|null $user
 * @method static AdFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ad newModelQuery()
 * @method static Builder<static>|Ad newQuery()
 * @method static Builder<static>|Ad onlyTrashed()
 * @method static Builder<static>|Ad query()
 * @method static Builder<static>|Ad withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ad withoutTrashed()
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string $adresse
 * @property string|null $price
 * @property string $surface_area
 * @property int $bedrooms
 * @property int $bathrooms
 * @property bool $has_parking
 * @property Point|null $location
 * @property string $status
 * @property string|null $expires_at
 * @property int $user_id
 * @property int $quarter_id
 * @property int $type_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static Builder<static>|Ad whereAdresse($value)
 * @method static Builder<static>|Ad whereBathrooms($value)
 * @method static Builder<static>|Ad whereBedrooms($value)
 * @method static Builder<static>|Ad whereCreatedAt($value)
 * @method static Builder<static>|Ad whereDeletedAt($value)
 * @method static Builder<static>|Ad whereDescription($value)
 * @method static Builder<static>|Ad whereExpiresAt($value)
 * @method static Builder<static>|Ad whereHasParking($value)
 * @method static Builder<static>|Ad whereId($value)
 * @method static Builder<static>|Ad whereLocation($value)
 * @method static Builder<static>|Ad wherePrice($value)
 * @method static Builder<static>|Ad whereQuarterId($value)
 * @method static Builder<static>|Ad whereSlug($value)
 * @method static Builder<static>|Ad whereStatus($value)
 * @method static Builder<static>|Ad whereSurfaceArea($value)
 * @method static Builder<static>|Ad whereTitle($value)
 * @method static Builder<static>|Ad whereTypeId($value)
 * @method static Builder<static>|Ad whereUpdatedAt($value)
 * @method static Builder<static>|Ad whereUserId($value)
 * @mixin Eloquent
 * @property-read \App\Models\AdType $ad_type
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 */
	class Ad extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $desc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static AdTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdType newModelQuery()
 * @method static Builder<static>|AdType newQuery()
 * @method static Builder<static>|AdType onlyTrashed()
 * @method static Builder<static>|AdType query()
 * @method static Builder<static>|AdType whereCreatedAt($value)
 * @method static Builder<static>|AdType whereDeletedAt($value)
 * @method static Builder<static>|AdType whereDesc($value)
 * @method static Builder<static>|AdType whereId($value)
 * @method static Builder<static>|AdType whereName($value)
 * @method static Builder<static>|AdType whereUpdatedAt($value)
 * @method static Builder<static>|AdType withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdType withoutTrashed()
 * @mixin Eloquent
 */
	class AdType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property string|null $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\AgencyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Agency withoutTrashed()
 */
	class Agency extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static CityFactory factory($count = null, $state = [])
 * @method static Builder<static>|City newModelQuery()
 * @method static Builder<static>|City newQuery()
 * @method static Builder<static>|City onlyTrashed()
 * @method static Builder<static>|City query()
 * @method static Builder<static>|City whereCreatedAt($value)
 * @method static Builder<static>|City whereDeletedAt($value)
 * @method static Builder<static>|City whereId($value)
 * @method static Builder<static>|City whereName($value)
 * @method static Builder<static>|City whereUpdatedAt($value)
 * @method static Builder<static>|City withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|City withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quarter> $quarters
 * @property-read int|null $quarters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @mixin Eloquent
 */
	class City extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property PaymentType $type
 * @property string $amount
 * @property string $transaction_id
 * @property PaymentMethod $payment_method
 * @property int $user_id
 * @property PaymentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @method static PaymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Payment newModelQuery()
 * @method static Builder<static>|Payment newQuery()
 * @method static Builder<static>|Payment onlyTrashed()
 * @method static Builder<static>|Payment query()
 * @method static Builder<static>|Payment whereAmount($value)
 * @method static Builder<static>|Payment whereCreatedAt($value)
 * @method static Builder<static>|Payment whereDeletedAt($value)
 * @method static Builder<static>|Payment whereId($value)
 * @method static Builder<static>|Payment wherePaymentMethod($value)
 * @method static Builder<static>|Payment whereStatus($value)
 * @method static Builder<static>|Payment whereTransactionId($value)
 * @method static Builder<static>|Payment whereType($value)
 * @method static Builder<static>|Payment whereUpdatedAt($value)
 * @method static Builder<static>|Payment whereUserId($value)
 * @method static Builder<static>|Payment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Payment withoutTrashed()
 * @property int $ad_id
 * @property-read \App\Models\Ad $ad
 * @method static Builder<static>|Payment whereAdId($value)
 * @mixin Eloquent
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $tokenable_type
 * @property string $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<array-key, mixed>|null $abilities
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $tokenable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereAbilities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereTokenableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereTokenableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonalAccessToken whereUpdatedAt($value)
 */
	class PersonalAccessToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property int $city_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read City $city
 * @method static QuarterFactory factory($count = null, $state = [])
 * @method static Builder<static>|Quarter newModelQuery()
 * @method static Builder<static>|Quarter newQuery()
 * @method static Builder<static>|Quarter onlyTrashed()
 * @method static Builder<static>|Quarter query()
 * @method static Builder<static>|Quarter whereCityId($value)
 * @method static Builder<static>|Quarter whereCreatedAt($value)
 * @method static Builder<static>|Quarter whereDeletedAt($value)
 * @method static Builder<static>|Quarter whereId($value)
 * @method static Builder<static>|Quarter whereName($value)
 * @method static Builder<static>|Quarter whereUpdatedAt($value)
 * @method static Builder<static>|Quarter withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Quarter withoutTrashed()
 * @mixin Eloquent
 */
	class Quarter extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read Ad|null $ad
 * @property-read User|null $user
 * @method static ReviewFactory factory($count = null, $state = [])
 * @method static Builder<static>|Review newModelQuery()
 * @method static Builder<static>|Review newQuery()
 * @method static Builder<static>|Review onlyTrashed()
 * @method static Builder<static>|Review query()
 * @method static Builder<static>|Review withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Review withoutTrashed()
 * @property int $id
 * @property string $rating
 * @property string|null $comment
 * @property int $ad_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static Builder<static>|Review whereAdId($value)
 * @method static Builder<static>|Review whereComment($value)
 * @method static Builder<static>|Review whereCreatedAt($value)
 * @method static Builder<static>|Review whereDeletedAt($value)
 * @method static Builder<static>|Review whereId($value)
 * @method static Builder<static>|Review whereRating($value)
 * @method static Builder<static>|Review whereUpdatedAt($value)
 * @method static Builder<static>|Review whereUserId($value)
 * @mixin Eloquent
 */
	class Review extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read Ad|null $ad
 * @property-read Payment|null $payment
 * @property-read User|null $user
 * @method static UnlockedAdFactory factory($count = null, $state = [])
 * @method static Builder<static>|UnlockedAd newModelQuery()
 * @method static Builder<static>|UnlockedAd newQuery()
 * @method static Builder<static>|UnlockedAd onlyTrashed()
 * @method static Builder<static>|UnlockedAd query()
 * @method static Builder<static>|UnlockedAd withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|UnlockedAd withoutTrashed()
 * @property int $id
 * @property int $ad_id
 * @property int $user_id
 * @property int $payment_id
 * @property int|null $unlocked_at
 * @property int|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static Builder<static>|UnlockedAd whereAdId($value)
 * @method static Builder<static>|UnlockedAd whereDeletedAt($value)
 * @method static Builder<static>|UnlockedAd whereId($value)
 * @method static Builder<static>|UnlockedAd wherePaymentId($value)
 * @method static Builder<static>|UnlockedAd whereUnlockedAt($value)
 * @method static Builder<static>|UnlockedAd whereUpdatedAt($value)
 * @method static Builder<static>|UnlockedAd whereUserId($value)
 * @mixin Eloquent
 */
	class UnlockedAd extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $phone_number
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $avatar
 * @property UserType|null $type
 * @property UserRole $role
 * @property int $city_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Ad> $ads
 * @property-read int|null $ads_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, UnlockedAd> $unlockedAds
 * @property-read int|null $unlocked_ads_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereAvatar($value)
 * @method static Builder<static>|User whereCityId($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstname($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastname($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhoneNumber($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereType($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property bool $is_active
 * @property-read City $city
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Review> $reviews
 * @property-read int|null $reviews_count
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereLastLoginAt($value)
 * @method static Builder<static>|User whereLastLoginIp($value)
 * @mixin Eloquent
 * @property \Clickbar\Magellan\Data\Geometries\Point|null $location
 * @property string|null $app_authentication_secret
 * @property array<array-key, mixed>|null $app_authentication_recovery_codes
 * @property bool $has_email_authentication
 * @property string|null $agency_id
 * @property-read \App\Models\Agency|null $agency
 * @property-read string $fullname
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAgencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHasEmailAuthentication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocation($value)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser, \Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication, \Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery, \Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication, \Spatie\MediaLibrary\HasMedia, \Filament\Models\Contracts\HasName, \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

