<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Portal\Support\PortalTheme;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The installed app's icon (FR-PRT-06): the business's initials on its
 * accent color. A stand-in until owners can upload a square app icon.
 */
final class IconController
{
    /**
     * @var list<int>
     */
    public const SIZES = [192, 512];

    public function __invoke(TenantContext $tenants, string $tenant, string $size): Response
    {
        $pixels = (int) $size;

        abort_unless(in_array($pixels, self::SIZES, true), 404);

        $business = $tenants->tenant();
        $initials = Str::upper(implode('', array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            array_slice(array_values(array_filter(explode(' ', $business->name))), 0, 2),
        )));

        $image = imagecreatetruecolor($pixels, $pixels);
        [$red, $green, $blue] = self::rgb(PortalTheme::accent($business));
        [$textRed, $textGreen, $textBlue] = self::rgb(PortalTheme::accentText($business));
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $red, $green, $blue));

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $fontSize = $pixels * (mb_strlen($initials) > 1 ? 0.3 : 0.4);
        $box = imagettfbbox($fontSize, 0, $font, $initials) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $x = (int) (($pixels - ($box[2] - $box[0])) / 2 - $box[0]);
        $y = (int) (($pixels - ($box[1] - $box[7])) / 2 - $box[7]);
        imagettftext($image, $fontSize, 0, $x, $y, (int) imagecolorallocate($image, $textRed, $textGreen, $textBlue), $font, $initials);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private static function rgb(string $hex): array
    {
        $channel = fn (int $offset): int => max(0, min(255, (int) hexdec(substr($hex, $offset, 2))));

        return [$channel(1), $channel(3), $channel(5)];
    }
}
