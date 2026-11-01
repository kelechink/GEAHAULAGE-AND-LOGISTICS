package ng.gea.driver.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import ng.gea.driver.data.DriverRepository
import ng.gea.driver.data.OutboxEntity
import ng.gea.driver.data.Trip
import ng.gea.driver.location.LocationService
import ng.gea.driver.sync.SyncWorker

sealed interface Screen {
    data object Login : Screen
    data object Trips : Screen
    data class Detail(val tripId: Long) : Screen
    data class Deliver(val tripId: Long) : Screen
    data class Issue(val tripId: Long) : Screen
}

class DriverViewModel(app: Application) : AndroidViewModel(app) {
    val repo = DriverRepository.get(app)

    val screen = MutableStateFlow<Screen>(if (repo.session.isSignedIn) Screen.Trips else Screen.Login)
    val trips: StateFlow<List<Trip>> = repo.trips
    val pending: StateFlow<Int> = repo.pendingCount.stateIn(viewModelScope, SharingStarted.Eagerly, 0)
    val failed: StateFlow<List<OutboxEntity>> = repo.failed.stateIn(viewModelScope, SharingStarted.Eagerly, emptyList())
    val busy = MutableStateFlow(false)
    val message = MutableStateFlow<String?>(null)

    fun trip(id: Long): Trip? = trips.value.firstOrNull { it.id == id }

    fun login(phone: String, pin: String) = launchBusy {
        repo.login(phone, pin)
            .onSuccess {
                SyncWorker.schedulePeriodic(getApplication())
                screen.value = Screen.Trips
                syncTracking()
            }
            .onFailure { message.value = it.message }
    }

    fun refresh() = launchBusy {
        repo.refreshTrips().onFailure { message.value = "No signal. Showing your last saved trips." }
        if (!repo.session.isSignedIn) screen.value = Screen.Login
        syncTracking()
    }

    fun advance(trip: Trip, lat: Double?, lng: Double?) = launchBusy {
        runCatching { repo.advance(trip, lat = lat, lng = lng) }.onFailure { message.value = it.message }
        syncTracking()
    }

    fun deliver(trip: Trip, receiver: String, photo: String?, signature: String?, lat: Double?, lng: Double?) = launchBusy {
        runCatching { repo.deliver(trip, receiver, photo, signature, lat, lng) }
            .onSuccess {
                message.value = "Delivery saved. It will be sent as soon as you have signal."
                screen.value = Screen.Trips
            }
            .onFailure { message.value = it.message }
        syncTracking()
    }

    fun reportIssue(trip: Trip, type: String, note: String?, photo: String?, lat: Double?, lng: Double?) = launchBusy {
        runCatching { repo.reportIssue(trip, type, note, photo, lat, lng) }
            .onSuccess {
                message.value = "Sent to Operations. They will call you."
                screen.value = Screen.Detail(trip.id)
            }
            .onFailure { message.value = it.message }
    }

    fun dismissFailed() = viewModelScope.launch { repo.dismissFailed() }

    fun logout() = launchBusy {
        LocationService.stop(getApplication())
        repo.logout()
        screen.value = Screen.Login
    }

    /** Runs GPS only while a trip needs it. */
    fun syncTracking() {
        if (repo.activeTrip() != null) LocationService.start(getApplication()) else LocationService.stop(getApplication())
    }

    private fun launchBusy(block: suspend () -> Unit) = viewModelScope.launch {
        busy.value = true
        try {
            block()
        } finally {
            busy.value = false
        }
    }
}
