// polysource/bulk-async — Stimulus controller for the live progress card.
//
// Two transports (cf. ADR-024 §9):
//   - Mercure: when `mercureUrlValue` is set, opens an EventSource on
//     the topic and never polls.
//   - Polling fallback: when no Mercure URL, fetches `urlValue` every
//     `intervalValue` ms.
//
// In both cases the controller normalises the same JSON payload
// shape (id, status, processed, failed, total, progress, startedAt,
// completedAt, errorMessage) into the existing markup. It stops on
// terminal status (completed / failed / cancelled).

import { Controller } from "@hotwired/stimulus";

const TERMINAL_STATUSES = new Set(["completed", "failed", "cancelled"]);
const STATUS_BADGE = {
    pending: "warning",
    running: "primary",
    completed: "success",
    failed: "danger",
    cancelled: "secondary",
};

export default class extends Controller {
    static values = {
        url: String,
        mercureUrl: String,
        interval: { type: Number, default: 2000 },
        payload: Object,
    };

    static targets = ["status", "counts", "eta", "bar", "error"];

    connect() {
        this.startedAtMs = Date.now();
        if (this.payloadValue && Object.keys(this.payloadValue).length > 0) {
            this.render(this.payloadValue);
            if (this.isTerminal(this.payloadValue.status)) return;
        }

        if (this.hasMercureUrlValue && this.mercureUrlValue) {
            this.openMercure();
        } else {
            this.startPolling();
        }
    }

    disconnect() {
        this.stopPolling();
        this.closeMercure();
    }

    openMercure() {
        try {
            this.eventSource = new EventSource(this.mercureUrlValue);
        } catch (e) {
            // EventSource construction failed (CSP, malformed URL) —
            // silently fall back to polling rather than breaking the UI.
            this.startPolling();
            return;
        }
        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.render(data);
                if (this.isTerminal(data.status)) this.closeMercure();
            } catch (_) {
                // Malformed event — ignore, keep listening.
            }
        };
        this.eventSource.onerror = () => {
            // Mercure dropped us — switch to polling so we still
            // converge on the terminal state.
            this.closeMercure();
            this.startPolling();
        };
    }

    closeMercure() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    startPolling() {
        if (this.pollHandle) return;
        this.pollHandle = window.setInterval(() => this.poll(), this.intervalValue);
    }

    stopPolling() {
        if (this.pollHandle) {
            window.clearInterval(this.pollHandle);
            this.pollHandle = null;
        }
    }

    async poll() {
        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            if (!response.ok) {
                if (response.status === 404) this.stopPolling();
                return;
            }
            const data = await response.json();
            this.render(data);
            if (this.isTerminal(data.status)) this.stopPolling();
        } catch (_) {
            // Network blip — let the next interval retry.
        }
    }

    render(data) {
        const pct = Math.round((data.progress ?? 0) * 1000) / 10;
        if (this.hasBarTarget) {
            this.barTarget.style.width = pct + "%";
            this.barTarget.parentElement.setAttribute("aria-valuenow", pct);
            const isTerminal = this.isTerminal(data.status);
            this.barTarget.classList.toggle("progress-bar-striped", !isTerminal);
            this.barTarget.classList.toggle("progress-bar-animated", !isTerminal);
            this.barTarget.classList.remove("bg-danger", "bg-secondary");
            if (data.status === "failed") this.barTarget.classList.add("bg-danger");
            if (data.status === "cancelled") this.barTarget.classList.add("bg-secondary");
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = (data.status || "").toUpperCase();
            const badgeClass = STATUS_BADGE[data.status] || "secondary";
            this.statusTarget.className = "badge bg-" + badgeClass;
        }
        if (this.hasCountsTarget) {
            const counts = `${data.processed ?? 0} / ${data.total ?? 0} records`;
            const failures = (data.failed ?? 0) > 0
                ? ` · ${data.failed} failed`
                : "";
            this.countsTarget.innerHTML = counts + failures;
        }
        if (this.hasEtaTarget && !this.isTerminal(data.status)) {
            this.etaTarget.textContent = this.computeEta(data);
        } else if (this.hasEtaTarget) {
            this.etaTarget.textContent = "";
        }
        if (this.hasErrorTarget && data.errorMessage) {
            this.errorTarget.textContent = data.errorMessage;
            this.errorTarget.classList.remove("d-none");
        }
    }

    computeEta(data) {
        const processed = data.processed ?? 0;
        const total = data.total ?? 0;
        if (processed <= 0 || total <= 0) return "…";
        const remaining = Math.max(0, total - processed);
        const elapsedMs = Math.max(1, Date.now() - this.startedAtMs);
        const msPerRecord = elapsedMs / processed;
        const etaSec = Math.round((remaining * msPerRecord) / 1000);
        return etaSec >= 60
            ? `~${Math.round(etaSec / 60)}m left`
            : `~${etaSec}s left`;
    }

    isTerminal(status) {
        return TERMINAL_STATUSES.has(status);
    }
}
