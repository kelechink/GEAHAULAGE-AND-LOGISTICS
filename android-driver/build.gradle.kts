// The Android build plugins are only put on the classpath when :app is part of the build (an Android SDK was
// found, see settings.gradle.kts). That keeps `gradle :core:test` working on machines without the SDK.
buildscript {
    val withApp = rootProject.findProject(":app") != null
    repositories {
        if (withApp) {
            google()
        }
        mavenCentral()
        gradlePluginPortal()
    }
    dependencies {
        classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:2.0.21")
        if (withApp) {
            classpath("com.android.tools.build:gradle:8.7.3")
            classpath("org.jetbrains.kotlin:compose-compiler-gradle-plugin:2.0.21")
            classpath("org.jetbrains.kotlin:kotlin-serialization:2.0.21")
            classpath("com.google.devtools.ksp:symbol-processing-gradle-plugin:2.0.21-1.0.28")
        }
    }
}
