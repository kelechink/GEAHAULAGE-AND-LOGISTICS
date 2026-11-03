package ng.gea.driver.data

import android.content.Context
import androidx.room.Dao
import androidx.room.Database
import androidx.room.Entity
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.Room
import androidx.room.RoomDatabase
import kotlinx.coroutines.flow.Flow
import ng.gea.driver.core.OutboxItem
import ng.gea.driver.core.OutboxKind

/** Durable queue of everything waiting to reach the server (PRD 13: offline queue). */
@Entity(tableName = "outbox")
data class OutboxEntity(
    @PrimaryKey val clientEventId: String,
    val kind: String,
    val tripId: Long,
    val createdAtEpochS: Long,
    val payloadJson: String,
    val attempts: Int = 0,
    val nextAttemptAtEpochS: Long = 0,
    /** Set when the server refused it for good (e.g. a 422); kept so the driver can see and call the office. */
    val failedMessage: String? = null,
) {
    fun toCore() = OutboxItem(clientEventId, OutboxKind.valueOf(kind), tripId, createdAtEpochS, payloadJson, attempts, nextAttemptAtEpochS)
}

@Dao
interface OutboxDao {
    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun add(item: OutboxEntity)

    @Query("SELECT * FROM outbox WHERE failedMessage IS NULL ORDER BY createdAtEpochS")
    suspend fun pending(): List<OutboxEntity>

    @Query("SELECT COUNT(*) FROM outbox WHERE failedMessage IS NULL")
    fun pendingCount(): Flow<Int>

    @Query("SELECT * FROM outbox WHERE failedMessage IS NOT NULL ORDER BY createdAtEpochS DESC")
    fun failed(): Flow<List<OutboxEntity>>

    @Query("SELECT DISTINCT tripId FROM outbox WHERE failedMessage IS NULL AND kind != 'POSITION'")
    suspend fun tripsWithPendingActions(): List<Long>

    @Query("DELETE FROM outbox WHERE clientEventId IN (:ids)")
    suspend fun remove(ids: List<String>)

    @Query("UPDATE outbox SET attempts = attempts + 1, nextAttemptAtEpochS = :next WHERE clientEventId IN (:ids)")
    suspend fun backoff(ids: List<String>, next: Long)

    @Query("UPDATE outbox SET failedMessage = :message WHERE clientEventId = :id")
    suspend fun markFailed(id: String, message: String)

    @Query("DELETE FROM outbox WHERE failedMessage IS NOT NULL")
    suspend fun clearFailed()

    @Query("DELETE FROM outbox")
    suspend fun clearAll()
}

@Database(entities = [OutboxEntity::class], version = 1, exportSchema = false)
abstract class AppDatabase : RoomDatabase() {
    abstract fun outbox(): OutboxDao

    companion object {
        @Volatile private var instance: AppDatabase? = null

        fun get(context: Context): AppDatabase = instance ?: synchronized(this) {
            instance ?: Room.databaseBuilder(context.applicationContext, AppDatabase::class.java, "gea-driver.db").build().also { instance = it }
        }
    }
}
