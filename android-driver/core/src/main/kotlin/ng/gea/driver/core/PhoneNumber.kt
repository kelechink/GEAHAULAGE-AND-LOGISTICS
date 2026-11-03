package ng.gea.driver.core

/** Same rule as the server's PhoneNumber::normalise: Nigerian numbers become 234XXXXXXXXXX. */
object PhoneNumber {
    fun normalise(raw: String): String? {
        val digits = raw.filter { it.isDigit() }
        return when {
            digits.length == 13 && digits.startsWith("234") -> digits
            digits.length == 11 && digits.startsWith("0") -> "234" + digits.substring(1)
            digits.length == 10 && digits[0] in "789" -> "234$digits"
            else -> null
        }
    }

    fun isValidPin(pin: String): Boolean = pin.length in 4..6 && pin.all { it.isDigit() }
}
