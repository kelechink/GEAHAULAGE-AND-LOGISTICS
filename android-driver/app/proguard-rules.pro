# kotlinx.serialization
-keepattributes *Annotation*, InnerClasses
-keepclassmembers class ng.gea.driver.data.** { *** Companion; }
-keepclasseswithmembers class ng.gea.driver.data.** { kotlinx.serialization.KSerializer serializer(...); }
# Retrofit
-keepattributes Signature, Exceptions
-keep,allowobfuscation interface ng.gea.driver.data.DriverApi
