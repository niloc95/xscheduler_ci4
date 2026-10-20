/**
 * Background refresh loop for SchedulerCore: passive polling that pauses while
 * the user interacts or an interactive request is in flight, with auth back-off.
 *
 * Mixed into SchedulerCore.prototype — methods use `this` as the scheduler.
 */

import { withBaseUrl } from '../../utils/url-helpers.js';

export const backgroundPollMethods = {
    scheduleBackgroundPoll(delay = this.backgroundPollInterval) {
        if (!this.backgroundPollingEnabled || this.isDestroyed) {
            return;
        }

        if (this.backgroundPollTimer) {
            clearTimeout(this.backgroundPollTimer);
        }

        this.backgroundPollTimer = setTimeout(() => {
            this.backgroundPollTimer = null;
            void this.runBackgroundPoll();
        }, delay);
    },

    async runBackgroundPoll() {
        if (!this.backgroundPollingEnabled || this.isDestroyed) {
            return;
        }

        if (!document.getElementById(this.containerId)) {
            this.stopBackgroundPolling();
            return;
        }

        if (document.hidden || this.isInteractionActive() || this.hasActiveInteractiveRequest()) {
            this.scheduleBackgroundPoll(this.backgroundPollInterval);
            return;
        }

        try {
            await this.refreshAndRender({
                passive: true,
                preserveOnError: true,
                throwOnError: true,
                reason: 'background-poll',
            });

            this.backgroundPollAuthFailures = 0;
            this.scheduleBackgroundPoll(this.backgroundPollInterval);
        } catch (error) {
            if (error?.name === 'AbortError') {
                if (this.backgroundPollingEnabled && !this.isDestroyed) {
                    this.scheduleBackgroundPoll(this.backgroundPollInterval);
                }
                return;
            }

            if (error?.status === 401) {
                this.backgroundPollAuthFailures += 1;

                if (this.backgroundPollAuthFailures < this.backgroundPollMaxAuthRetries) {
                    console.warn(
                        `[Scheduler] Background polling unauthorized; retrying shortly (${this.backgroundPollAuthFailures}/${this.backgroundPollMaxAuthRetries - 1})`
                    );
                    this.scheduleBackgroundPoll(this.backgroundPollAuthRetryDelay);
                    return;
                }

                this.stopBackgroundPolling();
                window.location.href = withBaseUrl('/auth/login');
                return;
            }

            console.error('[Scheduler] Background refresh failed:', error);
            this.scheduleBackgroundPoll(this.backgroundPollRetryDelay);
        }
    },

    startBackgroundPolling() {
        this.backgroundPollingEnabled = true;
        this.backgroundPollAuthFailures = 0;
        this.scheduleBackgroundPoll(this.backgroundPollInterval);
    },

    stopBackgroundPolling() {
        this.backgroundPollingEnabled = false;

        if (this.backgroundPollTimer) {
            clearTimeout(this.backgroundPollTimer);
            this.backgroundPollTimer = null;
        }

        this.abortActiveDataRequest({ passiveOnly: true });
    },

    triggerBackgroundRefresh() {
        if (!this.backgroundPollingEnabled || this.isDestroyed) {
            return;
        }

        if (this.backgroundPollTimer) {
            clearTimeout(this.backgroundPollTimer);
            this.backgroundPollTimer = null;
        }

        void this.runBackgroundPoll();
    }
};
