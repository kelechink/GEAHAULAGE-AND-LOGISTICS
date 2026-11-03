package ng.gea.driver.sync

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.OutOfQuotaPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import ng.gea.driver.core.OutboxKind
import ng.gea.driver.core.RetryPolicy
import ng.gea.driver.core.SyncPlanner
import ng.gea.driver.data.AppDatabase
import ng.gea.driver.data.DeliveryPayload
import ng.gea.driver.data.DriverApi
import ng.gea.driver.data.DriverRepository
import ng.gea.driver.data.IssuePayload
import ng.gea.driver.data.OutboxEntity
import ng.gea.driver.data.PositionBatch
import ng.gea.driver.data.PositionPayload
import ng.gea.driver.data.StagePayload
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException
import java.io.File
import java.io.IOException
import java.util.concurrent.TimeUnit

/** Drains the outbox whenever there is a network. Safe to run twice: the server dedupes on client_event_id. */
class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    private val repo = DriverRepository.get(context)
    private val dao = AppDatabase.get(context).outbox()
    private val api = DriverApi.create(repo.session)

    override suspend fun doWork(): Result {
        if (!repo.session.isSignedIn) return Result.success()
        val now = System.currentTimeMillis() / 1000
        val byId = dao.pending().associateBy { it.clientEventId }
        val steps = SyncPlanner().plan(byId.values.map { it.toCore() }, now)
        var retry = false

        for (step in steps) {
            val items = when (step) {
                is SyncPlanner.Step.Positions -> step.items.mapNotNull { byId[it.clientEventId] }
                is SyncPlanner.Step.Single -> listOfNotNull(byId[step.item.clientEventId])
            }
            try {
                send(items)
                dao.remove(items.map { it.clientEventId })
                items.forEach { deleteFiles(it) }
            } catch (e: HttpException) {
                if (e.code() == 401) {
                    repo.session.clear()
                    return Result.success()
                }
                if (RetryPolicy.isPermanent(e.code()) && items.size == 1) {
                    val msg = e.response()?.errorBody()?.string()?.let(::messageOf) ?: "Refused by the server (${e.code()})."
                    dao.markFailed(items.single().clientEventId, msg)
                } else {
                    dao.backoff(items.map { it.clientEventId }, now + RetryPolicy.delaySeconds(items.first().attempts + 1))
                    retry = true
                }
                if (items.first().kind != OutboxKind.POSITION.name) break // keep stage events in order
            } catch (e: IOException) {
                retry = true
                break // no signal: try the whole queue later
            }
        }

        repo.refreshTrips()
        return if (retry) Result.retry() else Result.success()
    }

    private suspend fun send(items: List<OutboxEntity>) {
        val first = items.first()
        val json = DriverApi.json
        when (OutboxKind.valueOf(first.kind)) {
            OutboxKind.POSITION -> api.positions(PositionBatch(items.map { json.decodeFromString(PositionPayload.serializer(), it.payloadJson) }))
            OutboxKind.STAGE -> api.stage(first.tripId, json.decodeFromString(StagePayload.serializer(), first.payloadJson))
            OutboxKind.DELIVERY -> {
                val p = json.decodeFromString(DeliveryPayload.serializer(), first.payloadJson)
                api.deliver(
                    first.tripId,
                    fields("client_event_id" to p.clientEventId, "receiver_name" to p.receiverName, "lat" to p.lat?.toString(), "lng" to p.lng?.toString()),
                    filePart("photo", p.photoFile),
                    filePart("signature", p.signatureFile),
                )
            }
            OutboxKind.ISSUE -> {
                val p = json.decodeFromString(IssuePayload.serializer(), first.payloadJson)
                api.issue(
                    first.tripId,
                    fields("client_event_id" to p.clientEventId, "type" to p.type, "note" to p.note, "lat" to p.lat?.toString(), "lng" to p.lng?.toString()),
                    filePart("photo", p.photoFile),
                )
            }
        }
    }

    private fun fields(vararg pairs: Pair<String, String?>): Map<String, RequestBody> =
        pairs.mapNotNull { (k, v) -> v?.let { k to it.toRequestBody("text/plain".toMediaType()) } }.toMap()

    private fun filePart(name: String, path: String?): MultipartBody.Part? {
        val file = path?.let(::File)?.takeIf { it.exists() } ?: return null
        val type = if (file.extension == "png") "image/png" else "image/jpeg"
        return MultipartBody.Part.createFormData(name, file.name, file.asRequestBody(type.toMediaType()))
    }

    private fun deleteFiles(item: OutboxEntity) {
        val json = DriverApi.json
        when (item.kind) {
            OutboxKind.DELIVERY.name -> json.decodeFromString(DeliveryPayload.serializer(), item.payloadJson)
                .let { listOfNotNull(it.photoFile, it.signatureFile) }
            OutboxKind.ISSUE.name -> listOfNotNull(json.decodeFromString(IssuePayload.serializer(), item.payloadJson).photoFile)
            else -> emptyList()
        }.forEach { File(it).delete() }
    }

    private fun messageOf(body: String): String? =
        Regex("\"message\"\\s*:\\s*\"([^\"]+)\"").find(body)?.groupValues?.get(1)

    companion object {
        private val online = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

        /** Send as soon as possible (expedited when the OS allows it). */
        fun runNow(context: Context) {
            val req = OneTimeWorkRequestBuilder<SyncWorker>()
                .setConstraints(online)
                .setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()
            WorkManager.getInstance(context).enqueueUniqueWork("sync-now", ExistingWorkPolicy.APPEND_OR_REPLACE, req)
        }

        /** Safety net: every 15 minutes (the Android minimum) while signed in. */
        fun schedulePeriodic(context: Context) {
            val req = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES).setConstraints(online).build()
            WorkManager.getInstance(context).enqueueUniquePeriodicWork("sync-periodic", ExistingPeriodicWorkPolicy.KEEP, req)
        }
    }
}
