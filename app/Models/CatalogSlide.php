<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CatalogSlide extends Model
{
    protected $fillable = ['title', 'subtitle', 'button_text', 'destination', 'sort_order', 'active', 'desktop_disk', 'desktop_path', 'mobile_disk', 'mobile_path', 'generation'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    public function getDesktopUrlAttribute(): string
    {
        return $this->url($this->desktop_disk, $this->desktop_path);
    }

    public function getMobileUrlAttribute(): string
    {
        return $this->mobile_path ? $this->url($this->mobile_disk, $this->mobile_path) : $this->desktop_url;
    }

    private function url(?string $disk, ?string $path): string
    {
        if (! $path || ! in_array($disk, ['public', 'r2_public'], true)) {
            return '';
        }

        return $disk === 'public' ? asset('storage/'.$path) : Storage::disk($disk)->url($path);
    }
}
