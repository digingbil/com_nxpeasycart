/**
 * Performance tracking and caching utilities for admin composables
 *
 * Helps measure hydration times and implements cache-first data strategy
 * with configurable TTL to reduce redundant API calls.
 */

import { ref, reactive } from "vue";

const performanceEnabled = typeof window !== "undefined" && window.performance;

/**
 * Simple perf marker wrapper around performance.mark
 */
export function markPerformance(name) {
    if (!performanceEnabled) {
        return;
    }

    try {
        performance.mark(name);
    } catch (error) {
        console.warn(`[NXP EC Performance] Failed to mark: ${name}`, error);
    }
}

/**
 * Measure time between two marks
 */
export function measurePerformance(name, startMark, endMark) {
    if (!performanceEnabled) {
        return null;
    }

    try {
        performance.measure(name, startMark, endMark);
        const measure = performance.getEntriesByName(name, "measure")[0];

        if (measure) {
            return measure.duration;
        }
    } catch (error) {
        console.warn(`[NXP EC Performance] Failed to measure: ${name}`, error);
    }

    return null;
}

/**
 * Cache store for composable data
 * Maps resource keys to cached payloads with TTL
 */
const cacheStore = reactive({});

/**
 * Default TTL: 5 minutes (300000ms)
 */
const DEFAULT_TTL = 300000;

/**
 * Check if cached entry is still valid
 */
function isCacheValid(entry, ttl) {
    if (!entry || !entry.timestamp) {
        return false;
    }

    const now = Date.now();
    const age = now - entry.timestamp;

    return age < ttl;
}

/**
 * Get cached data if valid
 */
export function getCachedData(key, ttl = DEFAULT_TTL) {
    const entry = cacheStore[key];

    if (!entry) {
        return null;
    }

    if (!isCacheValid(entry, ttl)) {
        delete cacheStore[key];

        return null;
    }

    return entry.data;
}

/**
 * Store data in cache with current timestamp
 */
export function setCachedData(key, data) {
    cacheStore[key] = {
        data,
        timestamp: Date.now(),
    };
}

/**
 * Clear cache for a specific key
 */
export function clearCachedData(key) {
    delete cacheStore[key];
}

/**
 * Clear all cached data
 */
export function clearAllCache() {
    Object.keys(cacheStore).forEach((key) => {
        delete cacheStore[key];
    });
}

/**
 * Get cache metadata (for debugging)
 */
export function getCacheMetadata() {
    const metadata = {};

    Object.keys(cacheStore).forEach((key) => {
        const entry = cacheStore[key];

        if (entry) {
            metadata[key] = {
                age: Date.now() - entry.timestamp,
                timestamp: entry.timestamp,
                valid: isCacheValid(entry, DEFAULT_TTL),
            };
        }
    });

    return metadata;
}

/**
 * Composable hook for performance tracking
 * Provides helpers for marking fetch start/end and cache management
 */
export function usePerformance(resourceName) {
    const metrics = ref({
        lastFetchDuration: null,
        totalFetches: 0,
        cacheHits: 0,
        cacheMisses: 0,
    });

    const startFetch = () => {
        const startMark = `${resourceName}-fetch-start`;
        markPerformance(startMark);

        return startMark;
    };

    const endFetch = (startMark) => {
        const endMark = `${resourceName}-fetch-end`;
        markPerformance(endMark);

        const duration = measurePerformance(
            `${resourceName}-fetch`,
            startMark,
            endMark
        );

        if (duration !== null) {
            metrics.value.lastFetchDuration = duration;
        }

        metrics.value.totalFetches += 1;
    };

    const recordCacheHit = () => {
        metrics.value.cacheHits += 1;
    };

    const recordCacheMiss = () => {
        metrics.value.cacheMisses += 1;
    };

    return {
        metrics,
        startFetch,
        endFetch,
        recordCacheHit,
        recordCacheMiss,
    };
}

export default usePerformance;
