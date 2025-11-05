# Performance Optimization Guide

This document describes the performance optimizations implemented for the NXP Easy Cart admin SPA to reduce empty states and improve perceived loading times.

## Overview

The admin component loading experience has been enhanced with:

1. **Performance markers** around fetch operations to track hydration times
2. **Cache-first data strategy** with configurable TTL to reduce redundant API calls
3. **Skeleton loading states** to provide visual feedback during data fetches
4. **Prefetch utilities** for adjacent panels (optional optimization)
5. **Last-updated timestamps** to show data freshness

## Implementation

### 1. Performance Tracking (`usePerformance.js`)

Located at: `media/com_nxpeasycart/src/app/composables/usePerformance.js`

**Features:**
- Performance.mark() wrappers for measuring fetch durations
- Cache store with TTL-based invalidation (default: 5 minutes)
- Cache hit/miss tracking for debugging
- Metadata export for performance analysis

**Usage:**
```javascript
import { usePerformance, getCachedData, setCachedData } from "./usePerformance.js";

const perf = usePerformance("products");
const startMark = perf.startFetch();

try {
    // ... fetch data
} finally {
    perf.endFetch(startMark);
}
```

**Console output:**
```
[NXP EC Performance] products-fetch: 234.56ms
[NXP EC Cache] Hit for products (3 hits, 1 misses)
```

### 2. Cache-First Strategy

**Updated composables:**
- `useProducts.js` - Full cache support with pagination/search awareness
- `useSettings.js` - Simple cache for settings data
- Recommended for: `useOrders.js`, `useCategories.js`, `useTaxRates.js`, `useShippingRules.js`

**Cache key patterns:**
```javascript
// Products: page + limit + search term
products:page=1:limit=20:search=shirt

// Settings: single key
settings:data

// Categories: page + limit + search term (recommended)
categories:page=1:limit=20:search=

// Orders: page + limit + search + state filter (recommended)
orders:page=1:limit=20:search=:state=paid
```

**Cache invalidation:**
- Automatic on mutation (create/update/delete)
- Manual via `refresh(true)` method
- TTL-based expiration (default: 5 minutes)

**Benefits:**
- Instant rendering when returning to a tab within TTL window
- Reduced server load from repeated fetches
- Improved perceived performance

### 3. Skeleton Loading States

**Component:** `SkeletonLoader.vue`

Located at: `media/com_nxpeasycart/src/app/components/SkeletonLoader.vue`

**Supported types:**
- `text` - Single line skeleton
- `rectangle` - Custom width/height block
- `circle` - Avatar/icon placeholder
- `card` - Card-shaped layout with header + body
- `table` - Multi-row, multi-column table skeleton

**Usage in panels:**
```vue
<div v-if="state.loading" class="nxp-ec-admin-panel__body">
    <SkeletonLoader type="table" :rows="5" :columns="5" />
</div>
```

