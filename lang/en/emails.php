<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Translations — English
    |--------------------------------------------------------------------------
    */

    // Layout / Footer
    'layout' => [
        'rights' => 'All rights reserved.',
        'receiving_reason' => 'You are receiving this email because you are registered on :app.',
        'unsubscribe' => 'Unsubscribe',
        'manage_preferences' => 'Manage my preferences',
    ],

    // Welcome
    'welcome' => [
        'subject' => 'Welcome to :app',
        'heading' => 'Welcome, :name',
        'intro' => 'Your <strong>:app</strong> account is now active. You are officially part of the community!',
        'what_you_can_do' => 'We designed this platform to simplify your real estate experience. Here\'s what you can do right now:',
        'feature_search' => 'Smart search',
        'feature_search_desc' => 'Advanced filters, map search and neighborhood browsing.',
        'feature_alerts' => 'Create alerts',
        'feature_alerts_desc' => 'Get notified as soon as a property matches your criteria.',
        'feature_favorites' => 'Manage favorites',
        'feature_favorites_desc' => 'Save listings that interest you.',
        'cta' => 'Take the grand tour',
        'help' => 'If you have any questions, our team is available at',
    ],

    // Verification Code
    'verification_code' => [
        'subject' => ':code is your :app verification code',
        'heading' => 'Verification code',
        'enter_code' => 'Enter the following verification code when prompted:',
        'otp_label' => 'One-time code — do not share',
        'not_requested' => 'Didn\'t request this?',
        'requested_from' => 'This code was requested from <strong>:from</strong> on <strong>:at</strong>. If you did not initiate this request, you can safely ignore this email.',
    ],

    // Forgot Password
    'forgot_password' => [
        'subject' => 'Reset your :app password',
        'heading' => 'Password reset',
        'intro' => 'We received a request to reset the password for your <strong>:app</strong> account.',
        'click_below' => 'Click the button below to choose a new password. This link will expire in <strong>60 minutes</strong>.',
        'cta' => 'Reset password',
        'fallback' => 'Or copy and paste this link into your browser:',
        'not_requested' => 'Didn\'t request this?',
        'requested_from' => 'This request was made from <strong>:from</strong> on <strong>:at</strong>. If you did not initiate this request, you can safely ignore this email.',
    ],

    // Ad Approved
    'ad_approved' => [
        'subject' => 'Your listing is published',
        'heading' => 'Your listing is published',
        'greeting' => 'Hello <strong>:name</strong>,',
        'intro' => 'Great news — your listing has been <strong>approved</strong> by our moderation team and is now <strong>visible to all users</strong> on the platform.',
        'status_badge' => 'Published and visible',
        'recap_label' => 'Summary',
        'title_label' => 'Title',
        'price_label' => 'Price',
        'cta' => 'View my listing online',
        'thanks' => 'Thank you for your trust. Happy publishing on KeyHome.',
    ],

    // Ad Declined
    'ad_declined' => [
        'subject' => 'Your listing was not approved',
        'heading' => 'Listing not approved',
    ],

    // Search Alert Match
    'search_alert' => [
        'subject' => 'New listing for you — :app',
        'heading' => ':name, a listing is waiting for you!',
        'intro' => 'A property matching your criteria has just been published on <strong>KeyHome</strong>. Don\'t miss this opportunity — the best listings go fast.',
        'new_badge' => 'New',
        'per_month' => '/month',
        'surface' => 'Area',
        'bedrooms' => 'Bedrooms',
        'bathrooms' => 'Bathrooms',
        'cta' => 'View this listing',
    ],

    // Refund Confirmation
    'refund' => [
        'subject' => 'Refund confirmed — :amount XAF',
        'heading' => 'Amount refunded',
        'greeting' => 'Hello :name,',
        'intro' => 'Your refund request has been successfully processed. Here are the details:',
        'payment_ref' => 'Payment reference',
        'type' => 'Type',
        'type_partial' => 'Partial refund',
        'type_full' => 'Full refund',
        'reason' => 'Reason',
        'date' => 'Date',
        'processing_note' => 'The refund will be credited to your original payment method within 5 to 10 business days, depending on your provider.',
        'contact' => 'If you have any questions, feel free to contact us.',
    ],

    // Subscription Expiring
    'subscription_expiring' => [
        'subject' => 'Reminder: Your subscription expires soon',
    ],

    // Subscription Renewal Reminder
    'subscription_renewal' => [
        'subject' => 'Subscription renewal — Action required',
    ],

    // Credit Purchase
    'credit_purchase' => [
        'subject' => 'Credit purchase confirmed — :name',
    ],

    // New Device Sign-In
    'new_device' => [
        'subject' => 'Sign-in from a new device',
    ],

    // New Location Sign-In
    'new_location' => [
        'subject' => 'Sign-in from a new location',
    ],

    // Password Changed
    'password_changed' => [
        'subject' => 'Your password has been changed',
    ],

    // Passkey Changed
    'passkey_changed' => [
        'subject' => 'Your passkey has been changed',
    ],

    // Email Updated
    'email_updated' => [
        'subject' => 'Your email address has been changed',
    ],

    // Generic
    'generic' => [
        'hello' => 'Hello',
        'thanks' => 'Thank you',
        'support_email' => 'support@keyhome.app',
        'questions' => 'If you have any questions, contact us at',
    ],

    // Welcome Drip Series
    'welcome_drip' => [
        'day1_subject' => 'Tip #1 — How to find the perfect property on :app',
        'day1_heading' => 'Find your next home',
        'day1_intro' => 'Hello :name, welcome to the <strong>:app</strong> community! Here are our best tips to get started.',
        'day1_tip1' => 'Use advanced filters',
        'day1_tip1_desc' => 'Price, neighborhood, number of bedrooms... refine your search in a few clicks.',
        'day1_tip2' => 'Enable alerts',
        'day1_tip2_desc' => 'Get notified by email when a new property matches your criteria.',
        'day1_tip3' => 'Explore the map',
        'day1_tip3_desc' => 'View available properties in your favorite neighborhood.',
        'day1_cta' => 'Explore listings',

        'day3_subject' => 'Tip #2 — Create your first alert on :app',
        'day3_heading' => 'Never miss a property',
        'day3_intro' => ':name, the best listings go fast. Create an alert to be notified first.',
        'day3_cta' => 'Create an alert',

        'day7_subject' => 'How is your experience on :app?',
        'day7_heading' => 'Your feedback matters',
        'day7_intro' => ':name, it\'s been a week since you joined us. Have you found what you were looking for?',
        'day7_cta' => 'Browse listings',
    ],

    // Inactivity Warning
    'inactivity' => [
        'subject' => ':name, we miss you on :app!',
        'heading' => 'Welcome back?',
        'intro' => 'It\'s been :days days since you last logged into <strong>:app</strong>. New listings are waiting for you!',
        'stats' => ':count new listings published since your last visit.',
        'cta' => 'See what\'s new',
    ],

    // Failed Payment Retry
    'failed_payment' => [
        'subject' => 'Your payment didn\'t go through — :app',
        'heading' => 'Payment unsuccessful',
        'intro' => 'Hello :name, your payment of <strong>:amount XAF</strong> for <strong>:type</strong> could not be processed.',
        'reason' => 'This may be due to insufficient balance, unstable network, or an expired session.',
        'cta' => 'Retry payment',
        'help' => 'If the problem persists, contact us at',
    ],

    // Weekly Digest
    'digest' => [
        'subject' => 'Your weekly real estate digest — :app',
        'heading' => 'Your week in review',
        'intro' => 'Hello :name, here\'s what happened this week on <strong>:app</strong>.',
        'new_ads' => ':count new listings',
        'in_your_city' => 'in your city',
        'matching_alerts' => ':count match your alerts',
        'cta' => 'View all listings',
        'no_activity' => 'No new listings this week. Create an alert to never miss out!',
    ],

    'subscription_success' => [
        'title' => 'Subscription activated — :app',
        'heading' => 'Subscription activated 🎉',
        'badge' => '✓ Payment confirmed',
        'greeting' => 'Hello :agency team,',
        'intro' => 'We are pleased to confirm the activation of your subscription. Thank you for your trust!',
        'amount_label' => 'Amount paid',
        'details_heading' => '📋 Subscription details',
        'plan' => 'Plan',
        'period' => 'Period',
        'valid_until' => 'Valid until',
        'benefits' => 'Benefits',
        'benefits_value' => 'Boost + increased limits',
        'dashboard_cta' => 'Go to my dashboard',
        'closing' => 'Thank you for choosing :app to grow your business!',
    ],

    'appointment_reminder' => [
        'subject' => 'Reminder: Viewing scheduled tomorrow — :app',
        'heading' => 'Viewing reminder',
        'intro' => 'Hello :name, you have a viewing scheduled tomorrow for <strong>:property</strong>.',
        'date_label' => 'Date and time',
        'address_label' => 'Address',
        'cta' => 'View details',
        'cancel_note' => 'If you can no longer attend, please cancel at least 2 hours in advance.',
    ],

    'post_viewing_feedback' => [
        'subject' => 'How was your viewing? — :app',
        'heading' => 'We\'d love your feedback',
        'intro' => 'Hello :name, you recently viewed <strong>:property</strong>. What did you think?',
        'cta' => 'Share my feedback',
        'alternative' => 'Still looking? Discover similar properties.',
        'browse_cta' => 'Browse listings',
    ],

    'abandoned_search' => [
        'subject' => 'Your searches are waiting — :app',
        'heading' => 'Don\'t miss your next home',
        'intro' => 'Hello :name, you recently searched for properties on <strong>:app</strong> but didn\'t finish your search.',
        'matching' => ':count listings match your criteria.',
        'cta' => 'Resume my search',
        'alert_tip' => 'Create an alert to be notified as soon as a matching property is listed.',
    ],
];
