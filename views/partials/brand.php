<?php
declare(strict_types=1);

/**
 * Bukid Basket brand marks, drawn as plain SVG geometry.
 * The seal is the round badge printed on the face of a rice sack;
 * the sack icon is the basket button's little bag.
 */

function brand_badge(string $class = 'brand-badge'): string
{
    // Grains sit alternately left/right along a drooping rice stalk.
    $grains = [
        [38.6, 17.2, 28], [41.4, 20.0, 52], [37.0, 21.4, 18], [40.2, 24.4, 46],
        [35.8, 25.6, 12], [38.6, 28.6, 38], [34.8, 30.0, 8], [37.0, 33.0, 30],
    ];
    $g = '';
    foreach ($grains as [$x, $y, $rot]) {
        $g .= sprintf('<ellipse cx="%s" cy="%s" rx="1.55" ry="3.1" transform="rotate(%d %s %s)"/>', $x, $y, $rot, $x, $y);
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 64 64" role="img" aria-label="Bukid Basket seal">'
        . '<circle cx="32" cy="32" r="31" fill="#c8202f"/>'
        . '<circle cx="32" cy="32" r="25.5" fill="#fbf9f3"/>'
        . '<circle cx="32" cy="32" r="28.2" fill="none" stroke="#fbf9f3" stroke-width="1.3" stroke-dasharray="3 2.2"/>'
        . '<path d="M31 52 C31 44 32 36 35 28 C37 22 39 18 42 15" fill="none" stroke="#1f6b3a" stroke-width="2.2" stroke-linecap="round"/>'
        . '<g fill="#1f6b3a">' . $g . '</g>'
        . '<path d="M31 50 C24 47 20 41 20 34 C26 37 29.5 42 31 50 Z" fill="#1f6b3a"/>'
        . '<path d="M31.4 46 C35 43 40 42 45 43 C41 46.5 36.5 47.5 31.4 46 Z" fill="#144b28"/>'
        . '<rect x="18" y="51.5" width="28" height="3" rx="1" fill="#161512"/>'
        . '</svg>';
}

function sack_icon(): string
{
    return '<svg class="basket-sack" viewBox="0 0 30 34" aria-hidden="true">'
        . '<path d="M9 3.5 L21 3.5 L19 9 C25.5 12 28.5 19 27.5 31.5 L2.5 31.5 C1.5 19 4.5 12 11 9 Z" fill="#fbf9f3"/>'
        . '<path d="M10 9.2 L20 9.2" stroke="#c8202f" stroke-width="2.2" stroke-linecap="round"/>'
        . '<rect x="4.2" y="19" width="21.6" height="5" fill="#c8202f"/>'
        . '<path d="M9.4 3.5 L8 1 M20.6 3.5 L22 1" stroke="#fbf9f3" stroke-width="1.6" stroke-linecap="round"/>'
        . '</svg>';
}
