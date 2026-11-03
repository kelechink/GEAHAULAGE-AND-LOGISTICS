package ng.gea.driver.core

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertTrue

class LocationPolicyTest {
    private val policy = LocationPolicy()
    private val t0 = 1_700_000_000L

    @Test
    fun `sixty seconds while moving, five minutes while parked`() {
        assertEquals(60, policy.intervalSeconds(62.0))
        assertEquals(300, policy.intervalSeconds(0.0))
        assertEquals(300, policy.intervalSeconds(null))
    }

    @Test
    fun `drops poor or null-island fixes`() {
        assertFalse(policy.shouldKeep(Fix(6.5, 3.3, t0, accuracyM = 900.0), null))
        assertFalse(policy.shouldKeep(Fix(0.0, 0.0, t0), null))
        assertTrue(policy.shouldKeep(Fix(6.5, 3.3, t0, accuracyM = 20.0), null))
    }

    @Test
    fun `parked truck sends one fix per interval unless it moves`() {
        val last = Fix(6.5, 3.3, t0)
        assertFalse(policy.shouldKeep(Fix(6.5001, 3.3, t0 + 60), last))
        assertTrue(policy.shouldKeep(Fix(6.5001, 3.3, t0 + 300), last))
        assertTrue(policy.shouldKeep(Fix(6.501, 3.3, t0 + 60), last)) // about 110 m
    }

    @Test
    fun `moving truck sends roughly every minute`() {
        val last = Fix(6.5, 3.3, t0, speedKmh = 60.0)
        assertFalse(policy.shouldKeep(Fix(6.51, 3.3, t0 + 20, speedKmh = 60.0), last))
        assertTrue(policy.shouldKeep(Fix(6.52, 3.3, t0 + 60, speedKmh = 60.0), last))
    }

    @Test
    fun `distance is about right for Lagos to Ibadan`() {
        val km = LocationPolicy.distanceMetres(Fix(6.5244, 3.3792, 0), Fix(7.3775, 3.9470, 0)) / 1000
        assertTrue(km in 110.0..120.0, "got $km")
    }
}
