package ng.gea.driver.core

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertTrue

class PhoneNumberTest {
    @Test
    fun `local and international formats match the server`() {
        assertEquals("2348031234567", PhoneNumber.normalise("0803 123 4567"))
        assertEquals("2348031234567", PhoneNumber.normalise("+234 803 123 4567"))
        assertEquals("2348031234567", PhoneNumber.normalise("8031234567"))
        assertNull(PhoneNumber.normalise("12345"))
    }

    @Test
    fun `pin is four to six digits`() {
        assertTrue(PhoneNumber.isValidPin("1234"))
        assertTrue(PhoneNumber.isValidPin("998877"))
        assertFalse(PhoneNumber.isValidPin("12a4"))
        assertFalse(PhoneNumber.isValidPin("123"))
    }
}
