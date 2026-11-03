package ng.gea.driver.ui

import android.Manifest
import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.core.content.ContextCompat
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.tasks.await

class MainActivity : ComponentActivity() {
    private val vm: DriverViewModel by viewModels()

    private val askBackground = registerForActivityResult(ActivityResultContracts.RequestPermission()) { vm.syncTracking() }

    private val askPermissions = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { granted ->
        if (granted[Manifest.permission.ACCESS_FINE_LOCATION] == true && Build.VERSION.SDK_INT >= 29 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_BACKGROUND_LOCATION) != PackageManager.PERMISSION_GRANTED
        ) {
            // Android only allows "all the time" from the settings screen, so explain first.
            AlertDialog.Builder(this)
                .setTitle("Allow location all the time")
                .setMessage(
                    "So GEA and the customer can follow the truck while your screen is off, choose " +
                        "\"Allow all the time\" on the next screen. Location is only used during an active trip.",
                )
                .setPositiveButton("Continue") { _, _ -> askBackground.launch(Manifest.permission.ACCESS_BACKGROUND_LOCATION) }
                .setNegativeButton("Not now") { _, _ -> vm.syncTracking() }
                .show()
        } else {
            vm.syncTracking()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        askPermissions.launch(
            buildList {
                add(Manifest.permission.ACCESS_FINE_LOCATION)
                add(Manifest.permission.ACCESS_COARSE_LOCATION)
                add(Manifest.permission.CAMERA)
                if (Build.VERSION.SDK_INT >= 33) add(Manifest.permission.POST_NOTIFICATIONS)
            }.toTypedArray(),
        )

        setContent {
            GeaTheme {
                val screen by vm.screen.collectAsState()
                val message by vm.message.collectAsState()
                val snackbar = remember { SnackbarHostState() }
                LaunchedEffect(message) {
                    message?.let { snackbar.showSnackbar(it); vm.message.value = null }
                }
                BackHandler(enabled = screen !is Screen.Trips && screen !is Screen.Login) {
                    vm.screen.value = when (val s = screen) {
                        is Screen.Deliver -> Screen.Detail(s.tripId)
                        is Screen.Issue -> Screen.Detail(s.tripId)
                        else -> Screen.Trips
                    }
                }
                val snackHost = @androidx.compose.runtime.Composable { SnackbarHost(snackbar) }
                when (val s = screen) {
                    Screen.Login -> LoginScreen(vm, snackHost)
                    Screen.Trips -> TripsScreen(vm, snackHost)
                    is Screen.Detail -> TripDetailScreen(vm, s.tripId, snackHost, ::currentLocation)
                    is Screen.Deliver -> DeliverScreen(vm, s.tripId, snackHost, ::currentLocation)
                    is Screen.Issue -> IssueScreen(vm, s.tripId, snackHost, ::currentLocation)
                }
            }
        }
    }

    override fun onResume() {
        super.onResume()
        if (vm.repo.session.isSignedIn) vm.refresh()
    }

    /** One fresh fix to stamp a stage change, delivery or issue; null if location is off or denied. */
    @SuppressLint("MissingPermission")
    suspend fun currentLocation(): Pair<Double, Double>? {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) return null
        return runCatching {
            LocationServices.getFusedLocationProviderClient(this)
                .getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, CancellationTokenSource().token).await()
                ?.let { it.latitude to it.longitude }
        }.getOrNull()
    }
}
