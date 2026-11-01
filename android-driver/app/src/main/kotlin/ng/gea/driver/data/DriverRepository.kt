package ng.gea.driver.data

import android.content.Context
import android.os.Build
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.KSerializer
import ng.gea.driver.core.ClientEventIds
import ng.gea.driver.core.OutboxKind
import ng.gea.driver.core.PhoneNumber
import ng.gea.driver.core.TripStage
import ng.gea.driver.sync.SyncWorker
import retrofit2.HttpException

/**
 * Single entry point for the UI. Actions are written to the outbox first and the trip is updated on screen
 * straight away; SyncWorker delivers them when there is signal.
 */
class DriverRepository(private val context: Context, val session: Session, private val api: DriverApi, private val outbox: OutboxDao) {
    private val _trips = MutableStateFlow(session.cachedTrips)
    val trips: StateFlow<List<Trip>> = _trips

    val pendingCount: Flow<Int> = outbox.pendingCount()
    val failed: Flow<List<OutboxEntity>> = outbox.failed()

    suspend fun login(phone: String, pin: String): Result<DriverProfile> = runCatching {
        val normalised = PhoneNumber.normalise(phone) ?: error("Enter your phone number, e.g. 0803 123 4567.")
        require(PhoneNumber.isValidPin(pin)) { "Your PIN is 4 to 6 digits." }
        val res = try {
            api.login(LoginRequest(normalised, pin, Build.MODEL ?: "android"))
        } catch (e: HttpException) {
            throw IllegalStateException(
                when (e.code()) {
                    422 -> "Wrong phone number or PIN."
                    429 -> "Too many tries. Wait a minute and try again."
                    403 -> "Your app access is switched off. Call Operations."
                    else -> "Could not sign in (error ${e.code()})."
                },
            )
        }
        session.token = res.token
        session.profile = res.driver
        refreshTrips()
        res.driver
    }

    suspend fun logout() {
        runCatching { api.logout() }
        outbox.clearAll()
        session.clear()
        _trips.value = emptyList()
    }

    /** Pulls the trip list; trips with unsent actions keep their local stage until the server catches up. */
    suspend fun refreshTrips(): Result<Unit> = runCatching {
        val server = api.trips().trips
        val pending = outbox.tripsWithPendingActions().toSet()
        val local = _trips.value.associateBy { it.id }
        val merged = server.map { t -> if (t.id in pending) local[t.id] ?: t else t }
        publish(merged)
    }.onFailure { e ->
        if (e is HttpException && e.code() == 401) session.clear()
    }

    suspend fun advance(trip: Trip, note: String? = null, lat: Double? = null, lng: Double? = null) {
        val next = TripStage.fromWire(trip.stage).next() ?: return
        check(next != TripStage.DELIVERED) { "Use the delivery screen to confirm delivery." }
        val id = ClientEventIds.new()
        enqueue(OutboxKind.STAGE, trip.id, id, StagePayload(id, next.wire, note, lat, lng), StagePayload.serializer())
        updateLocal(trip.id, next)
    }

    suspend fun deliver(trip: Trip, receiver: String, photoFile: String?, signatureFile: String?, lat: Double?, lng: Double?) {
        require(photoFile != null || signatureFile != null) { "Take a photo or get a signature." }
        val id = ClientEventIds.new()
        enqueue(OutboxKind.DELIVERY, trip.id, id, DeliveryPayload(id, receiver, photoFile, signatureFile, lat, lng), DeliveryPayload.serializer())
        updateLocal(trip.id, TripStage.DELIVERED)
    }

    suspend fun reportIssue(trip: Trip, type: String, note: String?, photoFile: String?, lat: Double?, lng: Double?) {
        val id = ClientEventIds.new()
        enqueue(OutboxKind.ISSUE, trip.id, id, IssuePayload(id, type, note, photoFile, lat, lng), IssuePayload.serializer())
        if (type == "delay" && trip.stage == TripStage.IN_TRANSIT.wire) updateLocal(trip.id, TripStage.DELAYED)
    }

    suspend fun recordPosition(p: PositionPayload) {
        outbox.add(
            OutboxEntity(p.clientEventId, OutboxKind.POSITION.name, p.shipmentId, System.currentTimeMillis() / 1000,
                DriverApi.json.encodeToString(PositionPayload.serializer(), p)),
        )
    }

    /** The trip GPS should follow: the one on the road, else the one waiting at the destination. */
    fun activeTrip(): Trip? = _trips.value.firstOrNull { TripStage.fromWire(it.stage).tracksLocation }

    suspend fun dismissFailed() = outbox.clearFailed()

    internal fun publish(trips: List<Trip>) {
        _trips.value = trips
        session.cachedTrips = trips
    }

    private suspend fun <T> enqueue(kind: OutboxKind, tripId: Long, id: String, payload: T, serializer: KSerializer<T>) {
        outbox.add(OutboxEntity(id, kind.name, tripId, System.currentTimeMillis() / 1000, DriverApi.json.encodeToString(serializer, payload)))
        SyncWorker.runNow(context)
    }

    private fun updateLocal(tripId: Long, stage: TripStage) {
        publish(
            _trips.value.map {
                if (it.id != tripId) it else it.copy(
                    stage = stage.wire, stageLabel = stage.label, nextStage = stage.next()?.wire,
                    nextStageLabel = stage.next()?.label, needsProof = stage.needsProof, pendingSync = true,
                )
            },
        )
    }

    companion object {
        @Volatile private var instance: DriverRepository? = null

        fun get(context: Context): DriverRepository = instance ?: synchronized(this) {
            instance ?: run {
                val app = context.applicationContext
                val session = Session(app)
                DriverRepository(app, session, DriverApi.create(session), AppDatabase.get(app).outbox()).also { instance = it }
            }
        }
    }
}
