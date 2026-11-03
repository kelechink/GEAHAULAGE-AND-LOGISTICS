package ng.gea.driver.core

import java.util.UUID

/**
 * Everything the app sends goes through a durable outbox first, so a driver with no network loses nothing
 * (PRD 13: offline queue). Each item carries a client_event_id; the server ignores a resend.
 */
enum class OutboxKind { POSITION, STAGE, DELIVERY, ISSUE }

data class OutboxItem(
    val clientEventId: String,
    val kind: OutboxKind,
    val tripId: Long,
    val createdAtEpochS: Long,
    val payloadJson: String,
    val attempts: Int = 0,
    val nextAttemptAtEpochS: Long = 0,
)

object ClientEventIds {
    fun new(): String = UUID.randomUUID().toString()
}

/** Exponential backoff for a failed send: 30 s, 1, 2, 4 … capped at 30 minutes. */
object RetryPolicy {
    private const val BASE_S = 30L
    private const val CAP_S = 30L * 60

    fun delaySeconds(attempts: Int): Long {
        if (attempts <= 0) return 0
        val shift = (attempts - 1).coerceAtMost(16)
        return (BASE_S shl shift).coerceAtMost(CAP_S)
    }

    /** 4xx answers (except 408/429) will never succeed on retry; the item is parked for the office to see. */
    fun isPermanent(httpStatus: Int): Boolean = httpStatus in 400..499 && httpStatus != 408 && httpStatus != 429
}

/**
 * Picks what to send next. Stage changes, deliveries and issues go one by one in the order they happened;
 * positions go in batches (the server takes up to 500). Positions for a trip are sent before that trip's
 * later stage events so the map and timeline line up.
 */
class SyncPlanner(private val maxBatch: Int = 200) {
    sealed interface Step {
        data class Positions(val items: List<OutboxItem>) : Step
        data class Single(val item: OutboxItem) : Step
    }

    fun plan(queue: List<OutboxItem>, nowEpochS: Long): List<Step> {
        val due = queue.filter { it.nextAttemptAtEpochS <= nowEpochS }.sortedBy { it.createdAtEpochS }
        val steps = mutableListOf<Step>()
        val pending = mutableListOf<OutboxItem>()
        fun flush() {
            pending.chunked(maxBatch).forEach { steps += Step.Positions(it) }
            pending.clear()
        }
        for (item in due) {
            if (item.kind == OutboxKind.POSITION) {
                pending += item
            } else {
                flush()
                steps += Step.Single(item)
            }
        }
        flush()
        return steps
    }
}
