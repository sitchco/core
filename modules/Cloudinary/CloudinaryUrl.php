<?php

declare(strict_types=1);

namespace Sitchco\Modules\Cloudinary;

use Sitchco\Support\CropDirection;
use Sitchco\Utils\Cache;

class CloudinaryUrl
{
    private const BASE_URL = 'https://res.cloudinary.com';
    private const DEFAULT_TRANSFORMS = ['f_auto', 'q_auto'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'ogv'];

    private const GRAVITY_MAP = [
        'center' => 'g_center',
        'top' => 'g_north',
        'bottom' => 'g_south',
        'left' => 'g_west',
        'right' => 'g_east',
        'top-center' => 'g_north',
        'bottom-center' => 'g_south',
    ];

    public function __construct(private string $cloudName, private string $folder) {}

    public function buildUrl(string $src, ?int $width = null, ?int $height = null, ?CropDirection $crop = null): string
    {
        $relativePath = $this->extractRelativePath($src);
        if ($relativePath === null) {
            return $src;
        }

        $resourceType = $this->resolveResourceType($relativePath);
        $isSvg = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'svg';
        $transforms = $isSvg ? ['q_auto'] : self::DEFAULT_TRANSFORMS;
        if ($resourceType === 'image' && !$isSvg) {
            if ($width) {
                $transforms[] = "w_{$width}";
            }
            if ($height) {
                $transforms[] = "h_{$height}";
            }
            if ($crop && $crop !== CropDirection::NONE) {
                $transforms[] = 'c_fill';
                $gravity = self::GRAVITY_MAP[$crop->value] ?? null;
                if ($gravity) {
                    $transforms[] = $gravity;
                }
            }
        }

        $transformString = implode(',', $transforms);
        return self::BASE_URL .
            "/{$this->cloudName}/{$resourceType}/upload/{$transformString}/{$this->folder}/{$relativePath}";
    }

    private function extractRelativePath(string $src): ?string
    {
        $src = strtok($src, '?#');
        $prefix = Cache::remember('uploads_base_url', fn() => wp_get_upload_dir()['baseurl']) . '/';
        if (!str_starts_with($src, $prefix)) {
            return null;
        }
        return substr($src, strlen($prefix));
    }

    private function resolveResourceType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        return in_array($extension, self::VIDEO_EXTENSIONS, true) ? 'video' : 'image';
    }
}
