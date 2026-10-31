<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;

/** A multipart body with one `photo` part: avatar or group photo. */
class UploadPhotoRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            // `mimes` validates the sniffed content, not the client file name or Content-Type.
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ];
    }

    public function photo(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('photo');

        return $file;
    }
}
