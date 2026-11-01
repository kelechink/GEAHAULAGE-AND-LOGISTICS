package ng.gea.driver.ui

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

val Navy = Color(0xFF172F66)
val Gold = Color(0xFFF5C400)
val Alert = Color(0xFFC8102E)

/** Large type and high contrast: the app is used in a cab, often in sunlight, sometimes with gloves. */
@Composable
fun GeaTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = lightColorScheme(
            primary = Navy, onPrimary = Color.White, secondary = Gold, onSecondary = Navy,
            error = Alert, background = Color.White, surface = Color.White, surfaceVariant = Color(0xFFF1F4F9),
        ),
        typography = Typography(
            headlineSmall = TextStyle(fontSize = 26.sp, fontWeight = FontWeight.Bold),
            titleLarge = TextStyle(fontSize = 22.sp, fontWeight = FontWeight.SemiBold),
            bodyLarge = TextStyle(fontSize = 18.sp),
            bodyMedium = TextStyle(fontSize = 16.sp),
            labelLarge = TextStyle(fontSize = 18.sp, fontWeight = FontWeight.SemiBold),
        ),
        content = content,
    )
}
