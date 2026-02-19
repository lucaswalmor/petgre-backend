<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResourceCollection extends ResourceCollection
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Indicates if the resource's collection keys should be preserved.
     *
     * @var bool
     */
    public $preserveKeys = true;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $first = $this->collection->first();
        $resourceName = 'data';

        if ($first !== null) {
            $resourceClass = get_class($first);
            $extracted = strtolower(substr($resourceClass, strrpos($resourceClass, '\\') + 1));
            if ($extracted) {
                $resourceName = $extracted;
            }
        }

        return [
            $resourceName => $this->collection,
        ];
    }
}