const applyReplacements = (message, replacements = []) => {
    if (!replacements.length) {
        return message;
    }

    const sprintf = window?.Joomla?.sprintf;

    if (typeof sprintf === 'function') {
        try {
            return sprintf.call(window.Joomla, message, ...replacements);
        } catch (error) {
            // Fall back to naive replacement when sprintf fails.
        }
    }

    let output = String(message);

    replacements.forEach((replacement) => {
        const value = replacement ?? '';
        output = output.replace('%s', String(value));
    });

    return output;
};

export const __ = (key, fallback = '', replacements = []) => {
    const text = window?.Joomla?.Text;
    let message = fallback || key;

    if (text && typeof text._ === 'function') {
        const translated = text._(key);

        if (translated && translated !== key) {
            message = translated;
        }
    }

    return applyReplacements(message, replacements);
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
