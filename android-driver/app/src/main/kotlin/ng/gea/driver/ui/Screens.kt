package ng.gea.driver.ui

import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import kotlinx.coroutines.launch
import ng.gea.driver.core.TripStage
import ng.gea.driver.data.Trip
import java.io.File
import java.io.FileOutputStream

typealias SnackHost = @Composable () -> Unit
typealias Locate = suspend () -> Pair<Double, Double>?

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun Bar(title: String, onBack: (() -> Unit)? = null, actions: @Composable () -> Unit = {}) {
    TopAppBar(
        title = { Text(title, color = Color.White) },
        navigationIcon = {
            if (onBack != null) IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Color.White) }
        },
        actions = { actions() },
        colors = TopAppBarDefaults.topAppBarColors(containerColor = Navy),
    )
}

@Composable
private fun BigButton(text: String, enabled: Boolean = true, color: Color = Navy, onClick: () -> Unit) {
    Button(
        onClick = onClick, enabled = enabled,
        modifier = Modifier.fillMaxWidth().height(64.dp),
        colors = ButtonDefaults.buttonColors(containerColor = color),
        shape = RoundedCornerShape(12.dp),
    ) { Text(text, style = MaterialTheme.typography.labelLarge) }
}

@Composable
fun LoginScreen(vm: DriverViewModel, snack: SnackHost) {
    var phone by remember { mutableStateOf("") }
    var pin by remember { mutableStateOf("") }
    val busy by vm.busy.collectAsState()
    Scaffold(snackbarHost = snack) { pad ->
        Column(Modifier.padding(pad).padding(24.dp).fillMaxSize(), verticalArrangement = Arrangement.Center) {
            Text("GEA Driver", style = MaterialTheme.typography.headlineSmall, color = Navy)
            Text("Sign in with your phone number and the PIN Operations gave you.", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(24.dp))
            OutlinedTextField(phone, { phone = it }, label = { Text("Phone number") }, singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))
            OutlinedTextField(pin, { if (it.length <= 6) pin = it.filter(Char::isDigit) }, label = { Text("PIN") }, singleLine = true,
                visualTransformation = PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(24.dp))
            BigButton(if (busy) "Signing in…" else "Sign in", enabled = !busy) { vm.login(phone, pin) }
        }
    }
}

@Composable
fun TripsScreen(vm: DriverViewModel, snack: SnackHost) {
    val trips by vm.trips.collectAsState()
    val pending by vm.pending.collectAsState()
    val failed by vm.failed.collectAsState()
    val busy by vm.busy.collectAsState()
    val name = vm.repo.session.profile?.name.orEmpty()
    Scaffold(
        topBar = {
            Bar("My trips") {
                IconButton(onClick = vm::refresh) { Icon(Icons.Default.Refresh, "Refresh", tint = Color.White) }
                IconButton(onClick = vm::logout) { Icon(Icons.AutoMirrored.Filled.Logout, "Sign out", tint = Color.White) }
            }
        },
        snackbarHost = snack,
    ) { pad ->
        LazyColumn(Modifier.padding(pad).fillMaxSize(), contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item { Text("Hello $name", style = MaterialTheme.typography.titleLarge) }
            if (pending > 0) item { SyncBanner(pending) }
            if (failed.isNotEmpty()) item {
                Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFFDECEE))) {
                    Column(Modifier.padding(16.dp)) {
                        Text("${failed.size} update(s) were refused by the office system:", color = Alert)
                        failed.take(3).forEach { Text("• ${it.failedMessage}") }
                        TextButton(onClick = vm::dismissFailed) { Text("OK, I will call Operations") }
                    }
                }
            }
            if (busy && trips.isEmpty()) item { CircularProgressIndicator() }
            if (!busy && trips.isEmpty()) item { Text("No trips assigned to you right now.", style = MaterialTheme.typography.bodyLarge) }
            items(trips, key = { it.id }) { t -> TripCard(t) { vm.screen.value = Screen.Detail(t.id) } }
        }
    }
}

@Composable
private fun SyncBanner(pending: Int) {
    Row(Modifier.fillMaxWidth().background(Color(0xFFFFF6CC), RoundedCornerShape(8.dp)).padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Default.CloudOff, null, tint = Navy)
        Spacer(Modifier.padding(4.dp))
        Text("$pending update(s) waiting for signal. They send automatically.")
    }
}

@Composable
private fun TripCard(t: Trip, onClick: () -> Unit) {
    Card(Modifier.fillMaxWidth().clickable(onClick = onClick), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
        Column(Modifier.padding(16.dp)) {
            Text("${t.origin} → ${t.destination}", style = MaterialTheme.typography.titleLarge)
            Text("${t.cargo}${t.tonnage?.let { " · $it t" } ?: ""}")
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                StageChip(t.stageLabel, t.stage)
                Spacer(Modifier.padding(4.dp))
                Text(t.trackingCode, style = MaterialTheme.typography.bodyMedium)
                if (t.pendingSync) Text("  · not sent yet", color = Color(0xFF8A6D00))
            }
        }
    }
}

