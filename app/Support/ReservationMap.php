<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReservationMap
{
    private const DIRECTORY = 'club-map';

    private const BASENAME = 'reservation-map';

    private const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    public static function url(): string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = self::pathFor($extension);

            if (Storage::disk('public')->exists($path)) {
                return '/storage/'.ltrim($path, '/');
            }
        }

        return asset('images/aabb.jpg');
    }

    public static function store(UploadedFile $file): string
    {
        self::deleteExisting();

        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = self::BASENAME.'.'.$extension;

        Storage::disk('public')->putFileAs(self::DIRECTORY, $file, $filename);

        return self::DIRECTORY.'/'.$filename;
    }

    private static function deleteExisting(): void
    {
        foreach (self::EXTENSIONS as $extension) {
            Storage::disk('public')->delete(self::pathFor($extension));
        }
    }

    private static function pathFor(string $extension): string
    {
        return self::DIRECTORY.'/'.self::BASENAME.'.'.$extension;
    }
}
