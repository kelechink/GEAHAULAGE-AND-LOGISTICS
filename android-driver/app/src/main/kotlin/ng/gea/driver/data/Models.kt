package ng.gea.driver.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LoginRequest(val phone: String, val pin: String, @SerialName("device_name") val deviceName: String)

@Serializable
data class LoginResponse(val token: String, val driver: DriverProfile)

@Serializable
data class MeResponse(val driver: DriverProfile)

@Serializable
data class DriverProfile(
    val id: Long,
    val name: String,
    val phone: String,
    val truck: String? = null,
    @SerialName("location_interval_moving_s") val movingIntervalS: Long = 60,
    @SerialName("location_interval_parked_s") val parkedIntervalS: Long = 300,
    @SerialName("operations_phone") val operationsPhone: String? = null,
)

@Serializable
data class Trip(
    val id: Long,
    @SerialName("tracking_code") val trackingCode: String,
    val stage: String,
    @SerialName("stage_label") val stageLabel: String,
    @SerialName("next_stage") val nextStage: String? = null,
    @SerialName("next_stage_label") val nextStageLabel: String? = null,
    val customer: String,
    val cargo: String,
    val tonnage: String? = null,
    val origin: String,
    @SerialName("origin_lat") val originLat: Double? = null,
    @SerialName("origin_lng") val originLng: Double? = null,
    val destination: String,
    @SerialName("destination_lat") val destinationLat: Double? = null,
    @SerialName("destination_lng") val destinationLng: Double? = null,
    @SerialName("pickup_date") val pickupDate: String? = null,
    @SerialName("waybill_number") val waybillNumber: String? = null,
    val truck: String? = null,
    val eta: String? = null,
    @SerialName("needs_proof") val needsProof: Boolean = false,
    @SerialName("delivered_at") val deliveredAt: String? = null,
    /** Local only: an action is queued and not yet confirmed by the server. */
    val pendingSync: Boolean = false,
)

@Serializable
data class TripsResponse(val trips: List<Trip>)

@Serializable
data class TripResponse(val trip: Trip)

@Serializable
data class PositionPayload(
    @SerialName("client_event_id") val clientEventId: String,
    @SerialName("shipment_id") val shipmentId: Long,
    val lat: Double,
    val lng: Double,
    @SerialName("recorded_at") val recordedAt: String,
    @SerialName("speed_kmh") val speedKmh: Double? = null,
    @SerialName("accuracy_m") val accuracyM: Double? = null,
    val heading: Double? = null,
)

@Serializable
data class PositionBatch(val positions: List<PositionPayload>)

@Serializable
data class PositionResult(val accepted: Int, val duplicates: Int, val rejected: Int)

@Serializable
data class StagePayload(
    @SerialName("client_event_id") val clientEventId: String,
    val stage: String,
    val note: String? = null,
    val lat: Double? = null,
    val lng: Double? = null,
)

/** Stored in the outbox; files are sent as multipart when the network is back. */
@Serializable
data class DeliveryPayload(
    @SerialName("client_event_id") val clientEventId: String,
    @SerialName("receiver_name") val receiverName: String,
    val photoFile: String? = null,
    val signatureFile: String? = null,
    val lat: Double? = null,
    val lng: Double? = null,
)

@Serializable
data class IssuePayload(
    @SerialName("client_event_id") val clientEventId: String,
    val type: String,
    val note: String? = null,
    val photoFile: String? = null,
    val lat: Double? = null,
    val lng: Double? = null,
)
