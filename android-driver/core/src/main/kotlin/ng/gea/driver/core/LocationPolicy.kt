package ng.gea.driver.core

import kotlin.math.asin
import kotlin.math.cos
import kotlin.math.pow
import kotlin.math.sin
import kotlin.math.sqrt

/**
 * How often the app records a fix (PRD 13): every 60 s while moving, every 5 min while parked.
 * Poor fixes are dropped and near-duplicate parked fixes are skipped to save data and battery.
 */
class LocationPolicy(
    val movingIntervalSeconds: Long = 60,
    val parkedIntervalSeconds: Long = 300,
    private val movingSpeedKmh: Double = 5.0,
    private val maxAccuracyMetres: Double = 200.0,
    private val parkedMinDistanceMetres: Double = 50.0,
) {
    fun intervalSeconds(speedKmh: Double?): Long =
        if ((speedKmh ?: 0.0) >= movingSpeedKmh) movingIntervalSeconds else parkedIntervalSeconds

    /** Decides whether a new fix is worth queueing given the last one we kept. */
    fun shouldKeep(fix: Fix, last: Fix?): Boolean {
        if (fix.accuracyM != null && fix.accuracyM > maxAccuracyMetres) return false
        if (fix.lat == 0.0 && fix.lng == 0.0) return false
        if (last == null) return true
        val elapsed = fix.recordedAtEpochS - last.recordedAtEpochS
        if (elapsed <= 0) return false
        val moving = (fix.speedKmh ?: 0.0) >= movingSpeedKmh
        if (moving) return elapsed >= movingIntervalSeconds - 5
        // Parked: keep one fix per parked interval, or sooner if the truck has actually moved.
        return elapsed >= parkedIntervalSeconds - 5 || distanceMetres(fix, last) >= parkedMinDistanceMetres
    }

    companion object {
        fun distanceMetres(a: Fix, b: Fix): Double {
            val r = 6_371_000.0
            val dLat = Math.toRadians(b.lat - a.lat)
            val dLng = Math.toRadians(b.lng - a.lng)
            val h = sin(dLat / 2).pow(2) + cos(Math.toRadians(a.lat)) * cos(Math.toRadians(b.lat)) * sin(dLng / 2).pow(2)
            return 2 * r * asin(sqrt(h))
        }
    }
}

data class Fix(
    val lat: Double,
    val lng: Double,
    val recordedAtEpochS: Long,
    val speedKmh: Double? = null,
    val accuracyM: Double? = null,
    val heading: Double? = null,
)
