package ng.gea.driver.data

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/** Token and profile, encrypted at rest. Clearing it signs the driver out. */
class Session(context: Context) {
    private val prefs: SharedPreferences = EncryptedSharedPreferences.create(
        context,
        "gea_session",
        MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
    )

    var token: String?
        get() = prefs.getString("token", null)
        set(value) = prefs.edit().putString("token", value).apply()

    var profile: DriverProfile?
        get() = prefs.getString("profile", null)?.let { DriverApi.json.decodeFromString(DriverProfile.serializer(), it) }
        set(value) = prefs.edit().putString("profile", value?.let { DriverApi.json.encodeToString(DriverProfile.serializer(), it) }).apply()

    /** Last trip list from the server, shown when the phone is offline. */
    var cachedTrips: List<Trip>
        get() = prefs.getString("trips", null)?.let { DriverApi.json.decodeFromString(TripsResponse.serializer(), it).trips }.orEmpty()
        set(value) = prefs.edit().putString("trips", DriverApi.json.encodeToString(TripsResponse.serializer(), TripsResponse(value))).apply()

    val isSignedIn: Boolean get() = token != null

    fun clear() = prefs.edit().clear().apply()
}
