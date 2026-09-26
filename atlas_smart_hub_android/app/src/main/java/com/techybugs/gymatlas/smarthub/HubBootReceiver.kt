package com.techybugs.gymatlas.smarthub

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class HubBootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED && intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return
        val store = SecureCredentialStore(context)
        if (!store.shouldRun() || store.load() == null) return

        context.startForegroundService(Intent(context, HubForegroundService::class.java))
    }
}
