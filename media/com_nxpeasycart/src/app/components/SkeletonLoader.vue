<template>
    <div class="nxp-ec-skeleton" :class="skeletonClasses">
        <div
            v-if="type === 'text'"
            class="nxp-ec-skeleton__line"
            :style="{ width: width }"
        ></div>
        <div v-else-if="type === 'table'" class="nxp-ec-skeleton__table">
            <div
                v-for="row in rows"
                :key="row"
                class="nxp-ec-skeleton__table-row"
            >
                <div
                    v-for="col in columns"
                    :key="col"
                    class="nxp-ec-skeleton__table-cell"
                ></div>
            </div>
        </div>
        <div v-else-if="type === 'card'" class="nxp-ec-skeleton__card">
            <div class="nxp-ec-skeleton__card-header"></div>
            <div class="nxp-ec-skeleton__card-body">
                <div class="nxp-ec-skeleton__line"></div>
                <div class="nxp-ec-skeleton__line" style="width: 80%"></div>
                <div class="nxp-ec-skeleton__line" style="width: 60%"></div>
            </div>
        </div>
        <div v-else-if="type === 'circle'" class="nxp-ec-skeleton__circle"></div>
        <div
            v-else
            class="nxp-ec-skeleton__rectangle"
            :style="{ width: width, height: height }"
        ></div>
    </div>
</template>

<script>
export default {
    name: "SkeletonLoader",
    props: {
        type: {
            type: String,
            default: "text",
            validator: (value) =>
                ["text", "rectangle", "circle", "card", "table"].includes(
                    value
                ),
        },
        width: {
            type: String,
            default: "100%",
        },
        height: {
            type: String,
            default: "20px",
        },
        rows: {
            type: Number,
            default: 3,
        },
        columns: {
            type: Number,
            default: 4,
        },
        animated: {
            type: Boolean,
            default: true,
        },
    },
    computed: {
        skeletonClasses() {
            return {
                "nxp-ec-skeleton--animated": this.animated,
                [`nxp-ec-skeleton--${this.type}`]: true,
            };
        },
    },
};
</script>

<style scoped>
.nxp-ec-skeleton {
    display: block;
    background: transparent;
}

.nxp-ec-skeleton--animated .nxp-ec-skeleton__line,
.nxp-ec-skeleton--animated .nxp-ec-skeleton__rectangle,
.nxp-ec-skeleton--animated .nxp-ec-skeleton__circle,
.nxp-ec-skeleton--animated .nxp-ec-skeleton__card-header,
.nxp-ec-skeleton--animated .nxp-ec-skeleton__table-cell {
    animation: nxp-ec-skeleton-pulse 1.5s ease-in-out infinite;
}

@keyframes nxp-ec-skeleton-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.4;
    }
}

.nxp-ec-skeleton__line {
    height: 1em;
    margin: 0.5em 0;
    background: #e4e7ec;
    border-radius: 4px;
}

.nxp-ec-skeleton__rectangle {
    background: #e4e7ec;
    border-radius: 4px;
}

.nxp-ec-skeleton__circle {
    width: 40px;
    height: 40px;
    background: #e4e7ec;
    border-radius: 50%;
}

.nxp-ec-skeleton__card {
    border: 1px solid #e4e7ec;
    border-radius: 6px;
    padding: 1rem;
    background: #fff;
}

.nxp-ec-skeleton__card-header {
    height: 24px;
    background: #e4e7ec;
    border-radius: 4px;
    margin-bottom: 1rem;
    width: 60%;
}

.nxp-ec-skeleton__card-body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.nxp-ec-skeleton__table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.nxp-ec-skeleton__table-row {
    display: grid;
    grid-template-columns: repeat(var(--columns, 4), 1fr);
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.nxp-ec-skeleton__table-row:first-child {
    border-top: 1px solid #f3f4f6;
}

.nxp-ec-skeleton__table-cell {
    height: 20px;
    background: #e4e7ec;
    border-radius: 4px;
}
</style>
