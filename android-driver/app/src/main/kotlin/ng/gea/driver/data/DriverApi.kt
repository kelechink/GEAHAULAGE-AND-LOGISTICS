package ng.gea.driver.data

import kotlinx.serialization.json.Json
import ng.gea.driver.BuildConfig
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.RequestBody
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.PartMap
import retrofit2.http.Path
import java.util.concurrent.TimeUnit

/** The Laravel driver API (backend/routes/api.php, prefix /api/driver). */
interface DriverApi {
    @POST("driver/login")
    suspend fun login(@Body body: LoginRequest): LoginResponse

    @GET("driver/me")
    suspend fun me(): MeResponse

    @POST("driver/logout")
    suspend fun logout()

    @GET("driver/trips")
    suspend fun trips(): TripsResponse

    @POST("driver/positions")
    suspend fun positions(@Body body: PositionBatch): PositionResult

    @POST("driver/trips/{id}/stage")
    suspend fun stage(@Path("id") tripId: Long, @Body body: StagePayload): TripResponse

    @Multipart
    @POST("driver/trips/{id}/deliver")
    suspend fun deliver(
        @Path("id") tripId: Long,
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part photo: MultipartBody.Part?,
        @Part signature: MultipartBody.Part?,
    ): TripResponse

    @Multipart
    @POST("driver/trips/{id}/issues")
    suspend fun issue(
        @Path("id") tripId: Long,
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part photo: MultipartBody.Part?,
    ): TripResponse

    companion object {
        val json = Json { ignoreUnknownKeys = true; explicitNulls = false; encodeDefaults = true }

        fun create(session: Session): DriverApi {
            val client = OkHttpClient.Builder()
                .connectTimeout(20, TimeUnit.SECONDS)
                .readTimeout(60, TimeUnit.SECONDS)
                .writeTimeout(120, TimeUnit.SECONDS) // proof photos on slow 3G
                .addInterceptor { chain ->
                    val b = chain.request().newBuilder()
                        .header("Accept", "application/json")
                        .header("X-App-Version", BuildConfig.VERSION_NAME)
                    session.token?.let { b.header("Authorization", "Bearer $it") }
                    chain.proceed(b.build())
                }
                .build()

            return Retrofit.Builder()
                .baseUrl(BuildConfig.API_BASE_URL)
                .client(client)
                .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
                .build()
                .create(DriverApi::class.java)
        }
    }
}
