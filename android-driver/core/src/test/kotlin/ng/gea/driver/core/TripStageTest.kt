package ng.gea.driver.core

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

class TripStageTest {
    @Test
    fun `driver walks a trip from assigned to delivered one step at a time`() {
        val path = generateSequence(TripStage.ASSIGNED) { it.next() }.toList()
        assertEquals(
            listOf(TripStage.ASSIGNED, TripStage.LOADING, TripStage.LOADED, TripStage.IN_TRANSIT, TripStage.ARRIVED, TripStage.DELIVERED),
            path,
        )
    }

    @Test
    fun `a delayed trip resumes in transit and office-only stages have no button`() {
        assertEquals(TripStage.IN_TRANSIT, TripStage.DELAYED.next())
        assertEquals("Moving again", TripStage.DELAYED.actionLabel())
        listOf(TripStage.BOOKED, TripStage.DELIVERED, TripStage.CLOSED, TripStage.CANCELLED).forEach {
            assertNull(it.next())
            assertNull(it.actionLabel())
        }
    }

    @Test
    fun `only the arrived step needs proof, and GPS runs from loaded to arrived`() {
        assertTrue(TripStage.ARRIVED.needsProof)
        assertFalse(TripStage.IN_TRANSIT.needsProof)
        assertTrue(TripStage.IN_TRANSIT.tracksLocation)
        assertFalse(TripStage.ASSIGNED.tracksLocation)
        assertFalse(TripStage.DELIVERED.tracksLocation)
    }

    @Test
    fun `wire values round trip`() {
        TripStage.entries.forEach { assertEquals(it, TripStage.fromWire(it.wire)) }
    }
}
