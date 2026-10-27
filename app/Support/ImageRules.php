<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;

/**
 * One definition of "an acceptable catalog image", shared by the admin panel
 * and by request validation, so the two can never drift apart.
 */
final class ImageRules
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            self::file(),
        ];
    }

    public static function file(): ImageFile
    {
        // image() rejects anything the server cannot decode as an image, which
        // is what keeps a renamed .php or a scriptable .svg out of storage.
        return File::image(allowSvg: false)
            ->max(self::maxKilobytes().'kb')
            ->extensions(self::extensions())
            ->dimensions(
                Rule::dimensions()
                    ->minWidth(self::minDimension())
                    ->minHeight(self::minDimension())
                    ->maxWidth(self::maxDimension())
                    ->maxHeight(self::maxDimension())
            );
    }

    /**
     * @return array<int, string>
     */
    public static function extensions(): array
    {
        return config('catalog.images.accepted_mimes');
    }

    /**
     * @return array<int, string>
     */
    public static function mimeTypes(): array
    {
        return config('catalog.images.accepted_mime_types');
    }

    public static function maxKilobytes(): int
    {
        return (int) config('catalog.images.max_kilobytes');
    }

    public static function minDimension(): int
    {
        return (int) config('catalog.images.min_dimension');
    }

    public static function maxDimension(): int
    {
        return (int) config('catalog.images.max_dimension');
    }
}
