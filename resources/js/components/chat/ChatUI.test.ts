import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ChatUI from '@/components/chat/ChatUI.vue';

type ChatEnterBehavior = 'send' | 'newline';

const jsonResponse = (payload: unknown, ok = true): Response =>
    ({
        ok,
        json: async () => payload,
    }) as Response;

let chatEnterBehavior: ChatEnterBehavior = 'send';

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<object>('@inertiajs/vue3');

    return {
        ...actual,
        usePage: vi.fn(() => ({
            props: {
                auth: {
                    user: {
                        chat_enter_behavior: chatEnterBehavior,
                    },
                },
            },
        })),
    };
});

vi.mock('primevue/usetoast', () => ({
    useToast: () => ({
        add: vi.fn(),
    }),
}));

vi.mock('primevue/button', () => ({
    default: defineComponent({
        name: 'PrimeButtonMock',
        emits: ['click'],
        props: {
            disabled: {
                type: Boolean,
                default: false,
            },
            type: {
                type: String,
                default: 'button',
            },
        },
        template:
            '<button :disabled="disabled" :type="type" @click="$emit(\'click\', $event)"><slot /></button>',
    }),
}));

vi.mock('@/routes/projects/agents/conversations/messages', () => ({
    store: {
        url: () => '/projects/project-uuid/agents/1/conversations/10/messages',
    },
    retry: {
        url: () => '/projects/project-uuid/agents/1/conversations/10/messages/1/retry',
    },
}));

vi.mock('@/routes/projects/prompt-conversations/messages', () => ({
    retry: {
        url: () => '/projects/project-uuid/prompt-conversations/10/messages/1/retry',
    },
}));

vi.mock('@/composables/usePromptDiff', () => ({
    usePromptDiff: () => ({
        viewMode: { value: 'diff' },
        diffLines: { value: [] },
        diffLineSymbol: () => ' ',
        hasSuggestion: { value: false },
        setViewMode: vi.fn(),
    }),
}));

const mountChat = () =>
    mount(ChatUI, {
        props: {
            projectUuid: 'project-uuid',
            type: 'agent_chat',
            agentId: 1,
            active: false,
            showHeader: false,
            showWelcome: false,
        },
        global: {
            stubs: {
                Icon: true,
            },
        },
    });

describe('ChatUI keyboard behavior', () => {
    beforeEach(() => {
        chatEnterBehavior = 'send';
        vi.unstubAllGlobals();
    });

    it('shows hint for send mode', () => {
        const wrapper = mountChat();

        expect(wrapper.text()).toContain(
            'Enter sends, Ctrl/Cmd+Enter inserts newline'
        );
    });

    it('shows hint for newline mode', () => {
        chatEnterBehavior = 'newline';
        const wrapper = mountChat();

        expect(wrapper.text()).toContain(
            'Enter inserts newline, Ctrl/Cmd+Enter sends'
        );
    });

    it('sends on Enter in send mode', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    conversation: {
                        id: 10,
                        type: 'agent_chat',
                        status: 'active',
                        created_at: '2026-02-20T00:00:00.000Z',
                        updated_at: '2026-02-20T00:00:00.000Z',
                    },
                    messages: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    user_message: {
                        id: 1,
                        role: 'user',
                        content: 'Hello',
                    },
                    assistant_message: {
                        id: 2,
                        role: 'assistant',
                        content: 'Hi',
                    },
                })
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter' });
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not send on Ctrl/Cmd+Enter in send mode', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter', ctrlKey: true });
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('sends on Ctrl+Enter in newline mode', async () => {
        chatEnterBehavior = 'newline';
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    conversation: {
                        id: 10,
                        type: 'agent_chat',
                        status: 'active',
                        created_at: '2026-02-20T00:00:00.000Z',
                        updated_at: '2026-02-20T00:00:00.000Z',
                    },
                    messages: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    user_message: {
                        id: 1,
                        role: 'user',
                        content: 'Hello',
                    },
                    assistant_message: {
                        id: 2,
                        role: 'assistant',
                        content: 'Hi',
                    },
                })
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter', ctrlKey: true });
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('sends on Cmd+Enter in newline mode', async () => {
        chatEnterBehavior = 'newline';
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    conversation: {
                        id: 10,
                        type: 'agent_chat',
                        status: 'active',
                        created_at: '2026-02-20T00:00:00.000Z',
                        updated_at: '2026-02-20T00:00:00.000Z',
                    },
                    messages: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    user_message: {
                        id: 1,
                        role: 'user',
                        content: 'Hello',
                    },
                    assistant_message: {
                        id: 2,
                        role: 'assistant',
                        content: 'Hi',
                    },
                })
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter', metaKey: true });
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not send on Enter in newline mode', async () => {
        chatEnterBehavior = 'newline';
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter' });
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('does not send while IME composition is active', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountChat();
        const textarea = wrapper.get('#prompt_idea_input');

        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter', isComposing: true });
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
