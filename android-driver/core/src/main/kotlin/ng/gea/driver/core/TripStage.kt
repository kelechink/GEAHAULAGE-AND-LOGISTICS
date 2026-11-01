package ng.gea.driver.core

/**
 * Mirrors the server's DriverApp::DRIVER_STEPS. A driver moves a trip one step at a time;
 * cancelling and closing stay with the office.
 */
enum class TripStage(val wire: String, val label: String) {
    BOOKED("booked", "Booked"),
    ASSIGNED("assigned", "Assigned"),
    LOADING("loading", "Loading"),
    LOADED("loaded", "Loaded"),
    IN_TRANSIT("in_transit", "In transit"),
    DELAYED("delayed", "Delayed"),
    ARRIVED("arrived", "Arrived"),
    DELIVERED("delivered", "Delivered"),
    CLOSED("closed", "Closed"),
    CANCELLED("cancelled", "Cancelled");

    /** The stage the driver's big button moves the trip to, or null when there is nothing to do. */
    fun next(): TripStage? = when (this) {
        ASSIGNED -> LOADING
        LOADING -> LOADED
        LOADED -> IN_TRANSIT
        IN_TRANSIT -> ARRIVED
        DELAYED -> IN_TRANSIT
        ARRIVED -> DELIVERED
        else -> null
    }

    /** Button text for the next step, written for drivers. */
    fun actionLabel(): String? = when (next()) {
        LOADING -> "Start loading"
        LOADED -> "Loading done"
        IN_TRANSIT -> if (this == DELAYED) "Moving again" else "Start trip"
        ARRIVED -> "I have arrived"
        DELIVERED -> "Confirm delivery"
        else -> null
    }

    /** GPS is sent while the truck is on the road or waiting at the destination. */
    val tracksLocation: Boolean get() = this in setOf(LOADED, IN_TRANSIT, DELAYED, ARRIVED)

    /** Delivery needs the proof screen (photo or signature), not a plain stage call. */
    val needsProof: Boolean get() = next() == DELIVERED

    companion object {
        fun fromWire(value: String): TripStage =
            entries.firstOrNull { it.wire == value } ?: throw IllegalArgumentException("Unknown stage: $value")
    }
}