@Composable
private fun StageChip(label: String, stage: String) {
    val bg = when (stage) {
        "delayed" -> Alert
        "in_transit" -> Navy
        "arrived", "delivered" -> Color(0xFF1B7F4B)
        else -> Color(0xFF55607A)
    }
    Text(label, color = Color.White, modifier = Modifier.background(bg, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp))
}

@Composable
fun TripDetailScreen(vm: DriverViewModel, tripId: Long, snack: SnackHost, locate: Locate) {
    val trips by vm.trips.collectAsState()
    val busy by vm.busy.collectAsState()
    val t = trips.firstOrNull { it.id == tripId }
    if (t == null) {
        LaunchedEffect(tripId) { vm.screen.value = Screen.Trips }
        return
    }
    val stage = TripStage.fromWire(t.stage)
    val scope = rememberCoroutineScope()
    val ctx = LocalContext.current
    val opsPhone = vm.repo.session.profile?.operationsPhone
    Scaffold(topBar = { Bar(t.trackingCode, onBack = { vm.screen.value = Screen.Trips }) }, snackbarHost = snack) { pad ->
        Column(Modifier.padding(pad).padding(16.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            StageChip(t.stageLabel, t.stage)
            Text("${t.origin} → ${t.destination}", style = MaterialTheme.typography.headlineSmall)
            Detail("Customer", t.customer)
            Detail("Cargo", t.cargo + (t.tonnage?.let { " · $it tonnes" } ?: ""))
            t.pickupDate?.let { Detail("Pickup", it) }
            t.waybillNumber?.let { Detail("Waybill", it) }
            t.truck?.let { Detail("Truck", it) }
            t.eta?.let { Detail("ETA", it) }
            if (t.pendingSync) Text("Saved on the phone. It will reach the office when you have signal.", color = Color(0xFF8A6D00))
            Spacer(Modifier.height(8.dp))

            val action = stage.actionLabel()
            if (action != null) {
                if (stage.needsProof) {
                    BigButton(action, color = Color(0xFF1B7F4B)) { vm.screen.value = Screen.Deliver(t.id) }
                } else {
                    BigButton(action, enabled = !busy) {
                        scope.launch { val loc = locate(); vm.advance(t, loc?.first, loc?.second) }
                    }
                }
            }
            if (stage in setOf(TripStage.LOADING, TripStage.LOADED, TripStage.IN_TRANSIT, TripStage.DELAYED, TripStage.ARRIVED)) {
                OutlinedButton(onClick = { vm.screen.value = Screen.Issue(t.id) }, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                    Icon(Icons.Default.Warning, null, tint = Alert); Spacer(Modifier.padding(4.dp)); Text("Report a problem")
                }
            }
            if (opsPhone != null) {
                OutlinedButton(onClick = { ctx.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:$opsPhone"))) },
                    modifier = Modifier.fillMaxWidth().height(56.dp)) {
                    Icon(Icons.Default.Phone, null); Spacer(Modifier.padding(4.dp)); Text("Call Operations")
                }
            }
            t.destinationLat?.let { lat ->
                TextButton(onClick = {
                    ctx.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("google.navigation:q=$lat,${t.destinationLng}")))
                }) { Text("Open directions to ${t.destination}") }
            }
        }
    }
}

@Composable
private fun Detail(label: String, value: String) {
    Row { Text("$label: ", color = Color(0xFF55607A)); Text(value, style = MaterialTheme.typography.bodyLarge) }
}

