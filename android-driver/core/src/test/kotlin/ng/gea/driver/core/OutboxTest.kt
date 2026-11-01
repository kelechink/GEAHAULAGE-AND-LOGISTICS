package ng.gea.driver.core

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertIs
import kotlin.test.assertNotEquals
import kotlin.test.assertTrue

class OutboxTest {
    private fun item(kind: OutboxKind, at: Long, next: Long = 0) =
        OutboxItem(ClientEventIds.new(), kind, tripId = 7, createdAtEpochS = at, payloadJson = "{}", nextAttemptAtEpochS = next)

    @Test
    fun `positions are batched and stage events keep their order`() {
        val q = listOf(
            item(OutboxKind.POSITION, 1), item(OutboxKind.POSITION, 2), item(OutboxKind.STAGE, 3),
            item(OutboxKind.POSITION, 4), item(OutboxKind.DELIVERY, 5),
        )
        val steps = SyncPlanner().plan(q.shuffled(), nowEpochS = 100)

        assertEquals(4, steps.size)
        assertEquals(2, assertIs<SyncPlanner.Step.Positions>(steps[0]).items.size)
        assertEquals(OutboxKind.STAGE, assertIs<SyncPlanner.Step.Single>(steps[1]).item.kind)
        assertEquals(1, assertIs<SyncPlanner.Step.Positions>(steps[2]).items.size)
        assertEquals(OutboxKind.DELIVERY, assertIs<SyncPlanner.Step.Single>(steps[3]).item.kind)
    }

    @Test
    fun `large position backlogs are split and items waiting on backoff are skipped`() {
        val q = (1..450).map { item(OutboxKind.POSITION, it.toLong()) } + item(OutboxKind.ISSUE, 500, next = 9_999)
        val steps = SyncPlanner(maxBatch = 200).plan(q, nowEpochS = 1_000)

        assertEquals(listOf(200, 200, 50), steps.map { (it as SyncPlanner.Step.Positions).items.size })
    }

    @Test
    fun `backoff grows and caps at thirty minutes`() {
        assertEquals(0, RetryPolicy.delaySeconds(0))
        assertEquals(30, RetryPolicy.delaySeconds(1))
        assertEquals(60, RetryPolicy.delaySeconds(2))
        assertEquals(1800, RetryPolicy.delaySeconds(50))
    }

    @Test
    fun `validation errors are permanent, timeouts and rate limits are not`() {
        assertTrue(RetryPolicy.isPermanent(422))
        assertFalse(RetryPolicy.isPermanent(429))
        assertFalse(RetryPolicy.isPermanent(408))
        assertFalse(RetryPolicy.isPermanent(503))
    }

    @Test
    fun `client event ids are unique`() {
        assertNotEquals(ClientEventIds.new(), ClientEventIds.new())
    }
}
