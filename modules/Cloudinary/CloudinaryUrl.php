<?php

declare(strict_types=1);

namespace Sitchco\Modules\Cloudinary;

use Sitchco\Support\CropDirection;
use Sitchco\Support\ImageTransform;
use Sitchco\Utils\Cache;

class CloudinaryUrl
{
    private const BASE_URL = 'https://res.cloudinary.com';
    private const DEFAULT_TRANSFORMS = ['f_auto', 'q_auto'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'ogv'];

    private string $cloudName;
    private string $folder;

    public function __construct()
    {
        if ($this->isConfigured()) {
            $this->cloudName = CLOUDINARY_CLOUD_NAME;
            $this->folder = CLOUDINARY_FOLDER;
        }
    }

    public function isConfigured(): bool
    {
        return defined('CLOUDINARY_CLOUD_NAME') &&
            defined('CLOUDINARY_FOLDER') &&
            CLOUDINARY_CLOUD_NAME &&
            CLOUDINARY_FOLDER;
    }

    public function buildUrl(string $src, ?ImageTransform $transform = null): string
    {
        $relativePath = $this->extractRelativePath($src);
        if ($relativePath === null) {
            return $src;
        }

        $resourceType = $this->resolveResourceType($relativePath);
        $isSvg = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'svg';
        $transforms = $isSvg ? ['q_auto'] : self::DEFAULT_TRANSFORMS;
        if ($transform && $resourceType === 'image' && !$isSvg) {
            if ($transform->width) {
                $transforms[] = "w_{$transform->width}";
            }
            if ($transform->height) {
                $transforms[] = "h_{$transform->height}";
            }
            if ($transform->crop && $transform->crop !== CropDirection::NONE) {
                $transforms[] = 'c_fill';
                $gravity = $this->gravityParam($transform->crop);
                if ($gravity) {
                    $transforms[] = $gravity;
                }
            }
        }

        $transformString = implode(',', $transforms);
        return self::BASE_URL .
            "/{$this->cloudName}/{$resourceType}/upload/{$transformString}/{$this->folder}/{$relativePath}";
    }

    private function gravityParam(CropDirection $crop): ?string
    {
        return match ($crop) {
            CropDirection::CENTER => 'g_center',
            CropDirection::TOP, CropDirection::TOP_CENTER => 'g_north',
            CropDirection::BOTTOM, CropDirection::BOTTOM_CENTER => 'g_south',
            CropDirection::LEFT => 'g_west',
            CropDirection::RIGHT => 'g_east',
            CropDirection::NONE => null,
        };
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
