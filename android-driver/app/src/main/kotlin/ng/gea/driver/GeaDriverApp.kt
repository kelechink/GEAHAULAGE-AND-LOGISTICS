package ng.gea.driver

import android.app.Application
import ng.gea.driver.data.DriverRepository
import ng.gea.driver.sync.SyncWorker

class GeaDriverApp : Application() {
    override fun onCreate() {
        super.onCreate()
        if (DriverRepository.get(this).session.isSignedIn) SyncWorker.schedulePeriodic(this)
    }
}
