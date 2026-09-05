<?php

declare(strict_types=1);

use App\Support\MailTheme;

it('keeps the two audiences visually apart on every token', function (): void {
    $client = MailTheme::client();
    $owner = MailTheme::owner();

    // If any colour token were shared, one space would bleed into the other and
    // the whole point of the class would be gone.
    foreach (['accent', 'link', 'gradient', 'surface', 'border', 'tintBorder', 'tintText'] as $token) {
        expect($client[$token])->not->toBe($owner[$token], "token {$token} is shared");
    }
});

it('exposes the same token set for both audiences', function (): void {
    expect(array_keys(MailTheme::client()))->toBe(array_keys(MailTheme::owner()));
});

it('resolves an audience string to its palette and falls back to the client one', function (): void {
    expect(MailTheme::for(MailTheme::OWNER))->toBe(MailTheme::owner())
        ->and(MailTheme::for(MailTheme::CLIENT))->toBe(MailTheme::client())
        ->and(MailTheme::for('marketing-intern-invented-this'))->toBe(MailTheme::client());
});

it('names its own audience so a partial can tell which palette it got', function (): void {
    expect(MailTheme::client()['audience'])->toBe(MailTheme::CLIENT)
        ->and(MailTheme::owner()['audience'])->toBe(MailTheme::OWNER);
});

// BUG CATCH: link text is the one token that carries copy, so it is the one that
// has to clear WCAG AA (4.5:1). The accent hues do not, which is why `link`
// exists as a separate, darker token.
it('uses link colours that clear WCAG AA on white', function (string $hex): void {
    expect(contrastOnWhite($hex))->toBeGreaterThanOrEqual(4.5);
})->with([
    'client' => MailTheme::client()['link'],
    'owner' => MailTheme::owner()['link'],
]);

it('has accents too light for body text, which is why link is darker', function (): void {
    // Documents the reason the two tokens are not merged: if this ever stops
    // being true the accents could be used for copy and `link` could go.
    expect(contrastOnWhite(MailTheme::client()['accent']))->toBeLessThan(4.5)
        ->and(contrastOnWhite(MailTheme::owner()['accent']))->toBeLessThan(4.5);
});

/**
 * WCAG 2.1 relative-luminance contrast ratio against #ffffff.
 */
function contrastOnWhite(string $hex): float
{
    $channel = static function (string $pair): float {
        $value = hexdec($pair) / 255;

        return $value <= 0.03928
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    };

    $hex = ltrim($hex, '#');

    $luminance = 0.2126 * $channel(substr($hex, 0, 2))
        + 0.7152 * $channel(substr($hex, 2, 2))
        + 0.0722 * $channel(substr($hex, 4, 2));

    return 1.05 / ($luminance + 0.05);
}
