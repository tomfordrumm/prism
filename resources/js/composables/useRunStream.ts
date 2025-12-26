import { onBeforeUnmount, ref, type Ref } from 'vue';

type StreamRun = {
    id: number;
    status?: string | null;
};

type StreamPayload<TRun, TStep> = {
    run?: TRun;
    steps?: TStep[];
};

const isLiveStatus = (status?: string | null) =>
    ['pending', 'running'].includes(status ?? '');

export const useRunStream = <TRun extends StreamRun, TStep>(options: {
    projectUuid: string;
    run: Ref<TRun>;
    steps: Ref<TStep[]>;
}) => {
    const { projectUuid, run, steps } = options;
    const eventSource = ref<EventSource | null>(null);

    const stopStream = () => {
        eventSource.value?.close();
        eventSource.value = null;
    };

    const startStream = () => {
        if (typeof window === 'undefined') return;
        if (!isLiveStatus(run.value.status)) return;

        const streamUrl = `/projects/${projectUuid}/runs/${run.value.id}/stream`;

        if (eventSource.value) {
            eventSource.value.close();
        }

        const es = new EventSource(streamUrl);
        eventSource.value = es;

        es.onmessage = (event) => {
            try {
                const payload = JSON.parse(event.data || '{}') as StreamPayload<
                    TRun,
                    TStep
                >;
                if (payload.run) {
                    run.value = payload.run;
                }
                if (payload.steps) {
                    steps.value = payload.steps;
                }

                if (!isLiveStatus(payload.run?.status ?? run.value.status)) {
                    es.close();
                    eventSource.value = null;
                }
            } catch (error) {
                console.error('Failed to parse run stream payload', error);
            }
        };

        es.onerror = () => {
            es.close();
            eventSource.value = null;
        };
    };

    onBeforeUnmount(stopStream);

    return {
        eventSource,
        isLiveStatus,
        startStream,
        stopStream,
    };
};