@Composable
fun DeliverScreen(vm: DriverViewModel, tripId: Long, snack: SnackHost, locate: Locate) {
    val t = vm.trip(tripId) ?: return
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val busy by vm.busy.collectAsState()
    var receiver by remember { mutableStateOf("") }
    var photo by remember { mutableStateOf<File?>(null) }
    var pending by remember { mutableStateOf<File?>(null) }
    val strokes = remember { mutableStateListOf<List<Offset>>() }
    var current by remember { mutableStateOf<List<Offset>>(emptyList()) }
    var padSize by remember { mutableStateOf(IntSize.Zero) }
    val takePhoto = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok -> if (ok) photo = pending }

    Scaffold(topBar = { Bar("Confirm delivery", onBack = { vm.screen.value = Screen.Detail(t.id) }) }, snackbarHost = snack) { pad ->
        Column(Modifier.padding(pad).padding(16.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("${t.trackingCode} · ${t.destination}", style = MaterialTheme.typography.titleLarge)
            OutlinedTextField(receiver, { receiver = it }, label = { Text("Receiver's name") }, singleLine = true, modifier = Modifier.fillMaxWidth())
            OutlinedButton(onClick = {
                val f = proofFile(ctx, "photo", "jpg").also { pending = it }
                takePhoto.launch(FileProvider.getUriForFile(ctx, ctx.packageName + ".files", f))
            }, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                Text(if (photo == null) "Take photo of goods / signed waybill" else "Photo taken ✓  (tap to retake)")
            }
            Text("Receiver signs here:")
            Box(
                Modifier.fillMaxWidth().height(200.dp).border(2.dp, Navy, RoundedCornerShape(8.dp)).background(Color.White)
                    .onSizeChanged { padSize = it }
                    .pointerInput(Unit) {
                        detectDragGestures(
                            onDragStart = { current = listOf(it) },
                            onDrag = { change, _ -> current = current + change.position },
                            onDragEnd = { strokes.add(current); current = emptyList() },
                        )
                    },
            ) {
                androidx.compose.foundation.Canvas(Modifier.fillMaxSize()) {
                    (strokes + listOf(current)).forEach { s ->
                        s.zipWithNext().forEach { (a, b) -> drawLine(Color.Black, a, b, strokeWidth = 5f, cap = StrokeCap.Round) }
                    }
                }
            }
            TextButton(onClick = { strokes.clear() }) { Text("Clear signature") }
            BigButton(if (busy) "Saving…" else "Confirm delivery", enabled = !busy && receiver.isNotBlank() && (photo != null || strokes.isNotEmpty()),
                color = Color(0xFF1B7F4B)) {
                scope.launch {
                    val sig = if (strokes.isNotEmpty()) saveSignature(ctx, strokes, padSize) else null
                    val loc = locate()
                    vm.deliver(t, receiver.trim(), photo?.absolutePath, sig?.absolutePath, loc?.first, loc?.second)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun IssueScreen(vm: DriverViewModel, tripId: Long, snack: SnackHost, locate: Locate) {
    val t = vm.trip(tripId) ?: return
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val busy by vm.busy.collectAsState()
    var type by remember { mutableStateOf("breakdown") }
    var note by remember { mutableStateOf("") }
    var photo by remember { mutableStateOf<File?>(null) }
    var pending by remember { mutableStateOf<File?>(null) }
    val takePhoto = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok -> if (ok) photo = pending }
    val types = listOf("breakdown" to "Breakdown", "accident" to "Accident", "delay" to "Delay", "checkpoint" to "Checkpoint")

    Scaffold(topBar = { Bar("Report a problem", onBack = { vm.screen.value = Screen.Detail(t.id) }) }, snackbarHost = snack) { pad ->
        Column(Modifier.padding(pad).padding(16.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                types.forEach { (k, label) -> FilterChip(selected = type == k, onClick = { type = k }, label = { Text(label) }) }
            }
            OutlinedTextField(note, { note = it }, label = { Text(if (type == "delay") "What is the delay? (needed)" else "What happened?") },
                minLines = 3, modifier = Modifier.fillMaxWidth())
            OutlinedButton(onClick = {
                val f = proofFile(ctx, "issue", "jpg").also { pending = it }
                takePhoto.launch(FileProvider.getUriForFile(ctx, ctx.packageName + ".files", f))
            }, modifier = Modifier.fillMaxWidth()) { Text(if (photo == null) "Add a photo (optional)" else "Photo added ✓") }
            BigButton(if (busy) "Sending…" else "Send to Operations", enabled = !busy && (type != "delay" || note.isNotBlank()), color = Alert) {
                scope.launch {
                    val loc = locate()
                    vm.reportIssue(t, type, note.ifBlank { null }, photo?.absolutePath, loc?.first, loc?.second)
                }
            }
        }
    }
}

private fun proofFile(ctx: Context, prefix: String, ext: String): File {
    val dir = File(ctx.filesDir, "proofs").apply { mkdirs() }
    return File(dir, "$prefix-${System.currentTimeMillis()}.$ext")
}

private fun saveSignature(ctx: Context, strokes: List<List<Offset>>, size: IntSize): File {
    val w = size.width.coerceAtLeast(1)
    val h = size.height.coerceAtLeast(1)
    val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(bmp).apply { drawColor(android.graphics.Color.WHITE) }
    val paint = Paint().apply { color = android.graphics.Color.BLACK; strokeWidth = 5f; strokeCap = Paint.Cap.ROUND; isAntiAlias = true }
    strokes.forEach { s -> s.zipWithNext().forEach { (a, b) -> canvas.drawLine(a.x, a.y, b.x, b.y, paint) } }
    val f = proofFile(ctx, "signature", "png")
    FileOutputStream(f).use { bmp.compress(Bitmap.CompressFormat.PNG, 100, it) }
    return f
}
