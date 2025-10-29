export const __ = (key, fallback = '', replacements = []) => {
    const text = window?.Joomla?.Text;

    if (text && typeof text._ === 'function') {
        const value = replacements.length
            ? text._(key, replacements)
            : text._(key);

        if (value && value !== key) {
            return value;
        }
    }

    return fallback;
};

export function useTranslations(dataset = {}) {
    const source = dataset || {};

    const fromDataset = (datasetKey, fallback) => {
        if (!datasetKey) {
            return fallback;
        }

        const value = source[datasetKey];

        return value != null && value !== '' ? value : fallback;
    };

    const translate = (languageKey, fallback = '', replacements = [], datasetKey = '') => {
        const resolvedFallback = fromDataset(datasetKey, fallback);

        return __(languageKey, resolvedFallback, replacements);
    };

    return {
        __: translate,
    };
}

export default useTranslations;
