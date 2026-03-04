<?php

namespace App\Http\Resources\Kit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KitCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'kits' => KitResource::collection($this->collection),
            'paginacao' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
                'has_more_pages' => $this->resource->hasMorePages(),
            ],
            'links' => $this->resource->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
            'filtros_aplicados' => [
                'ativo' => $request->ativo,
                'q' => $request->q,
                'order_by' => $request->order_by ?? 'created_at',
                'order_direction' => $request->order_direction ?? 'desc',
            ],
        ];
    }
}
