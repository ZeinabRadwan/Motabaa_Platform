<?php

namespace App\Http\Resources\Case;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SCaseFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_name' => $this['file_name'],
            'file_url' => $this['file_url'],
            'file_extension' => $this['file_extension'],
        ];
    }
}
