<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait FiltersByCatalog
{
    /**
     * Normalize a request field that may arrive as a scalar or array into a clean id array.
     */
    protected function catalogIds(Request $request, string $key): array
    {
        return array_filter((array) $request->get($key, []));
    }

    /**
     * Apply category_id/brand_id filtering to a query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $relation  Dot relation path to the product (e.g. 'items.product'), or null if $query is already a Product query.
     */
    protected function applyCategoryBrandFilter($query, ?string $relation, array $categoryIds, array $brandIds)
    {
        if (empty($categoryIds) && empty($brandIds)) {
            return $query;
        }

        $constrain = function ($q) use ($categoryIds, $brandIds) {
            if (!empty($categoryIds)) {
                $q->whereIn('category_id', $categoryIds);
            }
            if (!empty($brandIds)) {
                $q->whereIn('brand_id', $brandIds);
            }
        };

        if ($relation === null) {
            $constrain($query);
        } else {
            $query->whereHas($relation, $constrain);
        }

        return $query;
    }
}