**Styling:**
- Uses CSS animations (pulse effect)
- Neutral colors (#e4e7ec) for universal theme compatibility
- Scoped styles to avoid conflicts

### 4. Last Updated Indicator

Shows when data was last refreshed:

```vue
<div v-if="state.lastUpdated" class="nxp-ec-admin-panel__metadata">
    Last updated: {{ formatTimestamp(state.lastUpdated) }}
</div>
```

**Timestamp format:**
- "just now" (< 1 minute)
- "X minutes ago" (< 1 hour)
- "X hours ago" (< 24 hours)
- Full locale timestamp (> 24 hours)

### 5. Prefetch Utility (Optional)

Located at: `media/com_nxpeasycart/src/app/composables/usePrefetch.js`

**Purpose:** Background-load adjacent panels after main panel finishes loading

**Adjacency map:**
```javascript
{
    dashboard: ["products", "orders", "settings"],
    products: ["categories", "orders"],
    orders: ["customers", "products"],
    settings: ["payments", "tax", "shipping"],
    // ...
}
```

**Usage:**
```javascript
import { usePrefetch } from "./usePrefetch.js";

const { prefetchAdjacent } = usePrefetch();

// After dashboard loads:
prefetchAdjacent("dashboard", {
    products: () => loadProducts(),
    orders: () => loadOrders(),
    settings: () => loadSettings()
});
```

**Behavior:**
- 500ms delay before starting prefetch
- One prefetch at a time (queued)
- Logs prefetch activity to console
- Silent failures (doesn't interrupt UX)

## Configuration

### Cache TTL

Default TTL is 5 minutes (300000ms). Override per-composable:

```javascript
// Short TTL for frequently changing data
useProducts({ endpoints, token, cacheTTL: 120000 }); // 2 minutes

// Long TTL for rarely changing data
useSettings({ endpoints, token, cacheTTL: 600000 }); // 10 minutes
```

### Performance Monitoring

Check cache performance in console:

```javascript
import { getCacheMetadata } from "./usePerformance.js";

console.table(getCacheMetadata());
// Shows: key, age, timestamp, valid status
```

### Disable Caching (for debugging)

Force refresh bypasses cache:

```javascript
refresh(true); // Skip cache lookup
```

## Seeding Initial Data (PHP Side)

The PHP views already inject preload data via `data-*` attributes on the mount point. Composables that accept a `preload` parameter will use this data to avoid the first fetch:

**Example (Orders):**

`administrator/components/com_nxpeasycart/tmpl/app/default.php` already does:
```php
$ordersPreload = property_exists($this, 'orders') && is_array($this->orders)
    ? $this->orders
    : ['items' => [], 'pagination' => []];

// ... embedded in data-orders-preload attribute
```

**Composable side:**
```javascript
useOrders({
    endpoints,
    token,
    preload: { items: [...], pagination: {...} },
    autoload: true
});
```

The composable will:
1. Hydrate `state.items` from preload immediately
2. Skip the first fetch if preload data exists
3. Cache the preloaded data for consistency

**To seed more data**, update the View class:

```php
// In OrdersView.php (or similar)
public function display($tpl = null) {
    $model = $this->getModel();
    $this->orders = [
        'items' => $model->getItems(),
        'pagination' => [
            'total' => $model->getTotal(),
            'limit' => $model->getState('list.limit'),
            'current' => 1,
        ]
    ];

    parent::display($tpl);
}
```

## Measuring Impact

### Before optimization:
- Products panel: blank canvas → 500-1000ms wait → data appears
- Returning to Products: full re-fetch every time
- No visual feedback during loads

### After optimization:
- Products panel (first visit): skeleton → 500-1000ms → data appears
- Returning to Products (within 5min): **instant render from cache**
- "Last updated: 2 minutes ago" badge confirms data freshness
- Dashboard prefetches Products in background (optional)

### Performance markers output:
```
[NXP EC Performance] products-fetch: 234.56ms
[NXP EC Cache] Hit for products (3 hits, 1 misses)
[NXP EC Performance] settings-fetch: 89.12ms
[NXP EC Prefetch] Loading orders in background
[NXP EC Prefetch] Completed orders
```

## Next Steps (Optional Enhancements)

1. **Apply to remaining composables**:
   - `useOrders.js`
   - `useCategories.js`
   - `useTaxRates.js`
   - `useShippingRules.js`
   - `useCoupons.js`

2. **Expand skeleton variety**:
   - Add `list` type for simple item lists
   - Add `form` type for settings panels

3. **Service worker caching** (future):
   - Cache static admin bundle
   - Offline-first admin experience

4. **Smart prefetch**:
   - Track user navigation patterns
   - Prefetch based on most common flows

5. **Stale-while-revalidate**:
   - Show cached data immediately
   - Refresh in background
   - Update UI when new data arrives

## Testing Recommendations

1. **Test cache behavior:**
   ```bash
   # Load Products → Navigate away → Return to Products (< 5min)
   # Should render instantly from cache
   ```

2. **Test cache invalidation:**
   ```bash
   # Load Products → Create product → Check list updates
   # Cache should be cleared, fresh data fetched
   ```

3. **Test TTL expiration:**
   ```bash
   # Load Products → Wait 6 minutes → Return to Products
   # Should fetch fresh data (cache expired)
   ```

4. **Monitor console:**
   ```javascript
   // Look for performance markers and cache hit/miss logs
   [NXP EC Performance] products-fetch: 234.56ms
   [NXP EC Cache] Hit for products (3 hits, 1 misses)
   ```

5. **Test skeleton states:**
   ```bash
   # Throttle network in DevTools → Observe skeleton during load
   ```

## Troubleshooting

### Cache not working?
- Check console for "Cache Miss" vs "Cache Hit" logs
- Verify `cacheTTL` is not set too low
- Ensure composable accepts `cacheTTL` parameter

### Stale data showing?
- Use `refresh(true)` to force bypass cache
- Check if mutations are calling `clearCachedData()`
- Verify TTL is appropriate for data volatility

### Skeleton not appearing?
- Ensure `SkeletonLoader` is imported in component
- Check that `state.loading` is true during fetch
- Verify skeleton is wrapped in correct conditional block

### Performance markers missing?
- Check browser supports `window.performance.mark()`
- Verify composable calls `perf.startFetch()` / `perf.endFetch()`
- Open console to see logged durations

## Architecture Decisions

### Why client-side caching vs server-side?
- Reduces network round-trips for repeated views
- Instant tab switching within TTL window
- Complements existing PHP preload strategy
- No changes to server APIs required

### Why 5-minute default TTL?
- Balances freshness vs performance
- Short enough for admin use cases (not public data)
- Long enough to benefit repeat visits within session
- Configurable per-resource as needed

### Why prefetch is optional?
- Most panels load quickly enough without it
- Adds complexity and potential resource waste
- Beneficial only for slow endpoints or predictable flows
- Easy to add incrementally where needed

## Summary

These optimizations collectively achieve the goal: **smooth out empty states** and **reduce perceived latency** when navigating the admin component. The cache-first strategy provides the biggest win for repeat visits, while skeleton states improve the first-load experience.

All changes maintain backward compatibility and follow the project's "clarity + speed to first sale" principles.
