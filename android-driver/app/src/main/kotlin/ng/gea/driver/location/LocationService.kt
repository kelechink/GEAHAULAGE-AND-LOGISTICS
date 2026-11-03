package ng.gea.driver.location

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.location.Location
import android.os.Build
import android.os.IBinder
import android.os.Looper
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import ng.gea.driver.R
import ng.gea.driver.core.ClientEventIds
import ng.gea.driver.core.Fix
import ng.gea.driver.core.LocationPolicy
import ng.gea.driver.data.DriverRepository
import ng.gea.driver.data.PositionPayload
import ng.gea.driver.sync.SyncWorker
import ng.gea.driver.ui.MainActivity
import java.time.Instant

/**
 * Foreground service that records the truck's position while a trip is on the road (PRD 13):
 * every 60 s while moving, every 5 min parked. Fixes go to the outbox and are uploaded in batches.
 * Stops itself when no trip needs tracking.
 */
class LocationService : Service() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val client by lazy { LocationServices.getFusedLocationProviderClient(this) }
    private lateinit var repo: DriverRepository
    private lateinit var policy: LocationPolicy
    private var last: Fix? = null
    private var currentInterval = 0L
    private var keptSinceUpload = 0

    private val callback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            result.locations.forEach(::onFix)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        repo = DriverRepository.get(this)
        val profile = repo.session.profile
        policy = LocationPolicy(profile?.movingIntervalS ?: 60, profile?.parkedIntervalS ?: 300)
        startInForeground()
        request(policy.parkedIntervalSeconds)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (repo.activeTrip() == null) stopSelf()
        return START_STICKY
    }

    override fun onDestroy() {
        client.removeLocationUpdates(callback)
        scope.cancel()
        super.onDestroy()
    }

    private fun onFix(loc: Location) {
        val trip = repo.activeTrip() ?: run { stopSelf(); return }
        val fix = Fix(
            lat = loc.latitude, lng = loc.longitude, recordedAtEpochS = loc.time / 1000,
            speedKmh = if (loc.hasSpeed()) loc.speed * 3.6 else null,
            accuracyM = if (loc.hasAccuracy()) loc.accuracy.toDouble() else null,
            heading = if (loc.hasBearing()) loc.bearing.toDouble() else null,
        )
        val interval = policy.intervalSeconds(fix.speedKmh)
        if (interval != currentInterval) request(interval)
        if (!policy.shouldKeep(fix, last)) return
        last = fix

        scope.launch {
            repo.recordPosition(
                PositionPayload(
                    clientEventId = ClientEventIds.new(), shipmentId = trip.id, lat = fix.lat, lng = fix.lng,
                    recordedAt = Instant.ofEpochSecond(fix.recordedAtEpochS).toString(),
                    speedKmh = fix.speedKmh, accuracyM = fix.accuracyM, heading = fix.heading,
                ),
            )
            // Upload every 5 fixes; WorkManager waits for signal if there is none.
            if (++keptSinceUpload >= 5) {
                keptSinceUpload = 0
                SyncWorker.runNow(this@LocationService)
            }
        }
    }

    @SuppressLint("MissingPermission") // MainActivity asks before starting the service.
    private fun request(intervalSeconds: Long) {
        currentInterval = intervalSeconds
        client.removeLocationUpdates(callback)
        val req = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, intervalSeconds * 1000)
            .setMinUpdateIntervalMillis(intervalSeconds * 500)
            .build()
        client.requestLocationUpdates(req, callback, Looper.getMainLooper())
    }

    private fun startInForeground() {
        val nm = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            nm.createNotificationChannel(NotificationChannel(CHANNEL, getString(R.string.tracking_channel), NotificationManager.IMPORTANCE_LOW))
        }
        val open = PendingIntent.getActivity(this, 0, Intent(this, MainActivity::class.java), PendingIntent.FLAG_IMMUTABLE)
        val n: Notification = NotificationCompat.Builder(this, CHANNEL)
            .setSmallIcon(R.drawable.ic_launcher)
            .setContentTitle(getString(R.string.tracking_title))
            .setContentText(getString(R.string.tracking_body))
            .setOngoing(true)
            .setContentIntent(open)
            .build()
        val type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION else 0
        ServiceCompat.startForeground(this, 41, n, type)
    }

    companion object {
        private const val CHANNEL = "tracking"

        fun start(context: Context) = ContextCompat.startForegroundService(context, Intent(context, LocationService::class.java))

        fun stop(context: Context) = context.stopService(Intent(context, LocationService::class.java))
    }
}
