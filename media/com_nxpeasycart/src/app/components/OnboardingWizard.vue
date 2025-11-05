<template>
    <div v-if="visible" class="nxp-ec-onboarding" v-cloak>
        <div class="nxp-ec-onboarding__backdrop" @click="$emit('close')" />
        <section class="nxp-ec-onboarding__panel" role="dialog" aria-modal="true">
            <header class="nxp-ec-onboarding__header">
                <div>
                    <h2 class="nxp-ec-onboarding__title">
                        {{
                            __(
                                titleKey,
                                "Get ready to sell",
                                [],
                                "onboardingTitle"
                            )
                        }}
                    </h2>
                    <p class="nxp-ec-onboarding__lead">
                        {{
                            __(
                                leadKey,
                                "Work through these guided steps to launch in minutes.",
                                [],
                                "onboardingLead"
                            )
                        }}
                    </p>
                </div>
                <button
                    type="button"
                    class="nxp-ec-link-button"
                    @click="$emit('close')"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_ONBOARDING_DISMISS",
                            "Dismiss",
                            [],
                            "onboardingDismiss"
                        )
                    }}
                </button>
            </header>

            <div class="nxp-ec-onboarding__progress">
                <div class="nxp-ec-onboarding__progress-bar">
                    <span :style="{ width: progressPercent }" />
                </div>
                <span class="nxp-ec-onboarding__progress-label">
                    {{ progressLabel }}
                </span>
            </div>

            <ol class="nxp-ec-onboarding__steps">
                <li
                    v-for="step in steps"
                    :key="step.id"
                    :class="{ 'is-complete': step.completed }"
                >
                    <div class="nxp-ec-onboarding__step-main">
                        <span
                            class="nxp-ec-onboarding__step-icon"
                            aria-hidden="true"
                        >
                            <span
                                v-if="step.completed"
                                class="fa-solid fa-circle-check"
                            />
                            <span v-else class="fa-regular fa-circle" />
                        </span>
                        <div>
                            <h3>
                                {{
                                    __(
                                        step.titleKey,
                                        step.titleFallback,
                                        [],
                                        `${step.id}-title`
                                    )
                                }}
                            </h3>
                            <p>
                                {{
                                    __(
                                        step.descriptionKey,
                                        step.descriptionFallback,
                                        [],
                                        `${step.id}-description`
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="nxp-ec-onboarding__step-actions">
                        <button
                            v-if="step.link"
                            type="button"
                            class="nxp-ec-btn"
                            :class="{ 'nxp-ec-btn--ghost': step.completed }"
                            @click="$emit('navigate', step)"
                        >
                            {{
                                step.completed
                                    ? __(
                                          "COM_NXPEASYCART_ONBOARDING_REVISIT",
                                          "Review",
                                          [],
                                          `${step.id}-action`
                                      )
                                    : __(
                                          "COM_NXPEASYCART_ONBOARDING_START",
                                          "Start",
                                          [],
                                          `${step.id}-action`
                                      )
                            }}
                        </button>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    steps: {
        type: Array,
        default: () => [],
    },
    translate: {
        type: Function,
        required: true,
    },
    titleKey: {
        type: String,
        default: "COM_NXPEASYCART_ONBOARDING_TITLE",
    },
    leadKey: {
        type: String,
        default: "COM_NXPEASYCART_ONBOARDING_LEAD",
    },
});

const __ = props.translate;

const progress = computed(() => {
    if (!props.steps.length) {
        return 0;
    }

    const completed = props.steps.filter((step) => step.completed).length;
    return (completed / props.steps.length) * 100;
});

const progressPercent = computed(() => `${progress.value}%`);

const progressLabel = computed(() => {
    const completed = props.steps.filter((step) => step.completed).length;
    return __(
        "COM_NXPEASYCART_ONBOARDING_PROGRESS",
        "%s of %s complete",
        [String(completed), String(props.steps.length)],
        "onboardingProgress"
    );
});
</script>

<style scoped>
.nxp-ec-onboarding {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: grid;
    place-items: center;
}

.nxp-ec-onboarding__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
}

.nxp-ec-onboarding__panel {
    position: relative;
    background: #fff;
    border-radius: 1rem;
    padding: 2.5rem;
    width: min(720px, calc(100vw - 2rem));
    max-height: calc(100vh - 4rem);
    overflow-y: auto;
    box-shadow: 0 32px 80px rgba(15, 23, 42, 0.2);
}

.nxp-ec-onboarding__header {
    display: flex;
    justify-content: space-between;
    gap: 1.5rem;
    align-items: flex-start;
    margin-bottom: 1.75rem;
}

.nxp-ec-onboarding__title {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
}

.nxp-ec-onboarding__lead {
    margin: 0.5rem 0 0;
    color: #475467;
}

.nxp-ec-onboarding__progress {
    margin-bottom: 1.75rem;
    display: grid;
    gap: 0.75rem;
}

.nxp-ec-onboarding__progress-bar {
    background: #e9ecef;
    border-radius: 999px;
    height: 10px;
    overflow: hidden;
}

.nxp-ec-onboarding__progress-bar span {
    display: block;
    height: 100%;
    background: #0d6efd;
    transition: width 0.3s ease;
}

.nxp-ec-onboarding__progress-label {
    font-size: 0.9rem;
    color: #475467;
}

.nxp-ec-onboarding__steps {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 1.25rem;
}

.nxp-ec-onboarding__steps li {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    align-items: center;
}

.nxp-ec-onboarding__steps li.is-complete {
    border-color: rgba(13, 110, 253, 0.4);
    background: rgba(13, 110, 253, 0.05);
}

.nxp-ec-onboarding__step-main {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.nxp-ec-onboarding__step-main h3 {
    margin: 0 0 0.35rem;
    font-size: 1.1rem;
}

.nxp-ec-onboarding__step-main p {
    margin: 0;
    color: #667085;
    max-width: 32rem;
}

.nxp-ec-onboarding__step-icon {
    font-size: 1.5rem;
    color: #0d6efd;
    width: 2rem;
    display: grid;
    place-items: center;
}

.nxp-ec-onboarding__step-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

@media (max-width: 640px) {
    .nxp-ec-onboarding__panel {
        padding: 1.5rem;
        width: calc(100vw - 1.5rem);
    }

    .nxp-ec-onboarding__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .nxp-ec-onboarding__steps li {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
