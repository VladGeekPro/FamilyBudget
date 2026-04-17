<div
    wire:ignore
    x-data="window.expenseVoiceRecorder({
        mode: @js($mode),
        transcriptionUrl: @js($transcriptionUrl),
        appliedTitle: @js(__('resources.voice.notifications.applied_title')),
        appliedBody: @js(__('resources.voice.notifications.applied_body.' . $mode)),
        failedTitle: @js(__('resources.voice.notifications.failed_title')),
        unsupportedTitle: @js(__('resources.voice.notifications.unsupported_title')),
        unsupportedBody: @js(__('resources.voice.notifications.unsupported_body')),
        cancelLabel: @js(__('resources.voice.actions.cancel')),
        processingLabel: @js(__('resources.voice.status.processing')),
        readyLabel: @js(__('resources.voice.status.ready')),
        recordingLabel: @js(__('resources.voice.status.recording')),
        idleLabel: @js(__('resources.voice.status.idle')),
    })"
    x-on:close-modal.window="handleModalClose($event)"
    x-on:close-modal-quietly.window="handleModalClose($event)"
    class="expense-voice-modal-body"
>
    <div class="expense-voice-shell" :class="{ 'is-recording': isRecording, 'is-processing': isSaving }">
        <div class="expense-voice-hero">
            <button
                type="button"
                class="expense-voice-mic"
                :disabled="isSaving || ! supported"
                x-on:click.prevent.stop="pressButton('mic', $event); toggleRecording()"
            >
                <x-filament::icon
                    icon="heroicon-o-microphone"
                    class="expense-voice-mic-icon"
                />
            </button>

            <div class="expense-voice-status-block">
                <p class="expense-voice-status" x-text="statusLabel"></p>
                <p class="expense-voice-timer" x-text="formattedDuration"></p>
            </div>
        </div>

        <div class="expense-voice-visualizer">
            <canvas x-ref="canvas" class="expense-voice-canvas"></canvas>
        </div>

        <div class="expense-voice-helper-text">
            <p>{{ __('resources.voice.helper') }}</p>
        </div>

        <div class="expense-voice-transcript">
            <p class="expense-voice-transcript-label">{{ __('resources.voice.transcript') }}</p>
            <p class="expense-voice-transcript-text" x-text="transcript || previewText"></p>
        </div>

        <div class="expense-voice-actions">
            <button
                type="button"
                class="expense-voice-action-btn expense-voice-action-btn-cancel"
                :class="buttonClass('cancel')"
                :disabled="isSaving"
                @click="handleCancelClick()"
            >
                {{ __('resources.voice.actions.cancel') }}
            </button>

            <button
                type="button"
                class="expense-voice-action-btn expense-voice-action-btn-retry"
                :class="buttonClass('retry')"
                :disabled="isSaving || (! audioBlob && ! isRecording && ! transcript)"
                @click="handleRetryClick()"
            >
                {{ __('resources.voice.actions.retry') }}
            </button>

            <button
                type="button"
                class="expense-voice-action-btn expense-voice-action-btn-save"
                :class="buttonClass('save')"
                :disabled="isSaving || isRecording || ! audioBlob"
                @click="handleSaveClick()"
            >
                {{ __('resources.voice.actions.save') }}
            </button>
        </div>
    </div>
</div>